<?php

namespace App\Http\Resources\Api\V1\Business;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'booking-id'),
            'court_id' => EasyHashAction::encode($this->court_id, 'court-id'),
            'user_id' => EasyHashAction::encode($this->user_id, 'user-id'),
            'user' => [
                'id' => EasyHashAction::encode($this->user->id, 'user-id'),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'price' => $this->price,
            'currency' => $this->currency ? $this->currency->code : null,
            'is_pending' => $this->is_pending,
            'is_cancelled' => $this->is_cancelled,
            'is_paid' => $this->is_paid,
            'paid_at_venue' => $this->paid_at_venue,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
