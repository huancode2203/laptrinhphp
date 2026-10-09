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
        Schema::create('ai_search_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key')->unique();
            $table->text('message');
            $table->json('result');
            $table->timestamps();
        });
        Schema::create('demo_data_batches', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->json('counts');
            $table->timestamps();
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedInteger('product_amount')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('product_amount');
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('booking_id');
        });
        Schema::dropIfExists('demo_data_batches');
        Schema::dropIfExists('ai_search_turns');
    }
};
