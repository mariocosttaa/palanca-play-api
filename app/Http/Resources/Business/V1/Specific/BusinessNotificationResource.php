<?php

namespace App\Http\Resources\Business\V1\Specific;

use App\Actions\General\EasyHashAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => EasyHashAction::encode($this->id, 'notification-id'),
            'subject' => $this->subject,
            'message' => $this->message,
            'read' => !is_null($this->read_at),
            'read_at' => $this->read_at ? app(\App\Services\TimezoneService::class)->toUserTime($this->read_at) : null,
            'created_at' => app(\App\Services\TimezoneService::class)->toUserTime($this->created_at),
        ];
    }
}
