<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->integer('loyalty_points')->default(0);
            $t->integer('earned_points')->default(0);
            $t->string('google_sub')->nullable()->unique();
            $t->timestamp('phone_verified_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->string('avatar_path')->nullable();
        });
        Schema::table('branches', function (Blueprint $t): void {
            $t->unsignedSmallInteger('opens_minute')->default(180);
            $t->unsignedSmallInteger('closes_minute')->default(1380);
            $t->unsignedSmallInteger('peak_minute')->default(960);
            $t->unsignedSmallInteger('booking_days')->default(90);
            $t->unsignedTinyInteger('deposit_percent')->default(50);
            $t->unsignedSmallInteger('payment_minutes')->default(30);
            $t->unsignedTinyInteger('fixed_discount')->default(15);
            $t->string('image_path')->nullable();
        });
        Schema::table('bookings', function (Blueprint $t): void {
            $t->string('source')->default('online');
            $t->integer('deposit_amount')->default(0);
            $t->timestamp('payment_expires_at')->nullable()->index();
            $t->timestamp('reminded_at')->nullable();
        });
        Schema::table('membership_plans', function (Blueprint $t): void {
            $t->unsignedSmallInteger('early_days')->default(0);
            $t->unsignedInteger('points_threshold')->default(0);
        });
        Schema::table('community_posts', function (Blueprint $t): void {
            $t->string('moderation_status')->default('approved')->index();
            $t->json('moderation_result')->nullable();
            $t->timestamp('moderated_at')->nullable();
            $t->foreignId('moderated_by')->nullable()->constrained('users');
            $t->json('images')->nullable();
        });
        Schema::table('post_comments', fn (Blueprint $t) => $t->foreignId('parent_id')->nullable()->constrained('post_comments'));
        Schema::table('reviews', function (Blueprint $t): void {
            $t->json('images')->nullable();
            $t->text('reply')->nullable();
            $t->foreignId('replied_by')->nullable()->constrained('users');
        });
        Schema::table('conversations', fn (Blueprint $t) => $t->foreignId('booking_id')->nullable()->unique()->constrained());
        Schema::table('conversation_members', fn (Blueprint $t) => $t->timestamp('typing_until')->nullable());
        Schema::table('chat_messages', fn (Blueprint $t) => $t->json('attachments')->nullable());
        Schema::create('user_branches', function (Blueprint $t): void {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'branch_id']);
        });
        Schema::create('role_permissions', function (Blueprint $t): void {
            $t->id();
            $t->string('role');
            $t->string('permission');
            $t->boolean('allowed');
            $t->unique(['role', 'permission']);
        });
        Schema::create('pricing_rules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('court_id')->nullable()->constrained();
            $t->string('name');
            $t->unsignedTinyInteger('weekday')->nullable();
            $t->date('date')->nullable();
            $t->unsignedSmallInteger('from_minute');
            $t->unsignedSmallInteger('to_minute');
            $t->unsignedInteger('hourly_rate');
            $t->unsignedSmallInteger('priority')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('loyalty_entries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('request_key')->unique();
            $t->integer('points');
            $t->integer('balance');
            $t->string('description');
            $t->timestamps();
        });
        Schema::create('loyalty_rewards', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->unsignedInteger('points');
            $t->unsignedInteger('wallet_amount');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('favorite_courts', function (Blueprint $t): void {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('court_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'court_id']);
        });
        Schema::create('saved_posts', function (Blueprint $t): void {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'community_post_id']);
        });
        Schema::create('blocked_users', function (Blueprint $t): void {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $t->primary(['user_id', 'blocked_id']);
        });
        Schema::create('media_uploads', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('path')->unique();
            $t->string('name');
            $t->string('mime');
            $t->unsignedInteger('size');
            $t->timestamps();
        });
        Schema::create('support_tickets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('branch_id')->nullable()->constrained();
            $t->string('subject');
            $t->text('content');
            $t->string('status')->default('open');
            $t->text('reply')->nullable();
            $t->foreignId('replied_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('content_pages', function (Blueprint $t): void {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->string('type')->default('page');
            $t->text('content');
            $t->boolean('published')->default(false);
            $t->timestamps();
        });
        Schema::create('coaches', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->string('name');
            $t->text('bio')->nullable();
            $t->unsignedInteger('hourly_rate');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('coach_sessions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('coach_id')->constrained();
            $t->foreignId('booking_id')->unique()->constrained();
            $t->foreignId('user_id')->constrained();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->integer('amount');
            $t->string('status')->default('confirmed');
            $t->timestamps();
        });
        Schema::create('integration_transactions', function (Blueprint $t): void {
            $t->id();
            $t->string('provider');
            $t->string('transaction_id');
            $t->foreignId('booking_id')->nullable()->constrained();
            $t->integer('amount');
            $t->string('status');
            $t->timestamps();
            $t->unique(['provider', 'transaction_id']);
        });
        Schema::create('refund_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('booking_id')->constrained();
            $t->foreignId('payment_attempt_id')->nullable()->constrained();
            $t->string('request_key')->unique();
            $t->string('order_id')->unique();
            $t->integer('amount');
            $t->string('status')->default('pending');
            $t->string('message')->nullable();
            $t->timestamps();
        });
        Schema::create('phone_codes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('phone');
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['phone_codes', 'refund_requests', 'integration_transactions', 'coach_sessions', 'coaches', 'content_pages', 'support_tickets', 'media_uploads', 'blocked_users', 'saved_posts', 'favorite_courts', 'loyalty_rewards', 'loyalty_entries', 'pricing_rules', 'role_permissions', 'user_branches'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('chat_messages', fn (Blueprint $t) => $t->dropColumn('attachments'));
        Schema::table('conversation_members', fn (Blueprint $t) => $t->dropColumn('typing_until'));
        Schema::table('conversations', fn (Blueprint $t) => $t->dropConstrainedForeignId('booking_id'));
        Schema::table('reviews', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('replied_by');
            $t->dropColumn(['images', 'reply']);
        });
        Schema::table('post_comments', fn (Blueprint $t) => $t->dropConstrainedForeignId('parent_id'));
        Schema::table('community_posts', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('moderated_by');
            $t->dropColumn(['moderation_status', 'moderation_result', 'moderated_at', 'images']);
        });
        Schema::table('membership_plans', fn (Blueprint $t) => $t->dropColumn(['early_days', 'points_threshold']));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['source', 'deposit_amount', 'payment_expires_at', 'reminded_at']));
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(['opens_minute', 'closes_minute', 'peak_minute', 'booking_days', 'deposit_percent', 'payment_minutes', 'fixed_discount', 'image_path']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['loyalty_points', 'earned_points', 'google_sub', 'phone_verified_at', 'last_seen_at', 'avatar_path']));
    }
};
