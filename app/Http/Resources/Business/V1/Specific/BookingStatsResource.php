<?php

namespace App\Http\Resources\Business\V1\Specific;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_bookings' => $this->resource['total_bookings'],
            'bookings_this_month' => $this->resource['bookings_this_month'],
            'bookings_today' => $this->resource['bookings_today'],
            'bookings_tomorrow' => $this->resource['bookings_tomorrow'],
            'status_breakdown_this_month' => [
                'confirmed' => $this->resource['status_breakdown_this_month']['confirmed'],
                'pending' => $this->resource['status_breakdown_this_month']['pending'],
                'canceled' => $this->resource['status_breakdown_this_month']['canceled'],
            ],
        ];
    }
}
