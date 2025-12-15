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
        Schema::create('emails_sent', function (Blueprint $table) {
            $table->id();
            $table->string('user_email');
            $table->string('subject');
            $table->string('title');
            $table->longText('html_content');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('user_email');
            // Index for email history queries
            $table->index(['user_email', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails_sent');
    }
};
