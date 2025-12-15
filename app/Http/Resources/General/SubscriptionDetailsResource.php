<?php

namespace App\Http\Resources\General;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'max_courts' => $this->resource['max_courts'],
            'current_courts' => $this->resource['current_courts'],
            'date_end' => $this->resource['date_end'] ? $this->resource['date_end']->toISOString() : null,
            'days_remaining' => $this->resource['days_remaining'],
            'invoice' => $this->resource['invoice'] ? new InvoiceResourceGeneral($this->resource['invoice']) : null,
        ];
    }
}
