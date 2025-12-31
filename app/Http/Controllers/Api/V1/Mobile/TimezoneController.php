<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\TimezoneResourceGeneral;
use App\Models\Timezone;
use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags [API-MOBILE] Timezones
 */
class TimezoneController extends Controller
{
    /**
     * List timezones
     * 
     * @unauthenticated
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection<\App\Http\Resources\Shared\V1\General\TimezoneResourceGeneral>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $timezones = Timezone::all();

            return TimezoneResourceGeneral::collection($timezones);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve timezones.', ['error' => $e->getMessage()]);
            abort(500, 'Failed to retrieve timezones.');
        }
    }
}
