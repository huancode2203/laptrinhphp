<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\GameEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExpandedDemoSeeder;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpandedDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_expanded_dataset_covers_business_tables_without_overlap_and_preserves_user_edits_when_repeated(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(19, 0));
        Http::preventStrayRequests();
        $this->seed([DatabaseSeeder::class, RealisticDemoSeeder::class]);
        $customer = User::where('email', 'customer@badminton.test')->firstOrFail();
        $customer->update(['name' => 'Nguyễn Thảo Nguyên']);
        $this->seed(ExpandedDemoSeeder::class);
        foreach (['users', 'branches', 'courts', 'services', 'bookings', 'booking_services', 'payments', 'maintenances', 'audit_logs', 'memberships', 'coupons', 'court_holds', 'wallet_entries', 'products', 'sales', 'sale_items', 'community_posts', 'post_joins', 'post_comments', 'post_likes', 'content_reports', 'conversations', 'conversation_members', 'chat_messages', 'game_events', 'court_blocks', 'event_registrations', 'tournament_matches', 'commerce_payments', 'reviews', 'waitlist_entries', 'inventory_adjustments', 'payment_attempts', 'pricing_rules', 'loyalty_entries', 'favorite_courts', 'saved_posts', 'blocked_users', 'support_tickets', 'content_pages', 'coaches', 'coach_sessions', 'integration_transactions', 'refund_requests', 'user_branches', 'notifications'] as $table) {
            $this->assertGreaterThanOrEqual(30, DB::table($table)->count(), $table);
        }
        $this->assertGreaterThanOrEqual(1200, Booking::count());
        $this->assertGreaterThanOrEqual(60, Booking::whereDate('starts_at', now())->count());
        $overlaps = DB::table('bookings as a')->join('bookings as b', function ($q) {
            $q->on('a.court_id', '=', 'b.court_id')->on('a.id', '<', 'b.id')->on('a.starts_at', '<', 'b.ends_at')->on('a.ends_at', '>', 'b.starts_at');
        })->where('a.status', '!=', 'cancelled')->where('b.status', '!=', 'cancelled')->count();
        $this->assertSame(0, $overlaps);
        $leagues = GameEvent::where('title', 'like', 'Giải cầu lông phong trào · %')->where('format', 'round_robin')->get();
        foreach ($leagues as $league) {
            $matches = $league->matches()->get();
            $this->assertCount(28, $matches);
            $this->assertSame(7, $matches->pluck('round')->unique()->count());
            $this->assertSame(28, $matches->map(fn ($m) => implode('-', collect([$m->player_a, $m->player_b])->sort()->all()))->unique()->count());
        }
        foreach (Booking::where('reference', 'like', 'RALLY-BH-%')->with('payments')->get() as $booking) {
            $net = $booking->payments->sum(fn ($p) => $p->kind === 'refund' ? -$p->amount : $p->amount);
            $this->assertEquals($booking->paid_amount, $net, $booking->reference);
            $this->assertSame($booking->court_amount + $booking->service_amount + $booking->product_amount - $booking->discount_amount, $booking->total_amount);
            if ($booking->status === 'completed') {
                $this->assertSame($booking->total_amount, $booking->paid_amount);
            }
        }
        foreach (User::where('email', 'like', 'rally%@badminton.test')->get() as $player) {
            $this->assertEquals($player->wallet_balance, DB::table('wallet_entries')->where('user_id', $player->id)->sum('amount'));
            $this->assertEquals($player->loyalty_points, DB::table('loyalty_entries')->where('user_id', $player->id)->sum('points'));
        }
        $before = [Booking::count(), User::count(), DB::table('wallet_entries')->count()];
        $legacy = $leagues->first();
        $legacy->matches()->where('round', '>', 1)->delete();
        $guide = DB::table('content_pages')->where('slug', 'huong-dan-rally-1')->first();
        DB::table('content_pages')->where('id', $guide->id)->update(['content' => 'Hướng dẫn do quản trị chỉnh sửa.']);
        $this->seed(ExpandedDemoSeeder::class);
        $this->assertSame($before, [Booking::count(), User::count(), DB::table('wallet_entries')->count()]);
        $this->assertSame(28, $legacy->matches()->count());
        $this->assertSame('Hướng dẫn do quản trị chỉnh sửa.', DB::table('content_pages')->where('id', $guide->id)->value('content'));
        $this->assertSame('Nguyễn Thảo Nguyên', $customer->fresh()->name);
        $this->assertSame('Nguyễn Đức Hùng', User::where('email', 'admin@badminton.test')->value('name'));
        $this->assertSame(30, Branch::count());
        $this->actingAs($customer)->getJson('/api/v1/account')->assertOk()->assertJsonPath('data.profile_stats.sessions', Booking::where('user_id', $customer->id)->where('status', 'completed')->count());
        Http::assertNothingSent();
    }

    public function test_seeding_is_not_allowed_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(\RuntimeException::class);
        app(ExpandedDemoSeeder::class)->run();
    }
}
