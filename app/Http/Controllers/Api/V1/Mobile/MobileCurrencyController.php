<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\CurrencyResourceGeneral;
use App\Models\Manager\CurrencyModel;
use Illuminate\Http\JsonResponse;

/**
 * @tags [API-MOBILE] Currencies
 */
class MobileCurrencyController extends Controller
{
    /**
     * List currencies
     * 
     * @unauthenticated
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Shared\V1\General\CurrencyResourceGeneral>
     */
    public function index()
    {
        try {
            $currencies = CurrencyModel::all();
            return CurrencyResourceGeneral::collection($currencies);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch currencies.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch currencies.'], 500);
        }
    }
}
