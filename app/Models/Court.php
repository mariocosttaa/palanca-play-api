<?php
namespace App\Models;

use App\Actions\General\TenantFileAction;
use App\Enums\BookingStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    public function getAvailableDates($startDate, $endDate, $excludeBookingId = null)
    {
        $dates  = [];
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            if ($this->getAvailableSlots($date->format('Y-m-d'), $excludeBookingId)->isNotEmpty()) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }

    /**
     * Get available time slots for a specific date.
     * 
     * @param string|\Carbon\Carbon $date
     * @param int|null $excludeBookingId Optional booking ID to ignore (for updates)
     * @param int|null $excludeUserId Optional user ID to bypass buffer checks for sequential bookings
     * @return \Illuminate\Support\Collection
     */
    public function getAvailableSlots($date, $excludeBookingId = null, $excludeUserId = null)
    {
        $date      = \Carbon\Carbon::parse($date);
        $dayOfWeek = $date->format('l');

        // 1. Get Operating Hours
        // Check specific date first
        $availability = $this->availabilities()
            ->whereDate('specific_date', $date->format('Y-m-d'))
            ->first();

        // If no specific date, check recurring day
        if (! $availability) {
            $availability = $this->availabilities()
                ->where('day_of_week_recurring', $dayOfWeek)
                ->first();
        }

        // If no court specific, check court type (if applicable logic exists, but for now stick to court/tenant)
        // Assuming if no availability record found, it's closed? Or use default?
        // Let's assume closed if no record found for now, or maybe check Tenant default?
        // The user said "check first at the ... create_courts_availabilities_table.php"

        if (! $availability) {
            \Illuminate\Support\Facades\Log::info('No availability found for date: ' . $date->format('Y-m-d'));
            return collect();
        }

        if (! $availability->is_available) {
            \Illuminate\Support\Facades\Log::info('Availability found but is_available is false');
            return collect();
        }

        $tenantTimezone = $this->tenant->timezone ?? 'UTC';
        $startTime = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $availability->start_time, $tenantTimezone)->setTimezone('UTC');
        $endTime   = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $availability->end_time, $tenantTimezone)->setTimezone('UTC');
        
        // Handle overnight operating hours
        if ($endTime->lt($startTime)) {
            $endTime->addDay();
        }

        $breaks    = $availability->breaks ?? [];

            // 2. Get Existing Bookings
            $bookingsQuery = $this->bookings()
                ->whereDate('start_date', $date->format('Y-m-d'))
                ->where(function ($query) {
                    $query->where('status', '!=', BookingStatusEnum::CANCELLED);
                });

            if ($excludeBookingId) {
                $bookingsQuery->where('id', '!=', $excludeBookingId);
            }

            $bookings = $bookingsQuery->get();

            // 3. Generate Slots
            // Use court type's interval and buffer time settings
            $interval = $this->courtType->interval_time_minutes ?? $this->tenant->booking_interval_minutes ?? 60;
            $buffer   = $this->courtType->buffer_time_minutes ?? 0;
            $slots    = collect();

            $currentSlotStart = $startTime->copy();

            while ($currentSlotStart->copy()->addMinutes($interval)->lte($endTime)) {
                $currentSlotEnd = $currentSlotStart->copy()->addMinutes($interval);

                $collisionEndTime = null;

                // Check against breaks
                foreach ($breaks as $break) {
                    $breakStart = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $break['start']);
                    $breakEnd   = \Carbon\Carbon::parse($date->format('Y-m-d') . ' ' . $break['end']);

                    // If slot overlaps with break
                    if ($currentSlotStart->lt($breakEnd) && $currentSlotEnd->gt($breakStart)) {
                        $collisionEndTime = $collisionEndTime ? $collisionEndTime->max($breakEnd) : $breakEnd;
                    }
                }

                // Check against bookings
                foreach ($bookings as $booking) {
                    // Bookings are stored in UTC. We use the casted attributes which Laravel 
                    // handles with the app timezone, so we convert them back to UTC.
                    $bookingStart = $booking->start_date->copy()->setTimeFrom($booking->start_time)->setTimezone('UTC');
                    $bookingEnd   = $booking->end_date->copy()->setTimeFrom($booking->end_time)->setTimezone('UTC');

                    // Apply buffer to booking end
                    $bookingEndWithBuffer = $bookingEnd->copy()->addMinutes($buffer);
                    
                    // Calculate current slot end with buffer
                    $currentSlotEndWithBuffer = $currentSlotEnd->copy()->addMinutes($buffer);

                    // Special case: If it's the same user, we ignore the buffer for the overlap check.
                    // ONLY if the bookings are perfectly sequential (no gap).
                    if ($excludeUserId && $booking->user_id == $excludeUserId) {
                        if ($currentSlotStart->format('H:i') === $bookingEnd->format('H:i') || 
                            $currentSlotEnd->format('H:i') === $bookingStart->format('H:i')) {
                            $bookingEndWithBuffer = $bookingEnd;
                            $currentSlotEndWithBuffer = $currentSlotEnd;
                        }
                    }

                    // Check overlap
                    // Slot overlaps if: SlotStart < BookingEndWithBuffer AND SlotEndWithBuffer > BookingStart
                    if ($currentSlotStart->lt($bookingEndWithBuffer) && $currentSlotEndWithBuffer->gt($bookingStart)) {
                        // For same user, we only skip to the end of the booking, not the buffer.
                        // This allows the next iteration to check the sequential slot (which will bypass the buffer).
                        $skipTo = ($excludeUserId && $booking->user_id == $excludeUserId) ? $bookingEnd : $bookingEndWithBuffer;
                        $collisionEndTime = $collisionEndTime ? $collisionEndTime->max($skipTo) : $skipTo;
                    }
                }

            if ($collisionEndTime) {
                // If collision, move start time to the end of the collision
                // Ensure we actually advance to avoid infinite loops
                if ($collisionEndTime->lte($currentSlotStart)) {
                    $currentSlotStart->addMinutes(1); // Fallback to avoid infinite loop
                } else {
                    $currentSlotStart = $collisionEndTime->copy();
                }
                continue;
            }

            // No collision, add slot
            // Return slots in the user's timezone (or tenant's if no user context)
            $displayTimezone = app(\App\Services\TimezoneService::class)->getContextTimezone($this->tenant->timezone);
            
            $slots->push([
                'start' => $currentSlotStart->copy()->setTimezone($displayTimezone)->format('H:i'),
                'end'   => $currentSlotEnd->copy()->setTimezone($displayTimezone)->format('H:i'),
            ]);

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
     * @param string|int|null $excludeUserId Optional User ID to exclude buffer checks for sequential bookings
     * @param string|int|null $excludeBookingId Optional Booking ID to exclude from availability checks (for updates)
     * @return string|null Returns null if available, or error message string if not available
     */
    /**
     * Check if a time slot is available for booking
     *
     * @param string $date Date in Y-m-d format (UTC)
     * @param string $startTime Start time in H:i format (UTC)
     * @param string $endTime End time in H:i format (UTC)
     * @param string|int|null $excludeUserId Optional User ID to exclude buffer checks for sequential bookings
     * @param string|int|null $excludeBookingId Optional Booking ID to exclude from availability checks (for updates)
     * @return string|null Returns null if available, or error message string if not available
     */
    public function checkAvailability($date, $startTime, $endTime, $excludeUserId = null, $excludeBookingId = null)
    {
        // Inputs are in UTC
        $utcStart = \Carbon\Carbon::parse($date . ' ' . $startTime, 'UTC');
        $utcEnd = \Carbon\Carbon::parse($date . ' ' . $endTime, 'UTC');

        // Get Tenant Timezone (using the column, not the relationship)
        $tenantTimezone = $this->tenant->timezone ?? 'UTC';

        // Convert UTC inputs to Tenant Timezone for Operating Hours check
        $localStart = $utcStart->copy()->setTimezone($tenantTimezone);
        $localEnd = $utcEnd->copy()->setTimezone($tenantTimezone);

        $localDate = $localStart->format('Y-m-d');
        $dayOfWeek = $localStart->format('l');

        // 1. Check if there's any availability configuration (using Local Date/Day)
        $availability = $this->availabilities()
            ->whereDate('specific_date', $localDate)
            ->first();

        if (! $availability) {
            $availability = $this->availabilities()
                ->where('day_of_week_recurring', $dayOfWeek)
                ->first();
        }

        if (! $availability) {
            return 'Quadra não possui horário de funcionamento configurado para esta data.';
        }

        // 2. Check if court is marked as unavailable
        if (! $availability->is_available) {
            return 'Quadra marcada como indisponível nesta data.';
        }

        // 3. Check if requested time is within operating hours (using Local Time)
        // Operating hours are stored as H:i:s strings, implying local time
        $operatingStart = \Carbon\Carbon::parse($localDate . ' ' . $availability->start_time, $tenantTimezone);
        $operatingEnd   = \Carbon\Carbon::parse($localDate . ' ' . $availability->end_time, $tenantTimezone);

        // Handle overnight operating hours (e.g. 22:00 - 02:00)
        if ($operatingEnd->lt($operatingStart)) {
            $operatingEnd->addDay();
        }

        if ($localStart->lt($operatingStart) || $localEnd->gt($operatingEnd)) {
            return 'Horário solicitado está fora do horário de funcionamento da quadra ('
            . $availability->start_time . ' - ' . $availability->end_time . ').';
        }

        // 4. Check if time conflicts with a break (using Local Time)
        $breaks = $availability->breaks ?? [];
        foreach ($breaks as $break) {
            $breakStart = \Carbon\Carbon::parse($localDate . ' ' . $break['start'], $tenantTimezone);
            $breakEnd   = \Carbon\Carbon::parse($localDate . ' ' . $break['end'], $tenantTimezone);

            if ($localStart->lt($breakEnd) && $localEnd->gt($breakStart)) {
                return 'Horário conflita com uma pausa configurada ('
                    . $break['start'] . ' - ' . $break['end'] . ').';
            }
        }

        // 5. Check if time conflicts with existing bookings (using UTC)
        // We need to check for overlaps in UTC.
        // Since the booking might span across days in UTC or Local, we should check a range around the requested time.
        // A safe bet is to check bookings that start/end on the UTC date, previous day, and next day.
        
        $checkDateUtc = $utcStart->format('Y-m-d');
        $prevDateUtc = $utcStart->copy()->subDay()->format('Y-m-d');
        $nextDateUtc = $utcStart->copy()->addDay()->format('Y-m-d');

        $bookingsQuery = $this->bookings()
            ->whereIn(DB::raw('DATE(start_date)'), [$prevDateUtc, $checkDateUtc, $nextDateUtc])
            ->where('status', '!=', BookingStatusEnum::CANCELLED);

        if ($excludeBookingId) {
            $bookingsQuery->where('id', '!=', $excludeBookingId);
        }

        $bookings = $bookingsQuery->get();

        // Use court type's buffer time setting
        $buffer = $this->courtType->buffer_time_minutes ?? 0;

        // Calculate requested end with buffer
        $utcEndWithBuffer = $utcEnd->copy()->addMinutes($buffer);

        foreach ($bookings as $booking) {
            // Bookings are stored in UTC. We use the casted attributes which Laravel 
            // handles with the app timezone, so we convert them back to UTC.
            $bookingStart = $booking->start_date->copy()->setTimeFrom($booking->start_time)->setTimezone('UTC');
            $bookingEnd   = $booking->end_date->copy()->setTimeFrom($booking->end_time)->setTimezone('UTC');

            // Calculate existing booking buffer end
            $bookingEndWithBuffer = $bookingEnd->copy()->addMinutes($buffer);
            
            // Local copy of requested end with buffer for this specific booking check
            $currentUtcEndWithBuffer = $utcEnd->copy()->addMinutes($buffer);

            // Special case: If it's the same user, we ignore the buffer for the overlap check
            // ONLY if the bookings are perfectly sequential (no gap)
            if ($excludeUserId && $booking->user_id == $excludeUserId) {
                if ($utcStart->format('H:i') === $bookingEnd->format('H:i') || 
                    $utcEnd->format('H:i') === $bookingStart->format('H:i')) {
                    $bookingEndWithBuffer = $bookingEnd;
                    $currentUtcEndWithBuffer = $utcEnd;
                }
            }

            if ($utcStart->lt($bookingEndWithBuffer) && $currentUtcEndWithBuffer->gt($bookingStart)) {
                // Return error in User Timezone (or Tenant fallback) for better UX
                $displayTimezone = app(\App\Services\TimezoneService::class)->getContextTimezone($tenantTimezone);
                
                $bookingStartLocal = $bookingStart->copy()->setTimezone($displayTimezone);
                $bookingEndLocal = $bookingEnd->copy()->setTimezone($displayTimezone);
                
                $message = 'Este horário já está reservado ('
                . $bookingStartLocal->format('H:i') . ' - ' . $bookingEndLocal->format('H:i') . ').';

                // If the overlap is only with the buffer, make it clear in the error message.
                // This helps users understand why a slot starting exactly when another ends is blocked.
                if (($utcStart->gte($bookingEnd) && $utcStart->lt($bookingEndWithBuffer)) || 
                    ($utcEnd->lte($bookingStart) && $currentUtcEndWithBuffer->gt($bookingStart))) {
                    $message .= ' (Incluindo intervalo de manutenção de ' . $buffer . ' min).';
                }

                return $message;
            }
        }

        // If we get here, the slot is available
        return null;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete all associated image files when court is deleted (soft delete or force delete)
        static::deleting(function ($court) {
            try {
                // Get all images before deletion
                $images = $court->images()->get();

                foreach ($images as $image) {
                    try {
                        // Delete the image file using TenantFileAction following the documentation pattern
                        // Signature: delete($tenantId, $filePath, $fileUrl, $isPublic)
                        if ($image->path) {
                            TenantFileAction::delete(
                                $court->tenant_id,
                                null,         // filePath (null when using URL)
                                $image->path, // fileUrl (the URL stored in DB)
                                isPublic: true
                            );
                        }

                        // Delete the image record (needed for soft deletes since cascade only works on hard deletes)
                        $image->delete();
                    } catch (\Exception $e) {
                        Log::error('Failed to delete court image file', [
                            'court_id'   => $court->id,
                            'image_id'   => $image->id,
                            'image_path' => $image->path,
                            'tenant_id'  => $court->tenant_id,
                            'error'      => $e->getMessage(),
                        ]);
                        // Continue deleting other images even if one fails
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete court images', [
                    'court_id'  => $court->id,
                    'tenant_id' => $court->tenant_id,
                    'error'     => $e->getMessage(),
                ]);
                // Don't throw exception - allow court deletion to proceed even if image deletion fails
            }
        });
    }

}
