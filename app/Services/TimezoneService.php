<?php

namespace App\Services;

use Carbon\Carbon;

class TimezoneService
{
    protected ?string $userTimezone = null;

    /**
     * Set the current context timezone (e.g., from the authenticated user).
     */
    public function setContextTimezone(string $timezone): void
    {
        $this->userTimezone = $timezone;
    }

    /**
     * Get the current context timezone.
     */
    public function getContextTimezone(): string
    {
        return $this->userTimezone ?? 'UTC';
    }

    /**
     * Convert a frontend datetime string (in User TZ) to a Carbon instance in UTC.
     *
     * @param string|null $datetime
     * @return Carbon|null
     */
    public function toUTC(?string $datetime): ?Carbon
    {
        if (! $datetime) {
            return null;
        }

        // Parse the date assuming it is in the user's timezone
        // Then convert to UTC
        return Carbon::parse($datetime, $this->getContextTimezone())->setTimezone('UTC');
    }

    /**
     * Convert a UTC Carbon instance (or string) to the User's Timezone formatted string.
     *
     * @param Carbon|string|null $datetime
     * @return string|null
     */
    public function toUserTime($datetime): ?string
    {
        if (! $datetime) {
            return null;
        }

        if (is_string($datetime)) {
            $datetime = Carbon::parse($datetime);
        }

        return $datetime->setTimezone($this->getContextTimezone())->toIso8601String();
    }
}
