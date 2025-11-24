<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\Party;
use App\Models\Product;
use App\Models\Business;
use App\Models\SaleDetails;
use App\Models\BatchSaleDetail;
use App\Services\BillingService;
use App\Services\BatchAllocationService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AcnooSaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Sale::with('user:id,name', 'party:id,name,email,phone,type', 'details', 'details.product:id,productName,category_id', 'details.product.category:id,categoryName', 'details.batchSaleDetails.batch:id,batch_number,expiry_date,remaining_quantity', 'saleReturns.details')
                ->when(request('returned-sales') == "true", function ($query) {
                    $query->whereHas('saleReturns');
                })
                ->where('business_id', auth()->user()->business_id)
                ->latest()
                ->get();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, BillingService $billingService, BatchAllocationService $batchAllocationService)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.batch_allocations' => 'nullable|array', // Manual batch selection
            'products.*.batch_allocations.*.batch_id' => 'required_with:products.*.batch_allocations|exists:product_batches,id',
            'products.*.batch_allocations.*.quantity' => 'required_with:products.*.batch_allocations|numeric|min:0',
            'party_id' => 'nullable|exists:parties,id'
        ]);

        DB::beginTransaction();
        try {
            $productIds = collect($request->products)->pluck('product_id')->toArray();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
            
            // Validate stock availability (considering batches)
            foreach ($request->products as $key => $productData) {
                $product = $products->get($productData['product_id']);
                
                if ($product->track_by_batches) {
                    // For batch-tracked products, check available batch stock
                    $availableStock = $product->activeBatches()->sum('remaining_quantity');
                } else {
                    // For traditional products, check productStock
                    $availableStock = $product->productStock;
                }
                
                if ($availableStock < $productData['quantities']) {
                    return response()->json([
                        'message' => __($product->productName . ' - stock not available for this product. Available quantity is : '. $availableStock)
                    ], 400);
                }
            }

            if ($request->party_id) {
                $party = Party::findOrFail($request->party_id);
            }else{
                if(empty($request->customer_name)){
                    return response()->json([
                       'message' => __('Customer name is required.')
                    ], 400);
                }
                $party = [
                    'name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'type' => 'customer',
                    'business_id' => auth()->user()->business_id,
                    'due' => 0,
                    'meta' => [
                        'customer_phone' => $request->customer_phone
                    ],
                    'invoice_data' => [
                        'dnombRec' => $request->customer_name,
                        'itipoRec' => $request->customerType,
                        'ddirecEm' => $request->customer_address,
                        'dcorElectEmi' => $request->customer_email,
                        'druc' => $request->customer_ruc,
                    ],
                ];
            }

            if ($request->dueAmount) {
                if (!$request->party_id) {
                    return response()->json([
                        'message' => __('You can not sale in due for a walking customer.')
                    ], 400);
                }

                $party->update([
                    'due' => $party->due + $request->dueAmount
                ]);
            }

            $business = Business::findOrFail(auth()->user()->business_id);
            $business_name = $business->companyName;
            $business->update([
                'remainingShopBalance' => $business->remainingShopBalance + $request->paidAmount
            ]);

            $lossProfit = $productIds = collect($request->products)->pluck('lossProfit')->toArray();

            $sale = Sale::create($request->all() + [
                        'user_id' => auth()->id(),
                        'business_id' => auth()->user()->business_id,
                        'lossProfit' => array_sum($lossProfit) - $request->discountAmount,
                        'meta' => [
                            'customer_phone' => $request->customer_phone
                        ],
                    ]);

            $saleDetails = [];
            foreach ($request->products as $key => $productData) {
                $product = $products->get($productData['product_id']);
                
                // Calculate tax based on product tax_rate
                $subtotal = $productData['price'] * $productData['quantities'];
                $taxAmount = $product->calculateTaxAmount($subtotal);
                $totalWithTax = $subtotal + $taxAmount;
                
                // Create sale detail
                $saleDetail = SaleDetails::create([
                    'sale_id' => $sale->id,
                    'price' => $productData['price'],
                    'product_id' => $productData['product_id'],
                    'lossProfit' => $productData['lossProfit'],
                    'quantities' => $productData['quantities'] ?? 0,
                    'subtotal' => $subtotal,
                    'tax_rate' => $product->tax_rate,
                    'tax_amount' => $taxAmount,
                    'total' => $totalWithTax,
                ]);

                // Handle batch allocation
                if ($product->track_by_batches) {
                    // Check if manual batch selection was provided
                    if (isset($productData['batch_allocations']) && !empty($productData['batch_allocations'])) {
                        // Manual batch selection
                        $allocations = $batchAllocationService->allocateBatchesForSale(
                            $productData['product_id'],
                            $productData['quantities'],
                            $productData['batch_allocations']
                        );
                    } else {
                        // Automatic FEFO allocation
                        $allocations = $batchAllocationService->allocateBatchesForSale(
                            $productData['product_id'],
                            $productData['quantities']
                        );
                    }
                    
                    // Create batch sale details
                    foreach ($allocations as $allocation) {
                        BatchSaleDetail::create([
                            'sale_detail_id' => $saleDetail->id,
                            'batch_id' => $allocation['batch_id'],
                            'quantity' => $allocation['quantity'],
                        ]);
                    }
                } else {
                    // Traditional stock decrement for non-batch products
                    $product->decrement('productStock', $productData['quantities']);
                }
            }

            if ($party ?? false && $party->phone) {
                if (env('MESSAGE_ENABLED')) {
                    sendMessage($party->phone, saleMessage($sale, $party, $business_name));
                }
            }
             
            // Send sale data to external billing service
            $billingResult = $billingService->sendSaleToExternalBilling($sale, $party);

            DB::commit();

            return response()->json([
                'message' => __('Data saved successfully.'),
                'data' => $sale->load('user:id,name', 'party:id,name,email,phone,type', 'details', 'details.product:id,productName,category_id', 'details.product.category:id,categoryName', 'saleReturns.details', 'details.batchSaleDetails.batch'),
                'billing' => $billingResult
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => __('Error processing sale: ') . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Post pdf file
     */
    public function getPdf(Request $request, BillingService $billingService)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
        ]);

        $pdfFile = $billingService->getBillingPdfFile($request->sale_id);
        
        // Validación del contenido del PDF
        if (!$pdfFile || empty($pdfFile)) {
            return response()->json([
                'message' => __('PDF file could not be generated.'),
                'data' => null,
            ], 404);
        }
        
        // Codificación a base64
        $base64 = base64_encode($pdfFile);
        
        // Verificar que la codificación se realizó correctamente
        if (empty($base64)) {
            return response()->json([
                'message' => __('Error encoding PDF file.'),
                'data' => null,
            ], 500);
        }
        
        $data = 'data:application/pdf;base64,' . $base64;
        
        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $data,
        ]);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sale $sale, BillingService $billingService)
    {
        $request->validate([
            'products' => 'required|array',
            'party_id' => 'nullable|exists:parties,id',
            'products.*.product_id' => 'required|exists:products,id',
        ]);

        $prevDetails = SaleDetails::where('sale_id', $sale->id)->get();
        $productIds = collect($request->products)->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $key => $product) {
            $prevProduct = $prevDetails->first(function ($item) use($product) {
                                return $item->product_id == $product->id;
                            });

            $product_stock = $prevProduct ? ($product->productStock + $prevProduct->quantities) : $product->productStock;
            if ($product_stock < $request->products[$key]['quantities']) {
                return response()->json([
                    'message' => __($product->productName . ' - stock not available for this product. Available quantity is : '. $product->productStock)
                ], 400);
            }
        }

        foreach ($prevDetails as $prevItem) {
            Product::findOrFail($prevItem->product_id)->increment('productStock', $prevItem->quantities);
        }

        $prevDetails->each->delete();

        $saleDetails = [];
        foreach ($request->products as $key => $productData) {
            $saleDetails[$key] = [
                'sale_id' => $sale->id,
                'price' => $productData['price'],
                'product_id' => $productData['product_id'],
                'lossProfit' => $productData['lossProfit'],
                'quantities' => $productData['quantities'] ?? 0,
            ];

            Product::findOrFail($productData['product_id'])->decrement('productStock', $productData['quantities']);
        }

        SaleDetails::insert($saleDetails);

        if ($sale->dueAmount || $request->dueAmount) {

            $party = Party::findOrFail($request->party_id);
            $party->update([
                'due' => $request->party_id == $sale->party_id ? (($party->due - $sale->dueAmount) + $request->dueAmount) : ($party->due + $request->dueAmount)
            ]);

            if ($request->party_id != $sale->party_id) {
                $prev_party = Party::findOrFail($sale->party_id);
                $prev_party->update([
                    'due' => $prev_party->due - $sale->dueAmount
                ]);
            }
        }

        $business = Business::findOrFail(auth()->user()->business_id);
        $business->update([
            'shopOpeningBalance' => ($business->shopOpeningBalance - $sale->paidAmount) + $request->paidAmount
        ]);

        $lossProfit = $productIds = collect($request->products)->pluck('lossProfit')->toArray();

        $sale->update($request->all() + [
            'user_id' => auth()->id(),
            'business_id' => auth()->user()->business_id,
            'lossProfit' => array_sum($lossProfit) - $request->discountAmount,
            'meta' => [
                'customer_phone' => $request->customer_phone
            ],
        ]);
        
        // Send updated sale data to external billing service
        $billingResult = $billingService->sendSaleToExternalBilling($sale);

        return response()->json([
            'message' => __('Data saved successfully.'),
            'data' => $sale->load('user:id,name', 'party:id,name,email,phone,type', 'details', 'details.product:id,productName,category_id', 'details.product.category:id,categoryName', 'saleReturns.details'),
            'billing' => $billingResult
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        foreach ($sale->details as $product) {
            Product::findOrFail($product->id)->increment('productStock', $product->quantities);
        }

        if ($sale->dueAmount) {
            $party = Party::findOrFail($sale->party_id);
            $party->update([
                'due' => $party->due - $sale->dueAmount
            ]);
        }

        $business = Business::findOrFail(auth()->user()->business_id);
        $business->update([
            'shopOpeningBalance' => $business->shopOpeningBalance - $sale->paidAmount
        ]);

        $sale->delete();

        return response()->json([
            'message' => __('Data deleted successfully.'),
        ]);
    }
}
