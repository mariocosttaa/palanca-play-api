<?php

namespace App\Http\Resources\Specific;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\General\CountryResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResourceSpecific extends JsonResource
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
            'is_app_user' => (bool) $this->is_app_user,
            'google_login' => $this->google_login,
            'country_id' => $this->country_id
                ? EasyHashAction::encode($this->country_id, 'country-id')
                : null,
            'country' => new CountryResourceGeneral($this->whenLoaded('country')),
            'calling_code' => $this->calling_code,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

