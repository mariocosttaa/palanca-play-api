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
        Schema::create('courts_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('type', ['football', 'basketball', 'tennis', 'squash', 'badminton', 'padel', 'other'])->default('padel');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('interval_time_minutes');
            $table->integer('buffer_time_minutes');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courts_type');
    }
};

