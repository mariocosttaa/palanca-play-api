<?php

namespace App\Http\Resources\General;

use App\Actions\General\EasyHashAction;
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
            'images' => $this->when($this->relationLoaded('images'), function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => $image->path,
                        'alt' => $image->alt,
                        'is_primary' => $image->is_primary,
                    ];
                });
            }),
            'primary_image' => $this->when($this->relationLoaded('primaryImage'), function () {
                return $this->primaryImage ? [
                    'id' => $this->primaryImage->id,
                    'path' => $this->primaryImage->path,
                    'alt' => $this->primaryImage->alt,
                ] : null;
            }),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

