<?php

namespace App\Http\Resources\Business\V1\General;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Shared\V1\General\CountryResourceGeneral;
use App\Http\Resources\Shared\V1\General\TimezoneResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessUserResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'business-user-id'),
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'country_id' => $this->country_id
                ? EasyHashAction::encode($this->country_id, 'country-id')
                : null,
            'country' => new CountryResourceGeneral($this->whenLoaded('country')),
            'timezone_id' => $this->timezone_id
                ? EasyHashAction::encode($this->timezone_id, 'timezone-id')
                : null,
            'timezone' => new TimezoneResourceGeneral($this->whenLoaded('timezone')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

