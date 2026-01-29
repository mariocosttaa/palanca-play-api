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
    public function getContextTimezone(?string $fallback = null): string
    {
        if ($this->userTimezone) {
            return $this->userTimezone;
        }

        $user = auth()->user();
        if ($user && $user->timezone_string) {
            return $user->timezone_string;
        }

        return $fallback ?? 'UTC';
    }

    /**
     * Convert a frontend datetime string (in User TZ) to a Carbon instance in UTC.
     *
     * @param string|null $datetime
     * @param string|null $fallback Fallback timezone if user timezone is not set
     * @return Carbon|null
     */
    public function toUTC(?string $datetime, ?string $fallback = null): ?Carbon
    {
        if (! $datetime) {
            return null;
        }

        // Parse the date assuming it is in the user's timezone (or fallback)
        // Then convert to UTC
        return Carbon::parse($datetime, $this->getContextTimezone($fallback))->setTimezone('UTC');
    }

    /**
     * Convert a UTC Carbon instance (or string) to the User's Timezone formatted string.
     *
     * @param Carbon|string|null $datetime
     * @param string|null $fallback Fallback timezone if user timezone is not set
     * @return string|null
     */
    public function toUserTime($datetime, ?string $fallback = null): ?string
    {
        if (! $datetime) {
            return null;
        }

        if (is_string($datetime)) {
            $datetime = Carbon::parse($datetime);
        }

        return $datetime->setTimezone($this->getContextTimezone($fallback))->toIso8601String();
    }
    /**
     * Convert a date and time string from User TZ to UTC.
     *
     * @param string $date Y-m-d
     * @param string $time H:i
     * @param string|null $fallback Fallback timezone if user timezone is not set
     * @return Carbon|null
     */
    public function convertSlotToUtc(string $date, string $time, ?string $fallback = null): ?Carbon
    {
        return $this->toUTC($date . ' ' . $time, $fallback);
    }

    /**
     * Convert an array of slots (start/end times) from User TZ to UTC.
     * Returns the transformed slots and the UTC date of the first slot.
     *
     * @param string $date Y-m-d
     * @param array $slots Array of ['start' => 'H:i', 'end' => 'H:i']
     * @param string|null $fallback Fallback timezone if user timezone is not set
     * @return array{slots: array, start_date: string|null}
     */
    public function convertSlotsToUtc(string $date, array $slots, ?string $fallback = null): array
    {
        $newSlots = [];
        $firstSlotStartUtc = null;

        foreach ($slots as $index => $slot) {
            if (isset($slot['start']) && isset($slot['end'])) {
                $startUtc = $this->convertSlotToUtc($date, $slot['start'], $fallback);
                $endUtc = $this->convertSlotToUtc($date, $slot['end'], $fallback);

                if ($startUtc && $endUtc) {
                    $newSlots[$index] = [
                        'start' => $startUtc->format('H:i'),
                        'end' => $endUtc->format('H:i'),
                    ];

                    if ($index === 0) {
                        $firstSlotStartUtc = $startUtc;
                    }
                }
            }
        }

        return [
            'slots' => $newSlots,
            'start_date' => $firstSlotStartUtc ? $firstSlotStartUtc->format('Y-m-d') : null,
        ];
    }

    /**
     * Convert a UTC datetime to User TZ and return date and time parts.
     *
     * @param Carbon|string|null $utcDatetime
     * @param string|null $fallback Fallback timezone if user timezone is not set
     * @return array{date: string|null, time: string|null}
     */
    public function getUserTimeParts($utcDatetime, ?string $fallback = null): array
    {
        $userTime = $this->toUserTime($utcDatetime, $fallback);
        
        if (!$userTime) {
            return ['date' => null, 'time' => null];
        }

        $carbon = Carbon::parse($userTime);
        
        return [
            'date' => $carbon->format('Y-m-d'),
            'time' => $carbon->format('H:i'),
        ];
    }
}
