<?php

namespace App\Http\Resources\Business\V1\General;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Shared\V1\General\CountryResourceGeneral;
use App\Http\Resources\Shared\V1\General\TimezoneResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'user-id'),
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'country_id' => $this->country_id
                ? EasyHashAction::encode($this->country_id, 'country-id')
                : null,
            'country' => $this->country ? new CountryResourceGeneral($this->country) : null,
            'timezone_id' => $this->timezone_id
                ? EasyHashAction::encode($this->timezone_id, 'timezone-id')
                : null,
            'timezone' => $this->timezone ? new TimezoneResourceGeneral($this->timezone) : null,
            'is_app_user' => $this->is_app_user,
        ];
    }
}

