<?php

namespace App\Http\Resources\Api\V1\Business;

use Illuminate\Http\Resources\Json\JsonResource;

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
            'user' => $this->user ? [
                'id' => $this->user->hashid,
                'name' => $this->user->name,
                'surname' => $this->user->surname,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null,
            'amount' => $this->price, // in cents
            'amount_formatted' => $this->formatMoney(),
            'status' => $this->getStatus(),
            'status_label' => $this->getStatusLabel(),
            'is_paid' => $this->is_paid,
            'is_pending' => $this->is_pending,
            'is_cancelled' => $this->is_cancelled,
            'present' => $this->present,
            'paid_at_venue' => $this->paid_at_venue,
        ];
    }

    /**
     * Get booking status
     */
    private function getStatus(): string
    {
        if ($this->is_cancelled) {
            return 'cancelled';
        }
        
        if ($this->is_paid) {
            return 'paid';
        }
        
        if ($this->is_pending) {
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
