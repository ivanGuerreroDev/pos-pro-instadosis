<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DgiUbiCodes;
use Illuminate\Http\JsonResponse;

class DgiUbiCodesController extends Controller
{
    public function getDistricts($province): JsonResponse
    {
        $districts = DgiUbiCodes::select('codigo', 'nombre')
            ->where('tipo', 'distrito')
            ->where('codigo', 'like', $province . '-%')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($districts);
    }

    public function getTownships($district): JsonResponse
    {
        $townships = DgiUbiCodes::select('codigo', 'nombre')
            ->where('tipo', 'corregimiento')
            ->where('codigo', 'like', $district . '-%')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($townships);
    }
}
