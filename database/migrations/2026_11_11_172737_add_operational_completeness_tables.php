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
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->unsignedInteger('received_amount')->default(0);
            $table->unsignedInteger('settled_amount')->default(0);
            $table->string('review_reason', 300)->nullable();
        });
        Schema::create('payment_attempt_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('settled_amount')->default(0);
            $table->unique(['payment_attempt_id', 'booking_id']);
        });
        Schema::create('payment_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('provider_status');
            $table->unsignedInteger('expected_amount');
            $table->unsignedInteger('received_amount');
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('bank_transfer_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('bank_reference', 100);
            $table->json('media_ids')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cash_shifts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('opening_cash');
            $table->bigInteger('expected_cash')->nullable();
            $table->unsignedInteger('counted_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'user_id', 'status']);
        });
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 300)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'active']);
        });
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('reference')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('paid_amount')->default(0);
            $table->string('method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost');
            $table->unsignedInteger('amount');
            $table->unique(['purchase_order_id', 'product_id']);
        });
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('category');
            $table->string('description', 300);
            $table->unsignedInteger('amount');
            $table->string('method');
            $table->date('incurred_on');
            $table->string('source_type')->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'incurred_on']);
        });
        Schema::create('payrolls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('month', 7);
            $table->unsignedInteger('base_amount');
            $table->unsignedInteger('allowances')->default(0);
            $table->unsignedInteger('deductions')->default(0);
            $table->unsignedInteger('net_amount');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'user_id', 'month']);
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('unit_cost')->default(0);
        });
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->unsignedInteger('unit_cost')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedInteger('exchange_credit')->default(0);
        });
        Schema::create('sale_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('replacement_sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->string('kind');
            $table->unsignedInteger('amount');
            $table->json('items');
            $table->text('notes');
            $table->timestamps();
        });
        Schema::create('booking_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->string('last_error', 200)->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'channel']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('email_reminders')->default(false);
            $table->boolean('sms_reminders')->default(false);
        });
        Schema::table('community_posts', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
        });
        Schema::create('media_archives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_upload_id')->unique()->constrained('media_uploads')->restrictOnDelete();
            $table->string('original_path');
            $table->string('archive_path');
            $table->timestamp('archived_at');
            $table->timestamp('purge_after');
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('purged_at')->nullable();
        });
        Schema::create('backup_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose');
            $table->unsignedBigInteger('bytes');
            $table->json('summary');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['backup_records', 'media_archives', 'booking_deliveries', 'sale_adjustments', 'payrolls', 'expenses', 'purchase_order_items', 'purchase_orders', 'suppliers', 'cash_shifts', 'bank_transfer_claims', 'payment_reconciliations', 'payment_attempt_bookings'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('payment_attempts', fn (Blueprint $table) => $table->dropColumn(['received_amount', 'settled_amount', 'review_reason']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn(['unit_cost', 'returned_quantity']));
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('exchange_credit'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['email_reminders', 'sms_reminders']));
        Schema::table('community_posts', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};
