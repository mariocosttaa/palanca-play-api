<?php

namespace App\Http\Resources\Mobile\V1\Specific;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hashid,
            'subject' => $this->subject,
            'message' => $this->message,
            'read' => !is_null($this->read_at),
            'read_at' => $this->read_at ? app(\App\Services\TimezoneService::class)->toUserTime($this->read_at) : null,
            'created_at' => app(\App\Services\TimezoneService::class)->toUserTime($this->created_at),
        ];
    }
}
