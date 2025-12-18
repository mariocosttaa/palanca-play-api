<?php

namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtAvailabilityResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'court-availability-id'),
            'tenant_id' => EasyHashAction::encode($this->tenant_id, 'tenant-id'),
            'court_id' => $this->court_id ? EasyHashAction::encode($this->court_id, 'court-id') : null,
            'court_type_id' => $this->court_type_id ? EasyHashAction::encode($this->court_type_id, 'court-type-id') : null,
            'day_of_week_recurring' => $this->day_of_week_recurring,
            'specific_date' => $this->specific_date?->format('Y-m-d'),
            'start_time' => \Carbon\Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => \Carbon\Carbon::parse($this->end_time)->format('H:i'),
            'is_available' => $this->is_available,
        ];
    }
}
