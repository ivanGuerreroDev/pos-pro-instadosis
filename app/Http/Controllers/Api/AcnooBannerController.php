<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;

class AcnooBannerController extends Controller
{
    public function index()
    {
        $banners = Banner::latest()->get();

        return response()->json([
            'message' => __('Data fetched successfully.'),
            'data' => $banners,
        ]);
    }
}