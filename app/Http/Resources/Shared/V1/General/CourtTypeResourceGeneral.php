<?php

namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Shared\V1\General\CourtAvailabilityResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtTypeResourceGeneral extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'court-type-id'),
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'description' => $this->description,
            'interval_time_minutes' => $this->interval_time_minutes,
            'buffer_time_minutes' => $this->buffer_time_minutes,
            'price_per_interval' => $this->price_per_interval,
            'price_formatted' => $this->price_formatted,
            'status' => $this->status,
            'availabilities' => CourtAvailabilityResourceGeneral::collection($this->whenLoaded('availabilities')),
            'courts' => CourtResourceGeneral::collection($this->whenLoaded('courts')),
            'courts_count' => $this->when($this->relationLoaded('courts'), $this->courts->count()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

