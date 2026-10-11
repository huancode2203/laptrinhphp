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
        Schema::table('auth_codes', function (Blueprint $table): void {
            $table->boolean('demo')->default(false);
        });
        Schema::create('integration_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
        Schema::table('auth_codes', function (Blueprint $table): void {
            $table->dropColumn('demo');
        });
    }
};
