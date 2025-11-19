<?php

namespace App\Traits;

trait HasHashid
{
    /**
     * Get the hashid for this model.
     * This is a placeholder - actual implementation will use EasyHashAction.
     */
    public function getHashidAttribute(): ?string
    {
        // Placeholder - will be implemented with EasyHashAction
        // For now, return null to avoid errors
        return null;
    }
}

