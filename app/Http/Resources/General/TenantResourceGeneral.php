<?php

namespace App\Http\Resources\General;

use App\Actions\General\EasyHashAction;
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
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'subscription_plan' => new SubscriptionPlanResourceGeneral($this->whenLoaded('subscriptionPlan')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

