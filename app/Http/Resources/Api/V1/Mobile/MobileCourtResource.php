<?php

namespace App\Http\Resources\Api\V1\Mobile;

use App\Http\Resources\Shared\V1\General\CourtResourceGeneral;
use Illuminate\Http\Request;

class MobileCourtResource extends CourtResourceGeneral
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Include tenant location data if tenant is loaded
        $data['address'] = $this->whenLoaded('tenant', fn() => $this->tenant->address);
        $data['latitude'] = $this->whenLoaded('tenant', fn() => $this->tenant->latitude);
        $data['longitude'] = $this->whenLoaded('tenant', fn() => $this->tenant->longitude);

        return $data;
    }
}
