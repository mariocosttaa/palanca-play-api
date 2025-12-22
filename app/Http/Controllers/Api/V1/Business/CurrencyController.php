<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\CurrencyResourceGeneral;
use App\Models\Manager\CurrencyModel;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Currencies
 */
class CurrencyController extends Controller
{
    /**
     * Get a list of all currencies
     */
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $currencies = CurrencyModel::all();
            return CurrencyResourceGeneral::collection($currencies);
        } catch (\Exception $e) {
            Log::error('Erro ao listar moedas', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao listar moedas');
        }
    }
}
