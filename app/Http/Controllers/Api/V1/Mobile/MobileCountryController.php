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
    public function index()
    {
        try {
            $countries = Country::all();
            return CountryResourceGeneral::collection($countries);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch countries.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch countries.'], 500);
        }
    }
}
