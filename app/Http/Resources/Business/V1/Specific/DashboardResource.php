<?php

namespace App\Http\Resources\Business\V1\Specific;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'cards' => [
                    'total_revenue' => $this['total_revenue'],
                    'total_revenue_formatted' => $this['total_revenue_formatted'],
                    'total_open_bookings' => $this['total_open_bookings'],
                    'total_clients' => $this['total_clients'],
                    'total_court_usage_hours' => $this['total_court_usage_hours'],
                ],
                'lists' => [
                    'recent_bookings' => BookingResource::collection($this['recent_bookings']),
                    'active_clients' => $this['active_clients'],
                    'popular_courts' => $this['popular_courts'],
                ],
                'charts' => [
                    'daily_revenue' => $this['daily_revenue'],
                ],
            ]
        ];
    }
}
