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
            return $this->dataResponse(CurrencyResourceGeneral::collection($currencies)->resolve());
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao listar moedas', $e->getMessage());
        }
    }
}
