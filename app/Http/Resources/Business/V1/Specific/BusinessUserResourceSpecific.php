<?php

namespace App\Http\Resources\Business\V1\Specific;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Shared\V1\General\CountryResourceGeneral;
use App\Http\Resources\Shared\V1\General\TimezoneResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessUserResourceSpecific extends JsonResource
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
            'email_verified' => $this->hasVerifiedEmail(),
            'google_login' => $this->google_login,
            'country_id' => $this->country_id
                ? EasyHashAction::encode($this->country_id, 'country-id')
                : null,
            'country' => $this->country ? new CountryResourceGeneral($this->country) : null,
            'calling_code' => $this->calling_code,
            'phone' => $this->phone,
            'timezone_id' => $this->timezone_id
                ? EasyHashAction::encode($this->timezone_id, 'timezone-id')
                : null,
            'timezone' => new TimezoneResourceGeneral($this->whenLoaded('timezone')),
            'created_at' => app(\App\Services\TimezoneService::class)->toUserTime($this->created_at),
            'updated_at' => $this->updated_at ? app(\App\Services\TimezoneService::class)->toUserTime($this->updated_at) : null,
        ];
    }
}

