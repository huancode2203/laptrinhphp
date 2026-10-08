<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('wallet_balance')->default(0);
            $table->string('skill_level', 40)->default('Sơ cấp');
            $table->boolean('public_profile')->default(false);
        });
        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->unsignedInteger('amount');
            $table->string('method', 20);
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->unsignedInteger('value');
            $table->unsignedInteger('minimum_amount')->default(0);
            $table->unsignedInteger('max_discount')->nullable();
            $table->unsignedInteger('usage_limit')->default(100);
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('discount_amount')->default(0);
            $table->string('booking_type', 20)->default('single');
            $table->uuid('series_key')->nullable()->index();
        });
        Schema::create('court_holds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('expires_at');
            $table->boolean('released')->default(false);
            $table->timestamps();
            $table->index(['court_id', 'expires_at']);
        });
        Schema::create('wallet_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('kind', 30);
            $table->string('description');
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('sku', 40);
            $table->string('category', 40);
            $table->string('icon', 20)->default('🏸');
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'sku']);
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('reference', 32)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('clerk_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_amount');
            $table->string('method', 20);
            $table->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('amount');
            $table->timestamps();
        });
        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('type', 40);
            $table->string('skill_level', 40);
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['status', 'starts_at']);
        });
        Schema::create('post_joins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['community_post_id', 'user_id']);
        });
        Schema::create('post_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('content');
            $table->timestamps();
        });
        Schema::create('post_likes', function (Blueprint $table): void {
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['community_post_id', 'user_id']);
        });
        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('direct_key')->nullable()->unique();
            $table->foreignId('community_post_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('conversation_members', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('read_at')->nullable();
            $table->primary(['conversation_id', 'user_id']);
        });
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('content');
            $table->boolean('deleted')->default(false);
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('game_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->text('description');
            $table->string('format')->default('Đơn · Loại trực tiếp');
            $table->string('skill_level', 40)->default('Mọi trình độ');
            $table->string('image')->default('photo-1626224583764-f87db24ac4ea');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedInteger('fee')->default(0);
            $table->string('status', 20)->default('published');
            $table->timestamps();
        });
        Schema::create('court_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->foreignId('game_event_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('paid_amount')->default(0);
            $table->string('method', 20)->default('cash');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['game_event_id', 'user_id']);
        });
        Schema::create('tournament_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->unsignedSmallInteger('position');
            $table->foreignId('player_a')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('player_b')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('score_a')->nullable();
            $table->unsignedSmallInteger('score_b')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['game_event_id', 'round', 'position']);
        });
        Schema::create('commerce_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('kind', 20);
            $table->string('method', 20);
            $table->unsignedInteger('amount');
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('waiting');
            $table->timestamps();
            $table->unique(['court_id', 'user_id', 'starts_at', 'ends_at']);
        });
        Schema::create('auth_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash');
            $table->string('reset_hash', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['auth_codes', 'waitlist_entries', 'reviews', 'commerce_payments', 'tournament_matches', 'event_registrations', 'court_blocks', 'game_events', 'chat_messages', 'conversation_members', 'conversations', 'content_reports', 'post_likes', 'post_comments', 'post_joins', 'community_posts', 'sale_items', 'sales', 'products', 'wallet_entries', 'court_holds'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['discount_amount', 'booking_type', 'series_key']);
        });
        foreach (['coupons', 'memberships', 'membership_plans'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['active', 'wallet_balance', 'skill_level', 'public_profile']));
    }
};
