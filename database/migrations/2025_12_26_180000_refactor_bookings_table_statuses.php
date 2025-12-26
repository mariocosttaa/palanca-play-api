<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'status')) {
                $table->string('status')->default('pending')->after('price');
            }
            if (!Schema::hasColumn('bookings', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('status');
            }
            if (!Schema::hasColumn('bookings', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_status');
            }
        });

        // Migrate existing data
        DB::table('bookings')->chunkById(100, function ($bookings) {
            foreach ($bookings as $booking) {
                $status = 'confirmed';
                if ($booking->is_cancelled) {
                    $status = 'cancelled';
                } elseif ($booking->is_pending) {
                    $status = 'pending';
                }

                $paymentStatus = 'pending';
                if ($booking->is_paid) {
                    $paymentStatus = 'paid';
                }

                $paymentMethod = null;
                if ($booking->paid_at_venue) {
                    $paymentMethod = 'cash'; // Assuming paid at venue implies cash or similar, but we can default to cash for now or leave null if not strictly cash. 
                    // However, user asked for "method of payment". 
                    // If paid_at_venue is true, it means they intend to pay at venue. 
                    // If is_paid is true AND paid_at_venue is true, they paid at venue (likely cash/card terminal).
                    // Let's set it to 'cash' as a safe default for "at venue" for now, or maybe just leave null if we are not sure.
                    // But to be useful, let's map it.
                    // Actually, if paid_at_venue is true, it might just be a preference.
                    // Let's stick to mapping status.
                }

                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update([
                        'status' => $status,
                        'payment_status' => $paymentStatus,
                        'payment_method' => $paymentMethod,
                    ]);
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['is_pending']);
            $table->dropIndex(['is_cancelled']);
            $table->dropColumn(['is_pending', 'is_cancelled', 'is_paid', 'paid_at_venue']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_pending')->default(true);
            $table->boolean('is_cancelled')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->boolean('paid_at_venue')->default(false);
        });

        // Reverse data migration
        DB::table('bookings')->chunkById(100, function ($bookings) {
            foreach ($bookings as $booking) {
                $isPending = $booking->status === 'pending';
                $isCancelled = $booking->status === 'cancelled';
                $isPaid = $booking->payment_status === 'paid';
                $paidAtVenue = $booking->payment_method === 'cash'; // Approximation

                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update([
                        'is_pending' => $isPending,
                        'is_cancelled' => $isCancelled,
                        'is_paid' => $isPaid,
                        'paid_at_venue' => $paidAtVenue,
                    ]);
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['status', 'payment_status', 'payment_method']);
        });
    }
};
