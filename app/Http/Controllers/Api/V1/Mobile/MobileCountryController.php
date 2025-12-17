<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\General\CountryResourceGeneral;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags [API-MOBILE] Countries
 */
class MobileCountryController extends Controller
{
    /**
     * List all countries
     */
    public function index(): JsonResponse
    {
        try {
            $countries = Country::all();
            return $this->dataResponse(CountryResourceGeneral::collection($countries)->resolve());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch countries.', $e->getMessage(), 500);
        }
    }
}
