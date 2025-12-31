<?php

namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimezoneResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'timezone-id'),
            'name' => $this->name,
            'label' => $this->label,
            'offset' => $this->offset,
        ];
    }
}
