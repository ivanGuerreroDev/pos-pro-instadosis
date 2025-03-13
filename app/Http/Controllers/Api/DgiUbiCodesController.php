<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DgiUbiCodes;
use Illuminate\Http\JsonResponse;

class DgiUbiCodesController extends Controller
{
    /**
     * Get provinces list
     *
     * @return JsonResponse
     */
    public function getProvinces(): JsonResponse
    {
        $provinces = DgiUbiCodes::select('codigo', 'nombre')
            ->where('tipo', 'provincia')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($provinces);
    }

    /**
     * Get districts by province
     *
     * @param string $province Province code
     * @return JsonResponse
     */
    public function getDistricts($province): JsonResponse
    {
        $districts = DgiUbiCodes::select('codigo', 'nombre')
            ->where('tipo', 'distrito')
            ->where('codigo', 'like', $province . '-%')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($districts);
    }

    /**
     * Get townships by district
     *
     * @param string $district District code
     * @return JsonResponse
     */
    public function getTownships($district): JsonResponse
    {
        $townships = DgiUbiCodes::select('codigo', 'nombre')
            ->where('tipo', 'corregimiento')
            ->where('codigo', 'like', $district . '-%')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($townships);
    }

    /**
     * Get all location codes by type
     *
     * @param string $type Location type (provincia, distrito, corregimiento)
     * @return JsonResponse
     */
    public function getByType($type): JsonResponse
    {
        $locations = DgiUbiCodes::select('codigo', 'nombre')
            ->where('tipo', $type)
            ->orderBy('nombre')
            ->get();
            
        return response()->json($locations);
    }
}