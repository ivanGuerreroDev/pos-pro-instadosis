<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Models\Product;
use App\Services\BatchService;
use App\Services\BatchAllocationService;
use Illuminate\Http\Request;

class ProductBatchController extends Controller
{
    protected $batchService;
    protected $allocationService;

    public function __construct(BatchService $batchService, BatchAllocationService $allocationService)
    {
        $this->batchService = $batchService;
        $this->allocationService = $allocationService;
    }

    /**
     * Display a listing of the batches.
     */
    public function index(Request $request)
    {
        $query = ProductBatch::with(['product:id,productName', 'purchase:id,invoiceNumber'])
            ->where('business_id', auth()->user()->business_id);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter near expiry
        if ($request->has('near_expiry')) {
            $query->nearExpiry($request->near_expiry);
        }

        // Filter out of stock (optional)
        if ($request->has('out_of_stock') && $request->out_of_stock) {
            $query->where('remaining_quantity', '<=', 0);
        } elseif ($request->has('with_stock') && $request->with_stock) {
            $query->where('remaining_quantity', '>', 0);
        }
        // Por defecto, muestra todos los lotes incluyendo los en 0

        // Order by expiry date
        $query->orderBy('expiry_date', 'asc');

        $batches = $query->get();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $batches,
        ]);
    }

    /**
     * Store a newly created batch.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_number' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'purchase_price' => 'nullable|numeric|min:0',
            'manufacture_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:manufacture_date',
            'purchase_id' => 'nullable|exists:purchases,id',
            'notes' => 'nullable|string',
        ]);

        $data = $request->all();
        $data['business_id'] = auth()->user()->business_id;
        $data['reference_type'] = 'Manual';
        $data['reference_id'] = null;

        $batch = $this->batchService->createBatch($data);

        return response()->json([
            'message' => __('Batch created successfully.'),
            'data' => $batch->load('product'),
        ], 201);
    }

    /**
     * Display the specified batch.
     */
    public function show(ProductBatch $productBatch)
    {
        // Verify ownership
        if ($productBatch->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $batch = $productBatch->load([
            'product',
            'purchase',
            'transactions' => function ($query) {
                $query->with('user:id,name')->latest();
            }
        ]);

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $batch,
        ]);
    }

    /**
     * Update the specified batch.
     */
    public function update(Request $request, ProductBatch $productBatch)
    {
        // Verify ownership
        if ($productBatch->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $request->validate([
            'batch_number' => 'nullable|string',
            'manufacture_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $productBatch->update($request->only([
            'batch_number',
            'manufacture_date',
            'expiry_date',
            'notes',
        ]));

        return response()->json([
            'message' => __('Batch updated successfully.'),
            'data' => $productBatch->fresh()->load('product'),
        ]);
    }

    /**
     * Remove the specified batch.
     */
    public function destroy(ProductBatch $productBatch)
    {
        // Verify ownership
        if ($productBatch->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        return response()->json([
            'message' => __('Batch deletion is disabled. Use manual stock adjustments instead.'),
        ], 403);
    }

    /**
     * Discard a batch.
     */
    public function discard(Request $request, ProductBatch $productBatch)
    {
        // Verify ownership
        if ($productBatch->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $request->validate([
            'reason' => 'required|string',
        ]);

        $this->batchService->discardBatch($productBatch, $request->reason);

        return response()->json([
            'message' => __('Batch discarded successfully.'),
            'data' => $productBatch->fresh(),
        ]);
    }

    /**
     * Adjust batch quantity.
     */
    public function adjust(Request $request, ProductBatch $productBatch)
    {
        // Verify ownership
        if ($productBatch->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $request->validate([
            'reason' => 'required|string',
            'observation' => 'nullable|string',
            'new_quantity' => 'nullable|integer|min:0',
            'quantity' => 'nullable|integer|min:0',
            'type' => 'nullable|in:add,subtract',
        ]);

        $newQuantity = $request->input('new_quantity');

        // Support delta-based adjustments from mobile app.
        if ($newQuantity === null && $request->filled('quantity') && $request->filled('type')) {
            $delta = (int) $request->input('quantity');
            $currentQuantity = (int) $productBatch->remaining_quantity;
            $operation = $request->input('type');

            $newQuantity = $operation === 'add'
                ? $currentQuantity + $delta
                : $currentQuantity - $delta;
        }

        if ($newQuantity === null) {
            return response()->json([
                'message' => __('Provide either new_quantity or quantity + type.'),
            ], 422);
        }

        if ((int) $newQuantity < 0) {
            return response()->json([
                'message' => __('Adjusted quantity cannot be negative.'),
            ], 422);
        }

        $reason = trim((string) $request->input('reason'));
        $observation = trim((string) $request->input('observation', ''));
        $notes = 'Motivo: ' . $reason;

        if ($observation !== '') {
            $notes .= "\nObservacion: " . $observation;
        }

        $this->batchService->adjustBatch(
            $productBatch,
            (int) $newQuantity,
            $notes
        );

        return response()->json([
            'message' => __('Batch quantity adjusted successfully.'),
            'data' => $productBatch->fresh(),
        ]);
    }

    /**
     * Get batches for a specific product.
     */
    public function productBatches($productId)
    {
        $product = Product::where('id', $productId)
            ->where('business_id', auth()->user()->business_id)
            ->first();

        if (!$product) {
            return response()->json(['message' => __('Product not found')], 404);
        }

        $query = ProductBatch::where('product_id', $product->id)
            ->where('business_id', auth()->user()->business_id)
            ->with('transactions');

        // Apply filters from request
        if (request()->has('status')) {
            $query->where('status', request('status'));
        }

        if (request()->has('with_stock') && request('with_stock') == 'true') {
            $query->where('remaining_quantity', '>', 0);
        }

        $batches = $query->orderBy('expiry_date', 'asc')->get();

        $summary = $this->batchService->getProductBatchesSummary($product->id);

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $batches,
            'summary' => $summary,
        ]);
    }

    /**
     * Get available batches for sale selection.
     */
    public function availableForSale($productId)
    {
        $product = Product::where('id', $productId)
            ->where('business_id', auth()->user()->business_id)
            ->first();

        if (!$product) {
            return response()->json(['message' => __('Product not found')], 404);
        }

        $availableBatches = $this->allocationService->getAvailableBatchesForProduct($product->id);

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $availableBatches,
        ]);
    }

    /**
     * Get movement history for a specific batch.
     */
    public function transactions(ProductBatch $productBatch)
    {
        if ($productBatch->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => __('Unauthorized')], 403);
        }

        $transactions = $productBatch->transactions()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_type' => $transaction->transaction_type,
                    'type' => $transaction->transaction_type,
                    'quantity' => $transaction->quantity,
                    'reference_type' => $transaction->reference_type,
                    'reference_id' => $transaction->reference_id,
                    'notes' => $transaction->notes,
                    'created_at' => $transaction->created_at,
                    'user' => $transaction->user ? [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->name,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $transactions,
        ]);
    }
}
