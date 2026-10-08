<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('district');
            $table->string('address');
            $table->string('phone', 30)->nullable();
            $table->json('amenities')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->index();
            $table->string('phone', 30)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('hourly_rate');
            $table->unsignedInteger('peak_rate');
            $table->unsignedInteger('weekend_rate');
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('unit', 30);
            $table->unsignedInteger('price');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->uuid('request_key')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('confirmed');
            $table->unsignedInteger('court_amount');
            $table->unsignedInteger('service_amount')->default(0);
            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('paid_amount')->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'status', 'starts_at', 'ends_at']);
            $table->index(['user_id', 'starts_at']);
        });
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('amount');
            $table->unique(['booking_id', 'service_id']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('kind', 20)->default('payment');
            $table->string('method', 20);
            $table->unsignedInteger('amount');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('reason');
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->index(['court_id', 'status', 'starts_at', 'ends_at']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'maintenances', 'payments', 'booking_services', 'bookings', 'services', 'courts'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['role', 'phone']);
        });
        Schema::dropIfExists('branches');
    }
};
