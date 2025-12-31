<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shared\V1\General\TimezoneResourceGeneral;
use App\Models\Timezone;
use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimezoneController extends Controller
{
    /**
     * List all timezones
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
