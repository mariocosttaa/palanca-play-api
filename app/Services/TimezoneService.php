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
    /**
     * Convert a date and time string from User TZ to UTC.
     *
     * @param string $date Y-m-d
     * @param string $time H:i
     * @return Carbon|null
     */
    public function convertSlotToUtc(string $date, string $time): ?Carbon
    {
        return $this->toUTC($date . ' ' . $time);
    }

    /**
     * Convert an array of slots (start/end times) from User TZ to UTC.
     * Returns the transformed slots and the UTC date of the first slot.
     *
     * @param string $date Y-m-d
     * @param array $slots Array of ['start' => 'H:i', 'end' => 'H:i']
     * @return array{slots: array, start_date: string|null}
     */
    public function convertSlotsToUtc(string $date, array $slots): array
    {
        $newSlots = [];
        $firstSlotStartUtc = null;

        foreach ($slots as $index => $slot) {
            if (isset($slot['start']) && isset($slot['end'])) {
                $startUtc = $this->convertSlotToUtc($date, $slot['start']);
                $endUtc = $this->convertSlotToUtc($date, $slot['end']);

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
     * @return array{date: string|null, time: string|null}
     */
    public function getUserTimeParts($utcDatetime): array
    {
        $userTime = $this->toUserTime($utcDatetime);
        
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
