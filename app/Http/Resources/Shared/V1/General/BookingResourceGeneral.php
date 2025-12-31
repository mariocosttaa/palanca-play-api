<?php

namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use App\Http\Resources\Business\V1\General\TenantResourceGeneral;
use App\Http\Resources\Business\V1\General\UserResourceGeneral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResourceGeneral extends JsonResource
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

        $startUser = $timezoneService->toUserTime($startUtc);
        $endUser = $timezoneService->toUserTime($endUtc);

        $startUserCarbon = \Carbon\Carbon::parse($startUser);
        $endUserCarbon = \Carbon\Carbon::parse($endUser);

        return [
            'id' => EasyHashAction::encode($this->id, 'booking-id'),
            'tenant_id' => EasyHashAction::encode($this->tenant_id, 'tenant-id'),
            'tenant' => new TenantResourceGeneral($this->whenLoaded('tenant')),
            'court_id' => EasyHashAction::encode($this->court_id, 'court-id'),
            'court' => new CourtResourceGeneral($this->whenLoaded('court')),
            'user_id' => EasyHashAction::encode($this->user_id, 'user-id'),
            'user' => new UserResourceGeneral($this->whenLoaded('user')),
            'start_date' => $startUserCarbon->format('Y-m-d'),
            'end_date' => $endUserCarbon->format('Y-m-d'),
            'start_time' => $startUserCarbon->format('H:i'),
            'end_time' => $endUserCarbon->format('H:i'),
            'price' => $this->price,
            'price_fmt' => $this->price_fmt,
            'is_pending' => $this->is_pending,
            'is_cancelled' => $this->is_cancelled,
            'is_paid' => $this->is_paid,
            'created_at' => $timezoneService->toUserTime($this->created_at),
        ];
    }
}

