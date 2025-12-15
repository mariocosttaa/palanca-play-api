<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;

class CourtAvailabilityController extends Controller
{
    public function getDates(Request $request)
    {
        $request->validate([
            'court_id' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $courtId = EasyHashAction::decode($request->court_id, 'court-id');
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
        }

        $dates = $court->getAvailableDates($request->start_date, $request->end_date);

        return $this->dataResponse($dates);
    }

    public function getSlots(Request $request)
    {
        $request->validate([
            'court_id' => 'required',
            'date' => 'required|date',
        ]);

        $courtId = EasyHashAction::decode($request->court_id, 'court-id');
        $court = Court::forTenant($request->tenant->id)->find($courtId);

        if (!$court) {
            return $this->errorResponse('Quadra não encontrada', status: 404);
        }

        $slots = $court->getAvailableSlots($request->date);

        return $this->dataResponse($slots);
    }
}
