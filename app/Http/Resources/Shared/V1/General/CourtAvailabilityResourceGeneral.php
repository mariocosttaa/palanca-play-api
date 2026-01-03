<?php

namespace App\Http\Resources\Shared\V1\General;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $court_id
 * @property int|null $court_type_id
 * @property string|null $day_of_week_recurring
 * @property \Illuminate\Support\Carbon|null $specific_date
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_available
 */
class CourtAvailabilityResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'court-availability-id'),
            'tenant_id' => EasyHashAction::encode($this->tenant_id, 'tenant-id'),
            'court_id' => $this->court_id ? EasyHashAction::encode($this->court_id, 'court-id') : null,
            'court_type_id' => $this->court_type_id ? EasyHashAction::encode($this->court_type_id, 'court-type-id') : null,
            'day_of_week_recurring' => $this->day_of_week_recurring,
            'specific_date' => $this->specific_date?->format('Y-m-d'),
            'start_time' => app(\App\Services\TimezoneService::class)->toUserTime($this->start_time) ? \Carbon\Carbon::parse(app(\App\Services\TimezoneService::class)->toUserTime($this->start_time))->format('H:i') : null,
            'end_time' => app(\App\Services\TimezoneService::class)->toUserTime($this->end_time) ? \Carbon\Carbon::parse(app(\App\Services\TimezoneService::class)->toUserTime($this->end_time))->format('H:i') : null,
            'is_available' => (bool) $this->is_available,
        ];
    }
}
