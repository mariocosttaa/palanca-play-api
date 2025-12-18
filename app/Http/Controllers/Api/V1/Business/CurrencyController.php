<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\General\CurrencyResourceGeneral;
use App\Models\Manager\CurrencyModel;

/**
 * @tags [API-BUSINESS] Currencies
 */
class CurrencyController extends Controller
{
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
