<?php
namespace App\Http\Resources\Api\V1\Mobile;

use App\Http\Resources\Business\V1\Specific\BookingResource;
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

        $data['qr_code'] = $this->qr_code ? config('app.url') . '/' . $this->qr_code : null;

        return $data;
    }
}
