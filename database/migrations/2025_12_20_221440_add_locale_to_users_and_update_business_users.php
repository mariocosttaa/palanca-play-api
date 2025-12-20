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
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale')->default('pt')->after('email');
        });

        Schema::table('business_users', function (Blueprint $table) {
            // Rename language to locale if possible, or add locale and drop language
            // Since language is enum, let's drop it and add locale as string
            $table->dropColumn('language');
            $table->string('locale')->default('pt')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('business_users', function (Blueprint $table) {
            $table->dropColumn('locale');
            $table->enum('language', ['en', 'pt', 'es', 'fr'])->default('pt')->after('email');
        });
    }
};
