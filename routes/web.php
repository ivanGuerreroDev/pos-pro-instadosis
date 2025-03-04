<?php

use App\Http\Controllers as Web;
use App\Http\Controllers\Admin as Admin;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', [Web\WebController::class, 'index'])->name('home');
Route::resource('blogs', Web\BlogController::class)->only('index', 'show', 'store');
Route::get('/about-us', [Web\AboutController::class, 'index'])->name('about.index');
Route::get('/plans', [Web\PlanController::class, 'index'])->name('plan.index');

// Business Signup
Route::get('/get-business-categories', [Web\AcnooBusinessController::class, 'getBusinessCategories'])->name('get-business-categories');
Route::post('/businesses', [Web\AcnooBusinessController::class, 'store'])->name('business.store');
Route::post('/verify-code', [Web\AcnooBusinessController::class, 'verifyCode'])->name('business.verify-code');

Route::get('/terms-conditions', [Web\TermServiceController::class, 'index'])->name('term.index');
Route::get('/privacy-policy', [Web\PolicyController::class, 'index'])->name('policy.index');

Route::get('/contact-us', [Web\ContactController::class, 'index'])->name('contact.index');
Route::post('/contact/store', [Web\ContactController::class, 'store'])->name('contact.store');

Route::get('/payments-gateways/{plan_id}/{business_id}', [Web\PaymentController::class, 'index'])->name('payments-gateways.index');
Route::post('/payments/{plan_id}/{gateway_id}', [Web\PaymentController::class, 'payment'])->name('payments-gateways.payment');
Route::get('/payment/success', [Web\PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failed', [Web\PaymentController::class, 'failed'])->name('payment.failed');
Route::post('ssl-commerz/payment/success', [Web\PaymentController::class, 'sslCommerzSuccess']);
Route::post('ssl-commerz/payment/failed', [Web\PaymentController::class, 'sslCommerzFailed']);
Route::get('/order-status', [Web\PaymentController::class, 'orderStatus'])->name('order.status');

// Ubicaciones DGI
Route::get('admin/dgi/districts/{province}', [Admin\DgiUbiCodesController::class, 'getDistricts'])->name('admin.dgi.districts');
Route::get('admin/dgi/townships/{district}', [Admin\DgiUbiCodesController::class, 'getTownships'])->name('admin.dgi.townships');

Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return back()->with('success', __('Cache has been cleared.'));
});

Route::get('/migrate', function () {
    Artisan::call('migrate');
    return redirect('/')->with('message', __('System updated successfully.'));
});

require __DIR__.'/auth.php';
