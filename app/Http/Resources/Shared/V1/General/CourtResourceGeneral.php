<?php
namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtResourceGeneral extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => EasyHashAction::encode($this->id, 'court-id'),
            'name'       => $this->name,
            'number'     => $this->number,
            'images'     => CourtImageResourceGeneral::collection($this->whenLoaded('images')),
            'status'     => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
