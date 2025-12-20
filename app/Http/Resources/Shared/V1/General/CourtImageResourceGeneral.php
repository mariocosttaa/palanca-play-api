<?php
namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourtImageResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => EasyHashAction::encode($this->id, 'court-image-id'),
            'url'        => url($this->path),
            'is_primary' => $this->is_primary,
        ];
    }
}