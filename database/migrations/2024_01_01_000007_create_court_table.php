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
        Schema::create('court', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_type_id')->constrained('courts_type')->cascadeOnDelete();
            $table->enum('type', ['padel', 'tennis', 'squash', 'badminton', 'other'])->default('padel');
            $table->string('name')->nullable();
            $table->string('number')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('court_type_id');
            $table->index('status');
            $table->index(['court_type_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court');
    }
};

