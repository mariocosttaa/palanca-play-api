<?php

namespace App\Http\Resources\Api\V1\Mobile;

use App\Http\Resources\Shared\V1\General\CourtTypeResourceGeneral;
use Illuminate\Http\Request;

/**
 * @property bool $is_liked
 */
class MobileCourtTypeResource extends CourtTypeResourceGeneral
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['is_liked'] = (bool) ($this->is_liked ?? false);
        
        $data['qr_code'] = $this->whenLoaded('nextBooking', function() {
            return $this->nextBooking->qr_code ? config('app.url') . '/' . $this->nextBooking->qr_code : null;
        });

        return $data;
    }
}
