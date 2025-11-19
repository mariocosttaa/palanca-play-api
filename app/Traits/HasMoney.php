<?php

namespace App\Traits;

trait HasMoney
{
    /**
     * Format money stored in cents to currency string.
     * Assumes price is stored as integer (cents) or decimal.
     */
    public function getPriceFmtAttribute(): ?string
    {
        if (!isset($this->price) || $this->price === null) {
            return null;
        }

        // Handle both integer (cents) and decimal formats
        $amount = is_integer($this->price) ? $this->price / 100 : (float) $this->price;

        return '$' . number_format($amount, 2);
    }

    /**
     * Get price in dollars (float).
     */
    public function getPriceInDollarsAttribute(): ?float
    {
        if (!isset($this->price) || $this->price === null) {
            return null;
        }

        // Handle both integer (cents) and decimal formats
        return is_integer($this->price) ? $this->price / 100 : (float) $this->price;
    }
}

