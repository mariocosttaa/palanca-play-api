<?php
namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property int $number
 * @property bool $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CourtImage[] $images
 */
class CourtResourceGeneral extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => EasyHashAction::encode($this->id, 'court-id'),
            'name'       => $this->name,
            'number'     => (int) $this->number,
            'images'     => CourtImageResourceGeneral::collection($this->whenLoaded('images')),
            'status'     => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
