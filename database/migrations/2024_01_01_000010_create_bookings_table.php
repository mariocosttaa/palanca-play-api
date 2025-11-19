<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->bigInteger('price'); // Stored in cents
            $table->boolean('is_pending')->default(true);
            $table->boolean('is_cancelled')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('court_id');
            $table->index('user_id');
            $table->index(['court_id', 'start_date', 'start_time']);
            $table->index(['tenant_id', 'start_date']);
            $table->index('is_cancelled');
            $table->index('is_pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

