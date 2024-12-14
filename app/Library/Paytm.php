<?php
namespace App\Library;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Anand\LaravelPaytmWallet\Facades\PaytmWallet;

class Paytm
{
    public static function redirect_if_payment_success()
    {
        if (Session::has('fund_callback')) {
            return url(Session::get('fund_callback')['success_url']);
        } else {
            return url('users/payment/success');
        }
    }

    public static function redirect_if_payment_faild()
    {
        if (Session::has('fund_callback')) {
            return url(Session::get('fund_callback')['cancel_url']);
        } else {
            return url('users/payment/failed');
        }
    }

    public static function make_payment($array)
    {
        //Checking Minimum/Maximum amount
        // $gateway = Gateway::findOrFail($array['gateway_id']);
        $amount = $array['pay_amount'];

        $currency = $array['currency'];
        $email = $array['email'];
        $amount = round($array['pay_amount']);
        $name = $array['name'];
        $test_mode = $array['test_mode'];
        $billName = $array['billName'];
        $data['payment_mode'] = 'paytm';

        $data['amount'] = $amount;
        $data['test_mode'] = $test_mode;
        $data['charge'] = $array['charge'];
        $data['main_amount'] = $array['amount'];
        $data['gateway_id'] = $array['gateway_id'];

        $data['merchant_id'] = $array['merchant_id'];
        $data['merchant_key'] = $array['merchant_key'];
        $data['merchant_website'] = $array['merchant_website'];
        $data['channel'] = $array['channel'];
        $data['industry_type'] = $array['industry_type'];
        $data['environment'] = $array['environment'];

        Session::put('paytm_credentials', $data);

        $payment = PaytmWallet::with('receive');

        $payment->prepare([
            'order' => uniqid(),
            'user' => $billName,
            'mobile_number' => auth()->user()->phone ?? '54635',
            'email' => $email,
            'amount' => $amount,
            'callback_url' => url('users/paytm/status')
        ]);
        return $payment->receive();
    }

    public function status(Request $request)
    {
        $order_info = Session::get('paytm_credentials');

        $transaction = PaytmWallet::with('receive');

        $transaction->getResponseMessage(); //Get Response Message If Available
        //get important parameters via public methods
        $transaction->getOrderId(); // Get order id
        $transaction->getTransactionId(); // Get transaction id

        if($transaction->isSuccessful()) {
            $data['payment_method'] = "Paytm";
            $data['gateway_id'] = $order_info['gateway_id'];
            $data['amount'] = $order_info['amount'];
            $data['billName'] = $order_info['billName'];
            $data['charge'] = $order_info['charge'];
            $data['status'] = 'completed';
            $data['payment_status'] = 'completed';
            $data['is_fallback'] = $order_info['is_fallback'];

            Session::put('payment_info', $data);
            Session::forget('paytm_credentials');
            return request()->expectsJson() ?
            Paytm::redirect_if_payment_success() :
            redirect(Paytm::redirect_if_payment_success());
        } else {
            $data['payment_status'] = 'pending';
            Session::put('payment_info', $data);
            Session::forget('paytm_credentials');

            return request()->expectsJson() ?
            Paytm::redirect_if_payment_faild() :
            redirect(Paytm::redirect_if_payment_faild());
        }
    }
}
