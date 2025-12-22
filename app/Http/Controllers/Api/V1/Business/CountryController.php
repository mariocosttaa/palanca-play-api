<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\CountryResourceGeneral;
use App\Models\Country;
use Illuminate\Support\Facades\Log;

/**
 * @tags [API-BUSINESS] Countries
 */
class CountryController extends Controller
{
    /**
     * Get a list of all countries
     */
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $countries = Country::all();
            return CountryResourceGeneral::collection($countries);
        } catch (\Exception $e) {
            Log::error('Erro ao listar países', ['error' => $e->getMessage()]);
            abort(500, 'Erro ao listar países');
        }
    }
}
