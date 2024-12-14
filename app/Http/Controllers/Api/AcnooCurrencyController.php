<?php

namespace App\Http\Controllers\Api;

use App\Models\Currency;
use App\Http\Controllers\Controller;

class AcnooCurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::orderBy('is_default', 'desc')->orderBy('status', 'desc')->get();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $currencies
        ]);
    }
}
