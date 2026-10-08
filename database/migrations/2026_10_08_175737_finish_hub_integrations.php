<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->string('coupon_code', 30)->nullable());
        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->integer('delta');
            $table->string('reason');
            $table->timestamps();
        });
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('order_id', 80)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('purpose', 20);
            $table->string('partner_code', 50);
            $table->unsignedInteger('amount');
            $table->string('order_info');
            $table->string('extra_data', 1000)->default('');
            $table->string('status', 20)->default('pending');
            $table->text('pay_url')->nullable();
            $table->string('trans_id', 64)->nullable()->unique();
            $table->dateTime('expires_at');
            $table->integer('result_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('inventory_adjustments');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('coupon_code'));
    }
};
