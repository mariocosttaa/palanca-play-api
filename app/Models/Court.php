<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Court extends Model
{
    /** @use HasFactory<\Database\Factories\CourtFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'courts';

    protected $fillable = [
        'tenant_id',
        'court_type_id',
        'name',
        'number',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function images()
    {
        return $this->hasMany(CourtImage::class)->orderBy('is_primary', 'desc')->orderBy('created_at', 'asc');
    }

    public function primaryImage()
    {
        return $this->hasOne(CourtImage::class)->where('is_primary', true);
    }

    public function availabilities()
    {
        return $this->hasMany(CourtAvailability::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // Scopes
    public function scopeForCourtType($query, $courtTypeId)
    {
        return $query->where('court_type_id', $courtTypeId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Accessors
    public function getEffectiveAvailabilityAttribute()
    {
        if ($this->availabilities()->exists()) {
            return $this->availabilities;
        }

        return $this->courtType->availabilities ?? collect();
    }

    /**
     * Get effective availabilities for this court.
     * 
     * Logic:
     * 1. If court has custom availabilities, return those
     * 2. Otherwise, return court type availabilities
     * 3. If neither exists, return empty collection
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEffectiveAvailabilities()
    {
        // Check if court has custom availabilities
        $courtAvailabilities = $this->availabilities()->get();
        
        if ($courtAvailabilities->isNotEmpty()) {
            return $courtAvailabilities;
        }

        // Otherwise, check court type availabilities
        if ($this->courtType) {
            $courtTypeAvailabilities = $this->courtType->availabilities()->get();
            
            if ($courtTypeAvailabilities->isNotEmpty()) {
                return $courtTypeAvailabilities;
            }
        }

        // Return empty collection if nothing found
        return collect();
    }
    // Availability Logic
    public function getAvailableDates($startDate, $endDate)
    {
        $dates = [];
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            if ($this->getAvailableSlots($date->format('Y-m-d'))->isNotEmpty()) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }

    public function getAvailableSlots($date)
    {
        $date = \Carbon\Carbon::parse($date);
        $dayOfWeek = $date->format('l');

        // 1. Get Operating Hours
        // Check specific date first
        $availability = $this->availabilities()
            ->whereDate('specific_date', $date->format('Y-m-d'))
            ->first();

        // If no specific date, check recurring day
        if (!$availability) {
            $availability = $this->availabilities()
                ->where('day_of_week_recurring', $dayOfWeek)
                ->first();
        }

        // If no court specific, check court type (if applicable logic exists, but for now stick to court/tenant)
        // Assuming if no availability record found, it's closed? Or use default?
        // Let's assume closed if no record found for now, or maybe check Tenant default?
        // The user said "check first at the ... create_courts_availabilities_table.php"
        
        if (!$availability) {
            \Illuminate\Support\Facades\Log::info('No availability found for date: ' . $date->format('Y-m-d'));
            return collect();
        }

        if (!$availability->is_available) {
            \Illuminate\Support\Facades\Log::info('Availability found but is_available is false');
            return collect();
        }

        $startTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $availability->start_time);
        $endTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $availability->end_time);
        $breaks = $availability->breaks ?? [];

        // 2. Get Existing Bookings
        $bookings = $this->bookings()
            ->whereDate('start_date', $date->format('Y-m-d'))
            ->where(function ($query) {
                $query->where('is_cancelled', false);
            })
            ->get();

        // 3. Generate Slots
        $interval = $this->tenant->booking_interval_minutes ?? 60;
        $buffer = $this->tenant->buffer_between_bookings_minutes ?? 0;
        $slots = collect();

        $currentSlotStart = $startTime->copy();

        while ($currentSlotStart->copy()->addMinutes($interval)->lte($endTime)) {
            $currentSlotEnd = $currentSlotStart->copy()->addMinutes($interval);
            
            // Check against breaks
            $isBreak = false;
            foreach ($breaks as $break) {
                $breakStart = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $break['start']);
                $breakEnd = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $break['end']);
                
                // If slot overlaps with break
                if ($currentSlotStart->lt($breakEnd) && $currentSlotEnd->gt($breakStart)) {
                    $isBreak = true;
                    break;
                }
            }

            if ($isBreak) {
                $currentSlotStart->addMinutes($interval); // Move to next potential slot? Or just skip?
                // Actually, if fixed slots, just skip. If floating, maybe move?
                // Let's assume fixed slots based on start time for now.
                continue;
            }

            // Check against bookings
            $isBooked = false;
            foreach ($bookings as $booking) {
                $bookingStart = \Carbon\Carbon::parse($booking->start_date->format('Y-m-d') . ' ' . $booking->start_time->format('H:i:s'));
                $bookingEnd = \Carbon\Carbon::parse($booking->end_date->format('Y-m-d') . ' ' . $booking->end_time->format('H:i:s'));
                
                // Apply buffer to booking end
                $bookingEndWithBuffer = $bookingEnd->copy()->addMinutes($buffer);

                // Check overlap
                // Slot overlaps if: SlotStart < BookingEndWithBuffer AND SlotEnd > BookingStart
                if ($currentSlotStart->lt($bookingEndWithBuffer) && $currentSlotEnd->gt($bookingStart)) {
                    $isBooked = true;
                    break;
                }
            }

            if (!$isBooked) {
                $slots->push([
                    'start' => $currentSlotStart->format('H:i'),
                    'end' => $currentSlotEnd->format('H:i'),
                ]);
            }

            $currentSlotStart->addMinutes($interval);
        }

        return $slots;
    }

    /**
     * Check if a time slot is available for booking
     * 
     * @param string $date Date in Y-m-d format
     * @param string $startTime Start time in H:i format
     * @param string $endTime End time in H:i format
     * @return string|null Returns null if available, or error message string if not available
     */
    public function checkAvailability($date, $startTime, $endTime)
    {
        $date = \Carbon\Carbon::parse($date);
        $dayOfWeek = $date->format('l');
        $reqStart = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
        $reqEnd = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);

        // 1. Check if there's any availability configuration
        $availability = $this->availabilities()
            ->whereDate('specific_date', $date->format('Y-m-d'))
            ->first();

        if (!$availability) {
            $availability = $this->availabilities()
                ->where('day_of_week_recurring', $dayOfWeek)
                ->first();
        }

        if (!$availability) {
            return 'Quadra não possui horário de funcionamento configurado para esta data.';
        }

        // 2. Check if court is marked as unavailable
        if (!$availability->is_available) {
            return 'Quadra marcada como indisponível nesta data.';
        }

        // 3. Check if requested time is within operating hours
        $operatingStart = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $availability->start_time);
        $operatingEnd = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $availability->end_time);

        if ($reqStart->lt($operatingStart) || $reqEnd->gt($operatingEnd)) {
            return 'Horário solicitado está fora do horário de funcionamento da quadra (' 
                . $availability->start_time . ' - ' . $availability->end_time . ').';
        }

        // 4. Check if time conflicts with a break
        $breaks = $availability->breaks ?? [];
        foreach ($breaks as $break) {
            $breakStart = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $break['start']);
            $breakEnd = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $break['end']);
            
            if ($reqStart->lt($breakEnd) && $reqEnd->gt($breakStart)) {
                return 'Horário conflita com uma pausa configurada (' 
                    . $break['start'] . ' - ' . $break['end'] . ').';
            }
        }

        // 5. Check if time conflicts with existing bookings
        $bookings = $this->bookings()
            ->whereDate('start_date', $date->format('Y-m-d'))
            ->where('is_cancelled', false)
            ->get();

        $buffer = $this->tenant->buffer_between_bookings_minutes ?? 0;

        foreach ($bookings as $booking) {
            $bookingStart = \Carbon\Carbon::parse($booking->start_date->format('Y-m-d') . ' ' . $booking->start_time->format('H:i:s'));
            $bookingEnd = \Carbon\Carbon::parse($booking->end_date->format('Y-m-d') . ' ' . $booking->end_time->format('H:i:s'));
            $bookingEndWithBuffer = $bookingEnd->copy()->addMinutes($buffer);

            if ($reqStart->lt($bookingEndWithBuffer) && $reqEnd->gt($bookingStart)) {
                return 'Este horário já está reservado (' 
                    . $booking->start_time->format('H:i') . ' - ' . $booking->end_time->format('H:i') . ').';
            }
        }

        // If we get here, the slot is available
        return null;
    }

}

