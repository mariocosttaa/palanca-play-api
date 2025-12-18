<?php

namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Business\V1\General\TenantResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtTypeResourceGeneral extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'court-type-id'),
            'tenant_id' => EasyHashAction::encode($this->tenant_id, 'tenant-id'),
            'tenant' => new TenantResourceGeneral($this->whenLoaded('tenant')),
            'courts' => CourtResourceGeneral::collection($this->whenLoaded('courts')),
            'courts_count' => $this->when($this->relationLoaded('courts'), $this->courts->count()),
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'interval_time_minutes' => $this->interval_time_minutes,
            'buffer_time_minutes' => $this->buffer_time_minutes,
            'price_per_interval' => $this->price_per_interval,
            'price_formatted' => $this->price_formatted,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

