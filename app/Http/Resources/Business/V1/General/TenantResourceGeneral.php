<?php

namespace App\Http\Resources\Business\V1\General;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Shared\V1\General\CountryResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'tenant-id'),
            'country_id' => $this->country_id 
                ? EasyHashAction::encode($this->country_id, 'country-id') 
                : null,
            'name' => $this->name,
            'logo' => $this->logo ? config('app.url') . '/' . $this->logo : null,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'timezone_id' => $this->timezone_id 
                ? EasyHashAction::encode($this->timezone_id, 'timezone-id') 
                : null,
            'country' => new CountryResourceGeneral($this->whenLoaded('country')),
            'subscription_plan' => new SubscriptionPlanResourceGeneral($this->whenLoaded('subscriptionPlan')),
            'created_at' => app(\App\Services\TimezoneService::class)->toUserTime($this->created_at),
        ];
    }
}

