<?php

namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Business\V1\General\TenantResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResourceGeneral extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'court-id'),
            'tenant_id' => EasyHashAction::encode($this->tenant_id, 'tenant-id'),
            'tenant' => new TenantResourceGeneral($this->whenLoaded('tenant')),
            'name' => $this->name,
            'number' => $this->number,
            'court_type_id' => EasyHashAction::encode($this->court_type_id, 'court-type-id'),
            'court_type' => new CourtTypeResourceGeneral($this->whenLoaded('courtType')),
            'images' => CourtImageResourceGeneral::collection($this->whenLoaded('images')),
            'primary_image' => new CourtImageResourceGeneral($this->whenLoaded('primaryImage')),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

