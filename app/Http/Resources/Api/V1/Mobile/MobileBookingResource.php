<?php
namespace App\Http\Resources\Api\V1\Mobile;

use App\Http\Resources\Business\V1\Specific\BookingResource;
use App\Http\Resources\Api\V1\Mobile\MobileCourtResource;
use Illuminate\Http\Request;

class MobileBookingResource extends BookingResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['court'] = new MobileCourtResource($this->whenLoaded('court'));

        $data['qr_code'] = $this->qr_code ? config('app.url') . '/' . $this->qr_code : null;

        $data['court_image'] = $this->court && $this->court->primaryImage 
            ? url($this->court->primaryImage->path) 
            : ($this->court && $this->court->relationLoaded('images') && $this->court->images->isNotEmpty() 
                ? url($this->court->images->first()->path) 
                : null);

        return $data;
    }
}
