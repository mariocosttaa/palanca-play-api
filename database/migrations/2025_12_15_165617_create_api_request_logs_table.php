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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45);
            $table->string('method', 10);
            $table->string('endpoint', 255);
            $table->integer('status_code')->nullable();
            $table->integer('response_time')->nullable()->comment('Response time in milliseconds');
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
            $table->index(['endpoint', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
