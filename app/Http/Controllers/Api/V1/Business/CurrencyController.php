<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\CurrencyResourceGeneral;
use App\Models\Manager\CurrencyModel;

/**
 * @tags [API-BUSINESS] Currencies
 */
class CurrencyController extends Controller
{
    /**
     * Get a list of all currencies
     * 
     * @return \Illuminate\Http\Resources\Json\ResourceCollection<int, CurrencyResourceGeneral>
     * @response 200 \Illuminate\Http\Resources\Json\ResourceCollection<int, CurrencyResourceGeneral>
     * @response 500 {"message": "Server error"}
     */
    public function index()
    {
        try {
            $currencies = CurrencyModel::all();
            return CurrencyResourceGeneral::collection($currencies);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar moedas', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar moedas'], 500);
        }
    }
}
