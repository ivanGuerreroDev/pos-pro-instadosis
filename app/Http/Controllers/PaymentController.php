<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Gateway;
use App\Models\Business;
use App\Helpers\HasUploader;
use Illuminate\Http\Request;
use App\Models\PlanSubscribe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PaymentController extends Controller
{
    use HasUploader;
    /**
     * Display a listing of the resource.
    */
    public function index($id, $business_id)
    {
        $plan = Plan::findOrFail($id);
        session()->put('business_id', $business_id);
        $gateways = Gateway::with('currency:id,code,rate,symbol,position')->where('status', 1)->get();

        return view('payments.index', compact('gateways', 'plan'));
    }

    /**
     * Store a newly created resource in storage.
    */
    public function payment(Request $request, $plan_id, $gateway_id)
    {
        $request->validate([
            'phone' => 'max:15|min:5',
        ]);

        $plan = Plan::findOrFail($plan_id);
        $gateway = Gateway::findOrFail($gateway_id);
        $business = Business::findOrFail(session("business_id"));

        if ($gateway->is_manual) {
            $request->validate([
                'attachment' => 'required|max:2048|file',
            ]);

            DB::beginTransaction();
            try {

                $has_free_subscriptions = Plan::where('subscriptionPrice', '<=', 0)->orWhere('offerPrice', '<=', 0)->first();

                if ($plan->subscriptionPrice <= 0 && $has_free_subscriptions) {
                    return response()->json([
                        'status' => 406,
                        'message' => __('Sorry, you cannot subscribe to a free plan again.'),
                    ], 406);
                }

                if ($business->will_expire > now() && $request->plan_id == $business->plan_subscribe_id) {
                    return response()->json([
                        'status' => 409,
                        'message' => __('You have already subscribed to this plan. Please try again after - '. formatted_date($business->will_expire)),
                    ], 400);
                }

                $subscribe = PlanSubscribe::create([
                                'plan_id' => $plan->id,
                                'duration' => $plan->duration,
                                'business_id' => $business->id,
                                'price' => $plan->subscriptionPrice,
                                'gateway_id' => $gateway_id,
                            ]);

                sendNotification($subscribe->id, route('admin.subscription-reports.index', ['id' => $subscribe->id]), __('New subscription purchased requested.'));

                DB::commit();
                return redirect(route('order.status', ['status' => 'success']))->with('message', __('New subscription purchased requested.'));

            } catch (\Exception $e) {
                DB::rollback();
                return redirect(route('order.status', ['status' => 'failed']))->with('message', __('Something went wrong!'));
            }
        }

        $amount = $plan->offerPrice ?? $plan->subscriptionPrice;

        if ($gateway->namespace == 'App\Library\SslCommerz') {
            Session::put('fund_callback.success_url', '/payment/success');
            Session::put('fund_callback.cancel_url', '/payment/failed');
        } else {
            Session::put('fund_callback.success_url', '/payment/success');
            Session::put('fund_callback.cancel_url', '/payment/failed');
        }

        $payment_data['currency'] = $gateway->currency->code ?? 'USD';
        $payment_data['email'] = $business->companyName;
        $payment_data['name'] = $business->phoneNumber;
        $payment_data['phone'] = $business->phoneNumber;
        $payment_data['billName'] = __('Make plan purchase payment');
        $payment_data['amount'] = $amount;
        $payment_data['test_mode'] = $gateway->test_mode;
        $payment_data['charge'] = $gateway->charge ?? 0;
        $payment_data['pay_amount'] = round(convert_money($amount, $gateway->currency) + $gateway->charge);
        $payment_data['gateway_id'] = $gateway->id;
        $payment_data['payment_type'] = 'plan_payment';
        $payment_data['request_from'] = 'merchant';

        foreach ($gateway->data ?? [] as $key => $info) {
            $payment_data[$key] = $info;
        }

        session()->put('gateway_id', $gateway->id);
        session()->put('plan', $plan);

        $redirect = $gateway->namespace::make_payment($payment_data);
        return $redirect;
    }

    public function success()
    {
        DB::beginTransaction();
        try {

            $plan = session('plan');
            $gateway_id = session('gateway_id');

            if (!$plan) {
                return redirect(route('order.status', ['status' => 'failed']))->with('error', __('Transaction failed, Please try again.'));
            }

            $business = Business::findOrFail(session("business_id"));
            $has_free_subscriptions = Plan::where('subscriptionPrice', '<=', 0)->orWhere('offerPrice', '<=', 0)->first();

            if ($plan->subscriptionPrice <= 0 && $has_free_subscriptions) {
                return response()->json([
                    'status' => 406,
                    'message' => __('Sorry, you cannot subscribe to a free plan again.'),
                ], 406);
            }

            if ($business->will_expire > now() && $plan->plan_id == $business->plan_subscribe_id) {
                return response()->json([
                    'status' => 409,
                    'message' => __('You have already subscribed to this plan. Please try again after - '. formatted_date($business->will_expire)),
                ], 400);
            }

            $subscribe = PlanSubscribe::create([
                            'plan_id' => $plan->id,
                            'duration' => $plan->duration,
                            'business_id' => $business->id,
                            'price' => $plan->subscriptionPrice,
                            'gateway_id' => $gateway_id,
                            'payment_status' => 'paid',
                        ]);

            $business->update([
                'subscriptionDate' => now(),
                'plan_subscribe_id' => $subscribe->id,
                'will_expire' => now()->addDays($plan->duration),
            ]);

            session()->forget('gateway_id');
            session()->forget('plan');

            DB::commit();
            return redirect(route('order.status', ['status' => 'success']))->with('message', __('New subscription order successfully.'));

        } catch (\Exception $e) {
            DB::rollback();
            return redirect(route('order.status', ['status' => 'failed']))->with('message', __('Something went wrong!'));
        }
    }

    public function failed()
    {
        return redirect(route('order.status', ['status' => 'failed']))->with('error', __('Transaction failed, Please try again.'));
    }

    public function sslCommerzSuccess(Request $request)
    {
        DB::beginTransaction();
        try {

            if (!$request->value_a || !$request->value_b || !$request->value_c) {
                return redirect(route('order.status', ['status' => 'failed']))->with('error', __('Transaction failed, Please try again.'));
            }

            $plan = session('plan');
            $gateway_id = session('gateway_id');
            if (!$plan) {
                return redirect(route('order.status', ['status' => 'failed']))->with('error', __('Transaction failed, Please try again.'));
            }

            $business = Business::findOrFail(session("business_id"));
            $has_free_subscriptions = Plan::where('subscriptionPrice', '<=', 0)->orWhere('offerPrice', '<=', 0)->first();

            if ($plan->subscriptionPrice <= 0 && $has_free_subscriptions) {
                return response()->json([
                    'status' => 406,
                    'message' => __('Sorry, you cannot subscribe to a free plan again.'),
                ], 406);
            }

            if ($business->will_expire > now() && $request->plan_id == $business->plan_subscribe_id) {
                return response()->json([
                    'status' => 409,
                    'message' => __('You have already subscribed to this plan. Please try again after - '. formatted_date($business->will_expire)),
                ], 400);
            }

            $subscribe = PlanSubscribe::create([
                            'plan_id' => $plan->id,
                            'duration' => $plan->duration,
                            'business_id' => $business->id,
                            'price' => $plan->subscriptionPrice,
                            'gateway_id' => $gateway_id,
                            'payment_status' => 'paid',
                        ]);

            $business->update([
                'subscriptionDate' => now(),
                'plan_subscribe_id' => $subscribe->id,
                'will_expire' => now()->addDays($plan->duration),
            ]);

            session()->forget('gateway_id');
            session()->forget('plan');

            DB::commit();
            return redirect(route('order.status', ['status' => 'success']))->with('message', __('New subscription order successfully.'));

        } catch (\Exception $e) {
            DB::rollback();
            return redirect(route('order.status', ['status' => 'failed']))->with('message', __('Something went wrong!'));
        }
    }

    public function sslCommerzFailed()
    {
        return redirect(route('order.status', ['status' => 'failed']))->with('error', __('Transaction failed, Please try again.'));
    }

    public function orderStatus()
    {
        return request('status');
    }
}
