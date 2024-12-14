<?php

namespace App\Library;

use Stripe\Stripe;
use App\Models\Gateway;
use Illuminate\Http\Request;

class StripeGateway
{
    public static function make_payment($array)
    {
        $gateway = Gateway::findOrFail($array['gateway_id']);
        Stripe::setApiKey($gateway->data['stripe_secret']);
        $amount = payable($array['pay_amount'], $gateway) * 100;

        $session = \Stripe\Checkout\Session::create([
            'line_items'  => [
                [
                    'price_data' => [
                        'currency'     => 'USD',
                        'product_data' => [
                            "name" => $array['billName'],
                        ],
                        'unit_amount'  => $amount,
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $array['payment_type'] == 'plan_payment' ? route('payment.success') : route('recharge.success'),
            'cancel_url'  => $array['payment_type'] == 'plan_payment' ? route('payment.failed') : route('recharge.failed'),
        ]);

        return redirect()->away($session->url);
    }
}
