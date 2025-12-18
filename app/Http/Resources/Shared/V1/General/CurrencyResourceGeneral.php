<?php

namespace App\Http\Resources\Shared\V1\General;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResourceGeneral extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'symbol' => $this->symbol,
            'decimal_separator' => $this->decimal_separator,
        ];
    }
}
