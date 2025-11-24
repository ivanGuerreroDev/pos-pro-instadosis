<?php

use App\Http\Controllers\Api as Api;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/sign-in', [Api\Auth\AuthController::class, 'login']);
    Route::post('/submit-otp', [Api\Auth\AuthController::class, 'submitOtp']);
    Route::post('/sign-up', [Api\Auth\AuthController::class, 'signUp']);
    Route::post('/resend-otp', [Api\Auth\AuthController::class, 'resendOtp']);

    Route::post('/send-reset-code',[Api\Auth\AcnooForgotPasswordController::class, 'sendResetCode']);
    Route::post('/verify-reset-code',[Api\Auth\AcnooForgotPasswordController::class, 'verifyResetCode']);
    Route::post('/password-reset',[Api\Auth\AcnooForgotPasswordController::class, 'resetPassword']);

    Route::group(['middleware' => ['auth:sanctum']], function () {

        Route::get('summary', [Api\StatisticsController::class, 'summary']);
        Route::get('dashboard', [Api\StatisticsController::class, 'dashboard']);

        Route::apiResource('parties', Api\PartyController::class);
        Route::apiResource('users', Api\AcnooUserController::class)->except('show');
        Route::apiResource('units', Api\UnitController::class)->except('show');
        Route::apiResource('brands', Api\AcnooBrandController::class)->except('show');
        Route::apiResource('categories', Api\AcnooCategoryController::class)->except('show');
        Route::apiResource('products', Api\AcnooProductController::class)->except('show');
        
        // Product Batch Management Routes
        Route::prefix('product-batches')->group(function () {
            Route::get('/', [Api\ProductBatchController::class, 'index']);
            Route::post('/', [Api\ProductBatchController::class, 'store']);
            // Specific routes first (before parameterized routes)
            Route::get('/product/{productId}', [Api\ProductBatchController::class, 'productBatches']);
            Route::get('/product/{productId}/available', [Api\ProductBatchController::class, 'availableForSale']);
            // Parameterized routes after
            Route::get('/{productBatch}', [Api\ProductBatchController::class, 'show']);
            Route::put('/{productBatch}', [Api\ProductBatchController::class, 'update']);
            Route::delete('/{productBatch}', [Api\ProductBatchController::class, 'destroy']);
            Route::post('/{productBatch}/discard', [Api\ProductBatchController::class, 'discard']);
            Route::post('/{productBatch}/adjust', [Api\ProductBatchController::class, 'adjust']);
        });

        // Expired Batch Notifications Routes
        Route::prefix('batch-notifications')->group(function () {
            Route::get('/', [Api\ExpiredBatchNotificationController::class, 'index']);
            Route::get('/unread', [Api\ExpiredBatchNotificationController::class, 'unread']);
            Route::post('/{notification}/read', [Api\ExpiredBatchNotificationController::class, 'markAsRead']);
            Route::delete('/{notification}', [Api\ExpiredBatchNotificationController::class, 'dismiss']);
            Route::get('/stats', [Api\ExpiredBatchNotificationController::class, 'stats']);
        });
        
        Route::apiResource('business-categories', Api\BusinessCategoryController::class)->only('index');
        Route::apiResource('business', Api\BusinessController::class)->only('index', 'store', 'update');
        Route::apiResource('purchase', Api\PurchaseController::class)->except('show');
        Route::apiResource('sales', Api\AcnooSaleController::class)->except('show');
        Route::post('dgi-pdf', [Api\AcnooSaleController::class, 'getPdf']);
        Route::apiResource('sales-return', Api\SaleReturnController::class)->only('index', 'store', 'show');
        Route::apiResource('purchases-return', Api\PurchaseReturnController::class)->only('index', 'store', 'show');
        Route::apiResource('invoices', Api\AcnooInvoiceController::class)->only('index');
        Route::apiResource('dues', Api\AcnooDueController::class)->only('index', 'store');
        Route::apiResource('expense-categories', Api\ExpenseCategoryController::class)->except('show');
        Route::apiResource('expenses', Api\AcnooExpenseController::class)->only('index', 'store');
        Route::apiResource('income-categories', Api\AcnooIncomeCategoryController::class)->except('show');
        Route::apiResource('incomes', Api\AcnooIncomeController::class)->only('index', 'store');

        Route::get('locations/provinces', [Api\DgiUbiCodesController::class, 'getProvinces']);
        Route::get('locations/districts/{province}', [Api\DgiUbiCodesController::class, 'getDistricts']);
        Route::get('locations/townships/{district}', [Api\DgiUbiCodesController::class, 'getTownships']);
        Route::get('locations/{type}', [Api\DgiUbiCodesController::class, 'getByType']);

        Route::apiResource('banners', Api\AcnooBannerController::class)->only('index');
        Route::apiResource('lang', Api\AcnooLanguageController::class)->only('index', 'store');
        Route::apiResource('profile', Api\AcnooProfileController::class)->only('index', 'store');
        Route::apiResource('plans', Api\AcnooSubscriptionsController::class)->only('index');
        Route::apiResource('subscribes', Api\AcnooSubscribesController::class)->only('index');
        Route::apiResource('currencies', Api\AcnooCurrencyController::class)->only('index');

        Route::post('change-password', [Api\AcnooProfileController::class, 'changePassword']);

        Route::get('new-invoice', [Api\AcnooInvoiceController::class, 'newInvoice']);

        Route::get('/sign-out', [Api\Auth\AuthController::class, 'signOut']);
        Route::get('/refresh-token', [Api\Auth\AuthController::class, 'refreshToken']);
    });
});
