<?php

namespace App\Http\Resources\Business\V1\Specific;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Business\V1\Specific\UserResourceSpecific;
use App\Http\Resources\Shared\V1\General\CourtResourceGeneral;
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
        $timezoneService = app(\App\Services\TimezoneService::class);

        // Convert stored UTC dates to User TZ
        $startUtc = \Carbon\Carbon::parse($this->start_date->format('Y-m-d') . ' ' . $this->start_time->format('H:i:s'), 'UTC');
        $endUtc = \Carbon\Carbon::parse($this->end_date->format('Y-m-d') . ' ' . $this->end_time->format('H:i:s'), 'UTC');

        $startParts = $timezoneService->getUserTimeParts($startUtc);
        $endParts = $timezoneService->getUserTimeParts($endUtc);

        return [
            'id' => EasyHashAction::encode($this->id, 'booking-id'),
            'court_id' => EasyHashAction::encode($this->court_id, 'court-id'),
            'court' => new CourtResourceGeneral($this->whenLoaded('court')),
            'user_id' => EasyHashAction::encode($this->user_id, 'user-id'),
            'user' => new UserResourceSpecific($this->whenLoaded('user')),
            'start_date' => $startParts['date'],
            'end_date' => $endParts['date'],
            'start_time' => $startParts['time'],
            'end_time' => $endParts['time'],
            'price' => $this->price,
            'price_formatted' => $this->price_formatted,
            'currency' => $this->currency ? $this->currency->code : null,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'present' => $this->present,
            'created_at' => $timezoneService->toUserTime($this->created_at),
        ];
    }
}
