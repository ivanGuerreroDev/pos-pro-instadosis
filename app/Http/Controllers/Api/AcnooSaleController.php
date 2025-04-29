<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\Party;
use App\Models\Product;
use App\Models\Business;
use App\Models\SaleDetails;
use App\Services\BillingService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AcnooSaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Sale::with('user:id,name', 'party:id,name,email,phone,type', 'details', 'details.product:id,productName,category_id', 'details.product.category:id,categoryName', 'saleReturns.details')
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
    public function store(Request $request, BillingService $billingService)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'party_id' => 'nullable|exists:parties,id'
        ]);

        $productIds = collect($request->products)->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->get();
        foreach ($products as $key => $product) {
            if ($product->productStock < $request->products[$key]['quantities']) {
                return response()->json([
                    'message' => __($product->productName . ' - stock not available for this product. Available quantity is : '. $product->productStock)
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

        if ($party ?? false && $party->phone) {
            if (env('MESSAGE_ENABLED')) {
                sendMessage($party->phone, saleMessage($sale, $party, $business_name));
            }
        }
         
        // Send sale data to external billing service
        $billingResult = $billingService->sendSaleToExternalBilling($sale, $party);


        return response()->json([
            'message' => __('Data saved successfully.'),
            'data' => $sale->load('user:id,name', 'party:id,name,email,phone,type', 'details', 'details.product:id,productName,category_id', 'details.product.category:id,categoryName', 'saleReturns.details'),
            'billing' => $billingResult
        ]);
    }

    /**
     * get pdf file
     */

    public function getPdf(Request $request, BillingService $billingService)
    {
        $request->validate([
            'sale_id' => 'required|exists:sales,id',
        ]);

        $pdfFile =$billingService->getBillingPdfFile($request->sale_id);
        #pdf file response transform to base64 url
        $base64 = base64_encode($pdfFile);
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
