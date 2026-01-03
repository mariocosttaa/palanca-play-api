<?php
namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $path
 * @property bool $is_primary
 */
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
            'is_primary' => (bool) $this->is_primary,
        ];
    }
}