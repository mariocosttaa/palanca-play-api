<?php

namespace App\Http\Resources\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtTypeResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'court-type-id'),
            'tenant_id' => EasyHashAction::encode($this->tenant_id, 'tenant-id'),
            'tenant' => new TenantResourceGeneral($this->whenLoaded('tenant')),
            'courts' => CourtResourceGeneral::collection($this->whenLoaded('courts')),
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'interval_time_minutes' => $this->interval_time_minutes,
            'buffer_time_minutes' => $this->buffer_time_minutes,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

