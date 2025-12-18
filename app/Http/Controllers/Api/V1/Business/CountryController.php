<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\General\CountryResourceGeneral;
use App\Models\Country;

/**
 * @tags [API-BUSINESS] Countries
 */
class CountryController extends Controller
{
    public function index()
    {
        try {
            $countries = Country::all();
            return CountryResourceGeneral::collection($countries);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar países', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erro ao listar países'], 500);
        }
    }
}
