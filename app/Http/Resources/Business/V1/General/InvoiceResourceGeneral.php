<?php

namespace App\Http\Resources\Business\V1\General;

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
            'period' => $this->period,
            'date_start' => app(\App\Services\TimezoneService::class)->toUserTime($this->date_start),
            'date_end' => app(\App\Services\TimezoneService::class)->toUserTime($this->date_end),
            'price' => $this->price,
            'price_formatted' => $this->price_formatted,
            'currency' => $this->tenant->currency,
            'max_courts' => $this->max_courts,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_at' => app(\App\Services\TimezoneService::class)->toUserTime($this->created_at),
        ];
    }
}
