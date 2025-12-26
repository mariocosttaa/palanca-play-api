<?php

namespace App\Http\Resources\Business\V1\Specific;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\Business\V1\Specific\UserResourceSpecific;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;

class FinancialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->hashid,
            'date' => $this->start_date->format('Y-m-d'),
            'date_formatted' => $this->start_date->translatedFormat('d M Y'),
            'time' => $this->start_time->format('H:i'),
            'user' => new UserResourceSpecific($this->whenLoaded('user')),
            'amount' => $this->price, // in cents
            'amount_formatted' => $this->formatMoney(),
            'status' => $this->getStatus(),
            'status_label' => $this->getStatusLabel(),
            'present' => $this->present,
        ];
    }

    /**
     * Get booking status
     */
    private function getStatus(): string
    {
        if ($this->status === BookingStatusEnum::CANCELLED) {
            return 'cancelled';
        }
        
        if ($this->payment_status === PaymentStatusEnum::PAID) {
            return 'paid';
        }
        
        if ($this->status === BookingStatusEnum::PENDING) {
            return 'pending';
        }
        
        if ($this->present === false) {
            return 'not_present';
        }
        
        return 'unpaid';
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(): string
    {
        return match($this->getStatus()) {
            'paid' => 'Pago',
            'pending' => 'Pendente',
            'cancelled' => 'Cancelado',
            'not_present' => 'Não Compareceu',
            'unpaid' => 'Não Pago',
            default => 'Desconhecido',
        };
    }

    /**
     * Format money with currency symbol
     */
    private function formatMoney(): string
    {
        $currencyMap = [
            'usd' => '$',
            'eur' => '€',
            'gbp' => '£',
            'brl' => 'R$',
            'jpy' => '¥',
        ];

        $currencyCode = $this->currency?->code ?? 'eur';
        $symbol = $currencyMap[strtolower($currencyCode)] ?? $currencyCode;
        
        $amount = $this->price / 100;
        return $symbol . ' ' . number_format($amount, 2, '.', ',');
    }
}
