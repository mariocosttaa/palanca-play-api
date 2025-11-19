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
        Schema::create('courts_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('court_id')->nullable()->constrained('courts')->cascadeOnDelete();
            $table->foreignId('court_type_id')->nullable()->constrained('courts_type')->cascadeOnDelete();
            $table->string('day_of_week_recurring', 20)->nullable();
            $table->date('specific_date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->json('breaks')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('court_id');
            $table->index('court_type_id');
            $table->index('specific_date');
            $table->index('day_of_week_recurring');
            $table->index(['tenant_id', 'specific_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courts_availabilities');
    }
};

