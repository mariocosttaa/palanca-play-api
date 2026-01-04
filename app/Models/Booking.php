<?php
namespace App\Models;

use App\Actions\General\MoneyAction;
use App\Actions\General\TenantFileAction;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Manager\CurrencyModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'court_id',
        'user_id',
        'currency_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'price',
        'status',
        'payment_status',
        'payment_method',
        'present',
        'qr_code',
        'qr_code_verified',
    ];

    protected $casts = [
        'currency_id'      => 'integer',
        'start_date'       => 'date',
        'end_date'         => 'date',
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'price'            => 'integer', // Stored in cents
        'status'           => BookingStatusEnum::class,
        'payment_status'   => PaymentStatusEnum::class,
        'payment_method'   => PaymentMethodEnum::class,
        'present'          => 'boolean',
        'qr_code_verified' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currency()
    {
        return $this->belongsTo(CurrencyModel::class);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForCourt($query, $courtId)
    {
        return $query->where('court_id', $courtId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('status', BookingStatusEnum::PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', BookingStatusEnum::CONFIRMED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', BookingStatusEnum::CANCELLED);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', PaymentStatusEnum::PAID);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('start_date', $date);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete QR code when booking is deleted
        static::deleting(function ($booking) {
            try {
                if ($booking->qr_code) {
                    // Use TenantFileAction::delete following the documentation pattern
                    // Signature: delete($tenantId, $filePath, $fileUrl, $isPublic)
                    TenantFileAction::delete(
                        $booking->tenant_id,
                        null,              // filePath (null when using URL)
                        $booking->qr_code, // fileUrl (the URL stored in DB)
                        isPublic: true
                    );
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete QR code for booking', [
                    'booking_id' => $booking->id,
                    'tenant_id'  => $booking->tenant_id,
                    'qr_code'    => $booking->qr_code,
                    'error'      => $e->getMessage(),
                ]);
                // Don't throw exception - allow booking deletion to proceed even if QR deletion fails
            }
        });
    }
    
    public function getPriceFormattedAttribute()
    {
        return MoneyAction::format(
            amount: $this->price,
            currency: $this->tenant->currency,
            formatWithSymbol: true
        );
    }
}
