<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\General\CurrencyResourceGeneral;
use App\Models\Manager\CurrencyModel;
use Illuminate\Http\JsonResponse;

/**
 * @tags [API-MOBILE] Currencies
 */
class MobileCurrencyController extends Controller
{
    /**
     * List all currencies
     */
    public function index(): JsonResponse
    {
        try {
            $currencies = CurrencyModel::all();
            return $this->dataResponse(CurrencyResourceGeneral::collection($currencies)->resolve());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch currencies.', $e->getMessage(), 500);
        }
    }
}
