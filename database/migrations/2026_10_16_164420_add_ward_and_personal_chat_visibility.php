<?php

use App\LocationService;
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
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('ward')->nullable()->index();
            $table->string('google_place_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedBigInteger('cleared_through_id')->default(0);
        });
        app(LocationService::class)->updateDemoAddresses();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversation_members', fn (Blueprint $table) => $table->dropColumn(['hidden_at', 'cleared_through_id']));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn(['ward', 'google_place_id', 'latitude', 'longitude']));
    }
};
