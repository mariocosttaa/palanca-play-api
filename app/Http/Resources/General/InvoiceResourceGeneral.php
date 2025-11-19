<?php

namespace App\Http\Resources\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'invoice-id'),
            'tenant_id' => EasyHashAction::encode($this->tenant_id, 'tenant-id'),
            'tenant' => new TenantResourceGeneral($this->whenLoaded('tenant')),
            'subscription_plan_id' => $this->subscription_plan_id
                ? EasyHashAction::encode($this->subscription_plan_id, 'subscription-plan-id')
                : null,
            'subscription_plan' => new SubscriptionPlanResourceGeneral($this->whenLoaded('subscriptionPlan')),
            'period' => $this->period,
            'date_start' => $this->date_start?->toISOString(),
            'date_end' => $this->date_end?->toISOString(),
            'price' => $this->price,
            'price_fmt' => $this->price_fmt,
            'is_extra_court' => $this->is_extra_court,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

