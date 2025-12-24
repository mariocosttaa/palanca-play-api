<?php

namespace App\Http\Resources\Business\V1\Specific;

use Illuminate\Http\Resources\Json\JsonResource;

class CourtSlotsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Return array of slots - Laravel will wrap in 'data' automatically
        return collect($this->resource)->map(function ($slot) {
            return [
                'start' => $slot['start'],
                'end' => $slot['end'],
            ];
        })->values()->all();
    }
}
