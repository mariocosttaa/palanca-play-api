<?php

namespace App\Http\Resources\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'subscription-plan-id'),
            'name' => $this->name,
            'slug' => $this->slug,
            'max_courts' => $this->max_courts,
            'price' => $this->price,
            'price_fmt' => '$' . number_format($this->price, 2),
        ];
    }
}

