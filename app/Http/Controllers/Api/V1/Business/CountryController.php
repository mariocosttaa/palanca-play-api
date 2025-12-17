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
            return $this->dataResponse(CountryResourceGeneral::collection($countries)->resolve());
        } catch (\Exception $e) {
            return $this->errorResponse('Erro ao listar países', $e->getMessage());
        }
    }
}
