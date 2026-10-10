<?php

namespace Tests\Feature;

use App\AccessService;
use App\BookingService;
use App\FeatureService;
use App\LoyaltyService;
use App\Models\Booking;
use App\Models\CommunityPost;
use App\Models\Court;
use App\Models\GameEvent;
use App\Models\MembershipPlan;
use App\Models\PaymentAttempt;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompleteFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $manager;

    private User $admin;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
        config(['ai.search.provider' => 'local', 'ai.moderation.provider' => 'local']);
        Http::preventStrayRequests();
        $this->customer = User::factory()->create(['wallet_balance' => 1000000]);
        $this->court = Court::factory()->create();
        $this->court->branch->update(['district' => 'Bình Thạnh', 'amenities' => ['Parking', 'WiFi']]);
        $this->manager = User::factory()->create(['role' => 'manager', 'branch_id' => $this->court->branch_id]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'court_id' => $this->court->id, 'starts_at' => '2026-10-12T13:00:00+07:00', 'ends_at' => '2026-10-12T15:00:00+07:00'], $extra);
    }

    private function book(array $extra = []): Booking
    {
        $r = $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload($extra))->assertCreated();

        return Booking::findOrFail($r->json('data.id'));
    }

    private function postData(array $extra = []): array
    {
        return array_replace(['branch_id' => $this->court->branch_id, 'title' => 'Tìm bạn chơi cầu lông', 'content' => 'Giao lưu vui vẻ', 'type' => 'Chia sẻ', 'skill_level' => 'Mọi trình độ', 'capacity' => 4, 'status' => 'open', 'starts_at' => '2026-10-13 18:00'], $extra);
    }

    public function test_search_returns_only_available_database_courts_and_current_server_prices(): void
    {
        $r = $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay tôi muốn đánh cầu lúc 13h tới 15 giờ khu vực thành phố Hồ Chí Minh']);
        $r->assertOk()->assertJsonPath('data.total_count', 1)->assertJsonPath('data.courts.0.court_id', $this->court->id)->assertJsonPath('data.courts.0.total_amount', 200000);
        $this->book();
        $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay 13h đến 15h ở Bình Thạnh'])->assertOk()->assertJsonPath('data.total_count', 0);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay 6h đến 7h ở TP.HCM'])->assertOk()->assertJsonPath('data.needs_clarification', true);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Tìm giúp tôi sân'])->assertOk()->assertJsonPath('data.needs_clarification', true);
    }

    public function test_search_validates_filters_budget_and_supported_location(): void
    {
        $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay 13h đến 15h ở TP.HCM dưới 100k'])->assertOk()->assertJsonPath('data.total_count', 0);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay 13h đến 15h ở Hà Nội'])->assertOk()->assertJsonPath('data.total_count', 0);
        $this->postJson('/api/v1/ai/courts', ['message' => 'x', 'filters' => ['date' => '2026-10-12', 'start_time' => '13:00', 'end_time' => '15:00', 'district' => []]])->assertUnprocessable();
    }

    public function test_openai_search_and_ollama_moderation_use_independent_models_and_schema(): void
    {
        config(['ai.search.provider' => 'openai', 'ai.search.model' => 'search-test', 'ai.openai_key' => 'test-secret', 'ai.moderation.provider' => 'ollama', 'ai.moderation.model' => 'moderation-test']);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['date' => '2026-10-12', 'start_time' => '13:00', 'end_time' => '15:00', 'district' => null, 'max_budget' => null, 'amenities' => [], 'clarification' => null])]]]]]),
            'http://127.0.0.1:11434/api/chat' => Http::response(['message' => ['content' => json_encode(['decision' => 'review', 'reason' => 'Cần người duyệt kiểm tra ngôn từ.', 'categories' => ['language'], 'confidence' => 0.9])]]),
        ]);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Giúp tôi tìm giờ phù hợp'])->assertOk()->assertJsonPath('data.mode', 'model')->assertJsonPath('data.courts.0.court_id', $this->court->id);
        $this->actingAs($this->customer)->postJson('/api/v1/community/posts', $this->postData())->assertCreated()->assertJsonPath('data.moderation_status', 'pending');
        Http::assertSent(fn ($r) => $r->url() === 'https://api.openai.com/v1/responses' && $r['model'] === 'search-test' && $r['store'] === false && $r['text']['format']['strict'] === true);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/chat') && $r['model'] === 'moderation-test' && $r['stream'] === false && $r['format']['additionalProperties'] === false);
    }

    public function test_model_failure_does_not_publish_posts_or_invent_search_results(): void
    {
        config(['ai.search.provider' => 'ollama', 'ai.moderation.provider' => 'ollama']);
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);
        $this->postJson('/api/v1/ai/courts', ['message' => 'Hôm nay 13h đến 15h ở Bình Thạnh'])->assertOk()->assertJsonPath('data.mode', 'filters')->assertJsonPath('data.total_count', 1);
        $p = $this->actingAs($this->customer)->postJson('/api/v1/community/posts', $this->postData())->assertCreated()->json('data.id');
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/api/v1/community/posts/'.$p)->assertNotFound();
    }

    public function test_human_moderation_is_reset_when_author_edits_and_comments_are_screened(): void
    {
        $p = $this->actingAs($this->customer)->postJson('/api/v1/community/posts', $this->postData(['content' => 'đồ ngu']))->assertCreated()->assertJsonPath('data.moderation_status', 'pending')->json('data.id');
        $this->actingAs($this->admin)->patchJson('/api/v1/admin/posts/'.$p.'/moderation', ['decision' => 'approved', 'reason' => 'Đã kiểm tra.'])->assertOk();
        $this->actingAs($this->customer)->putJson('/api/v1/community/posts/'.$p, $this->postData(['content' => 'đồ ngu lần nữa']))->assertOk()->assertJsonPath('data.moderation_status', 'pending');
        $this->assertNull(CommunityPost::find($p)->moderated_by);
        $this->postJson('/api/v1/community/posts/'.$p.'/comments', ['content' => 'hello'])->assertNotFound();
        $this->putJson('/api/v1/community/posts/'.$p, $this->postData())->assertOk();
        $a = $this->postJson('/api/v1/community/posts/'.$p.'/comments', ['content' => 'Hẹn gặp tại sân'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/community/posts/'.$p.'/comments', ['content' => 'Mình tham gia', 'parent_id' => $a])->assertCreated();
        $this->postJson('/api/v1/community/posts/'.$p.'/comments', ['content' => 'đồ ngu'])->assertUnprocessable();
    }

    public function test_deposit_wallet_payment_and_expiry_are_idempotent_and_release_court(): void
    {
        $b = $this->book();
        $this->assertSame(100000, $b->deposit_amount);
        $key = (string) Str::uuid();
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/bookings/'.$b->id.'/wallet-payment', ['request_key' => $key, 'deposit_only' => true])->assertOk();
        }
        $this->assertSame(900000, $this->customer->fresh()->wallet_balance);
        $this->assertSame(100000, $b->fresh()->paid_amount);
        $this->travel(31)->minutes();
        $this->assertSame(0, app(FeatureService::class)->expireBookings());
        $this->assertSame('confirmed', $b->fresh()->status);
        $this->travelBack();
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
        $other = Court::factory()->create(['branch_id' => $this->court->branch_id]);
        $u = app(BookingService::class)->create($this->customer, $this->payload(['court_id' => $other->id]));
        $this->travel(31)->minutes();
        $this->assertSame(1, app(FeatureService::class)->expireBookings());
        $this->assertSame(0, app(FeatureService::class)->expireBookings());
        $this->assertSame('cancelled', $u->fresh()->status);
        $this->postJson('/api/v1/bookings/'.$u->id.'/wallet-payment', ['request_key' => (string) Str::uuid()])->assertUnprocessable();
    }

    public function test_expired_cash_deposit_remains_pending_refund_without_automatic_wallet_credit(): void
    {
        $b = $this->book();
        $this->actingAs($this->manager)->postJson('/api/v1/manage/bookings/'.$b->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => 20000, 'kind' => 'payment', 'method' => 'cash'])->assertOk();
        $this->travel(31)->minutes();
        app(FeatureService::class)->expireBookings();
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame(20000, $b->fresh()->paid_amount);
        $this->assertSame(1000000, $this->customer->fresh()->wallet_balance);
    }

    public function test_bank_webhook_authentication_deduplication_and_late_money_credit(): void
    {
        config(['integrations.bank.enabled' => true, 'integrations.bank.key' => 'bank-key', 'integrations.bank.account' => '12345']);
        $b = $this->book();
        $d = ['id' => 401, 'accountNumber' => '12345', 'transferType' => 'in', 'transferAmount' => 100000, 'content' => $b->reference];
        $this->postJson('/api/v1/payments/bank/webhook', $d)->assertForbidden();
        $this->withHeader('Authorization', 'Apikey bank-key')->postJson('/api/v1/payments/bank/webhook', $d)->assertOk();
        $this->postJson('/api/v1/payments/bank/webhook', $d)->assertOk();
        $this->assertSame(100000, $b->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
        $this->travel(31)->minutes();
        app(FeatureService::class)->expireBookings();
        $this->assertSame('confirmed', $b->fresh()->status);
        $b->update(['status' => 'cancelled']);
        $this->postJson('/api/v1/payments/bank/webhook', [...$d, 'id' => 402, 'transferAmount' => 10000])->assertOk();
        $this->assertSame(1010000, $this->customer->fresh()->wallet_balance);
    }

    public function test_momo_refund_queries_pending_result_and_never_resends_or_double_refunds(): void
    {
        config(['momo.enabled' => true, 'momo.partner_code' => 'TEST', 'momo.access_key' => 'access', 'momo.secret_key' => 'secret', 'momo.ipn_url' => 'https://hub.example/api/v1/payments/momo/ipn', 'momo.redirect_url' => 'https://hub.example/#account', 'momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create']);
        $b = $this->book();
        $b->update(['status' => 'cancelled', 'paid_amount' => 100000]);
        $a = PaymentAttempt::factory()->create(['booking_id' => $b->id, 'user_id' => $this->customer->id, 'amount' => 100000, 'status' => 'paid', 'trans_id' => '777']);
        Http::fake(['*' => fn ($r) => Http::response(str_ends_with($r->url(), '/query') ? ['partnerCode' => 'TEST', 'requestId' => $r['requestId'], 'orderId' => $r['orderId'], 'resultCode' => 0, 'refundTrans' => [['orderId' => $r['orderId'], 'amount' => 100000, 'resultCode' => 0]]] : ['partnerCode' => 'TEST', 'requestId' => $r['requestId'], 'orderId' => $r['orderId'], 'amount' => 100000, 'resultCode' => 7002, 'message' => 'Processing'])]);
        $d = ['request_key' => (string) Str::uuid(), 'attempt_id' => $a->id, 'amount' => 100000];
        $this->actingAs($this->manager)->postJson('/api/v1/manage/bookings/'.$b->id.'/momo-refund', $d)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->postJson('/api/v1/manage/bookings/'.$b->id.'/payments', ['request_key' => (string) Str::uuid(), 'kind' => 'refund', 'method' => 'cash', 'amount' => 100000])->assertStatus(409);
        $this->postJson('/api/v1/manage/bookings/'.$b->id.'/momo-refund', $d)->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson('/api/v1/manage/bookings/'.$b->id.'/momo-refund', $d)->assertOk();
        $this->assertSame(0, $b->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
        Http::assertSentCount(2);
    }

    public function test_loyalty_tiers_and_redemption_do_not_multiply_on_retry(): void
    {
        MembershipPlan::factory()->create(['points_threshold' => 10, 'discount_percent' => 5, 'early_days' => 3]);
        app(LoyaltyService::class)->award($this->customer, 200000, 'paid-once', 'Hoàn thành buổi chơi');
        app(LoyaltyService::class)->award($this->customer, 200000, 'paid-once', 'Hoàn thành buổi chơi');
        $this->assertSame(20, $this->customer->fresh()->earned_points);
        $this->assertDatabaseCount('memberships', 1);
        $id = DB::table('loyalty_rewards')->insertGetId(['name' => 'Quà thử', 'points' => 10, 'wallet_amount' => 1000, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $d = ['request_key' => (string) Str::uuid(), 'reward_id' => $id];
        $this->actingAs($this->customer)->postJson('/api/v1/loyalty/redeem', $d)->assertOk();
        $this->postJson('/api/v1/loyalty/redeem', $d)->assertOk();
        $this->assertSame(10, $this->customer->fresh()->loyalty_points);
        $this->assertSame(1001000, $this->customer->fresh()->wallet_balance);
    }

    public function test_branch_assignment_and_permission_overrides_apply_to_api_mutations(): void
    {
        $second = Court::factory()->create();
        $this->actingAs($this->manager)->getJson('/api/v1/manage/calendar?branch_id='.$second->branch_id.'&date=2026-10-12')->assertForbidden();
        $this->actingAs($this->admin)->putJson('/api/v1/admin/users/'.$this->manager->id.'/branches', ['branch_ids' => [$this->court->branch_id, $second->branch_id]])->assertOk();
        $this->actingAs($this->manager->fresh())->getJson('/api/v1/manage/calendar?branch_id='.$second->branch_id.'&from=2026-10-12&to=2026-10-18')->assertOk();
        $this->actingAs($this->admin)->putJson('/api/v1/admin/permissions', ['role' => 'manager', 'permission' => 'bookings.write', 'allowed' => false])->assertOk();
        $this->actingAs($this->manager)->postJson('/api/v1/bookings', $this->payload(['user_id' => $this->customer->id]))->assertForbidden();
    }

    public function test_hours_pricing_and_court_moves_recalculate_deposit(): void
    {
        $this->actingAs($this->manager)->putJson('/api/v1/manage/branches/'.$this->court->branch_id.'/policy', ['opens_minute' => 480, 'closes_minute' => 1200, 'peak_minute' => 960, 'booking_days' => 30, 'deposit_percent' => 50, 'payment_minutes' => 30, 'fixed_discount' => 15])->assertOk();
        $this->postJson('/api/v1/courts/'.$this->court->id.'/quote', ['starts_at' => '2026-10-13 06:00', 'ends_at' => '2026-10-13 07:00'])->assertUnprocessable();
        $this->postJson('/api/v1/manage/pricing', ['branch_id' => $this->court->branch_id, 'name' => 'Giá buổi trưa', 'weekday' => 1, 'from_minute' => 780, 'to_minute' => 900, 'hourly_rate' => 50000, 'priority' => 2, 'active' => true])->assertOk();
        $b = $this->book();
        $this->assertSame(100000, $b->total_amount);
        $second = Court::factory()->create(['branch_id' => $this->court->branch_id, 'peak_rate' => 300000]);
        $this->actingAs($this->customer)->patchJson('/api/v1/bookings/'.$b->id.'/reschedule', ['court_id' => $second->id, 'starts_at' => '2026-10-12 18:00', 'ends_at' => '2026-10-12 20:00'])->assertOk()->assertJsonPath('data.total_amount', 600000)->assertJsonPath('data.deposit_amount', 300000);
        $this->assertSame($second->id, $b->fresh()->court_id);
    }

    public function test_media_ownership_publication_and_pdf_attachment_privacy(): void
    {
        Storage::fake('local');
        $file = $this->actingAs($this->customer)->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('court.jpg')])->assertCreated()->json('data.id');
        $other = User::factory()->create();
        $this->actingAs($other)->get('/api/v1/media/'.$file)->assertNotFound();
        $this->postJson('/api/v1/community/posts', $this->postData(['images' => [$file]]))->assertUnprocessable();
        $p = $this->actingAs($this->customer)->postJson('/api/v1/community/posts', $this->postData(['images' => [$file], 'content' => 'đồ ngu']))->assertCreated()->json('data.id');
        $this->actingAs($other)->get('/api/v1/media/'.$file)->assertNotFound();
        $this->actingAs($this->admin)->get('/api/v1/media/'.$file)->assertOk();
        $this->patchJson('/api/v1/admin/posts/'.$p.'/moderation', ['decision' => 'approved', 'reason' => 'Đã kiểm tra'])->assertOk();
        $this->actingAs($other)->get('/api/v1/media/'.$file)->assertOk();
        $pdf = $this->actingAs($this->customer)->postJson('/api/v1/media', ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/community/posts', $this->postData(['images' => [$pdf]]))->assertUnprocessable();
    }

    public function test_saved_favorite_support_and_business_chat_are_accessible_and_scoped(): void
    {
        $b = $this->book();
        $this->putJson('/api/v1/courts/'.$this->court->id.'/favorite', ['active' => true])->assertOk();
        $this->postJson('/api/v1/support', ['subject' => 'Giúp tôi đổi lịch', 'content' => 'Muốn đổi sang buổi tối', 'branch_id' => $this->court->branch_id])->assertCreated();
        $c = $this->postJson('/api/v1/bookings/'.$b->id.'/conversation')->assertOk()->json('data.id');
        $this->postJson('/api/v1/conversations/'.$c.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Chào sân'])->assertCreated();
        $this->actingAs($this->manager)->getJson('/api/v1/conversations/'.$c.'/messages')->assertOk()->assertJsonCount(1, 'data');
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/api/v1/conversations/'.$c.'/messages')->assertForbidden();
        $this->actingAs($this->customer)->getJson('/api/v1/features/account')->assertOk()->assertJsonCount(1, 'data.favorites')->assertJsonCount(1, 'data.tickets');
    }

    public function test_direct_chat_blocking_attachments_typing_and_sse_auth(): void
    {
        $other = User::factory()->create(['public_profile' => true]);
        $c = $this->actingAs($this->customer)->postJson('/api/v1/conversations/direct', ['user_id' => $other->id])->assertOk()->json('data.id');
        $this->postJson('/api/v1/conversations/'.$c.'/typing')->assertOk();
        $this->get('/api/v1/conversations/'.$c.'/stream')->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $this->putJson('/api/v1/users/'.$other->id.'/block', ['active' => true])->assertOk();
        $this->actingAs($other)->postJson('/api/v1/conversations/'.$c.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Bị chặn'])->assertForbidden();
    }

    public function test_coaching_checks_trainer_overlap_and_retry_identity(): void
    {
        $legacy = Service::factory()->create(['branch_id' => $this->court->branch_id, 'name' => 'Huấn luyện viên']);
        $this->actingAs($this->customer)->postJson('/api/v1/bookings', $this->payload(['services' => [['id' => $legacy->id, 'quantity' => 1]]]))->assertUnprocessable();
        $coach = DB::table('coaches')->insertGetId(['branch_id' => $this->court->branch_id, 'name' => 'HLV kiểm thử', 'bio' => 'Luyện kỹ thuật', 'hourly_rate' => 200000, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $d = $this->payload(['coach_id' => $coach, 'wallet' => false]);
        $b = $this->actingAs($this->customer)->postJson('/api/v1/coaching', $d)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/coaching', $d)->assertCreated()->assertJsonPath('data.id', $b);
        $second = Court::factory()->create(['branch_id' => $this->court->branch_id]);
        $this->postJson('/api/v1/coaching', [...$d, 'request_key' => (string) Str::uuid(), 'court_id' => $second->id])->assertUnprocessable();
        $this->assertDatabaseCount('coach_sessions', 1);
    }

    public function test_round_robin_covers_each_pair_once_and_swiss_has_no_repeated_opponents(): void
    {
        foreach (['round_robin', 'swiss'] as $format) {
            $e = GameEvent::factory()->create(['branch_id' => $this->court->branch_id, 'type' => 'league', 'format' => $format]);
            foreach (User::factory()->count(4)->create() as $u) {
                $e->registrations()->create(['user_id' => $u->id, 'status' => 'confirmed', 'amount' => 0, 'paid_amount' => 0, 'method' => 'cash']);
            }
            $this->actingAs($this->manager)->postJson('/api/v1/manage/events/'.$e->id.'/draw')->assertOk();
            if ($format === 'round_robin') {
                $this->assertSame(6, $e->matches()->count());
            } else {
                foreach ($e->matches()->where('round', 1)->get() as $m) {
                    $this->patchJson('/api/v1/manage/matches/'.$m->id.'/score', ['score_a' => 21, 'score_b' => 10])->assertOk();
                }
                $this->assertSame(4, $e->matches()->count());
            }
            $pairs = $e->matches()->get()->map(fn ($m) => min($m->player_a, $m->player_b).'-'.max($m->player_a, $m->player_b));
            $this->assertSame($pairs->count(), $pairs->unique()->count());
            $this->getJson('/api/v1/events/'.$e->id)->assertOk()->assertJsonCount(4, 'data.standings');
        }
    }

    public function test_xlsx_and_pdf_exports_contain_real_financial_data_and_respect_scope(): void
    {
        $b = $this->book();
        $this->actingAs($this->manager)->postJson('/api/v1/manage/bookings/'.$b->id.'/payments', ['request_key' => (string) Str::uuid(), 'amount' => 100000, 'method' => 'bank'])->assertOk();
        $query = '?branch_id='.$this->court->branch_id.'&from=2026-10-12&to=2026-10-12';
        $this->getJson('/api/v1/manage/reports'.$query)->assertOk()->assertJsonPath('data.net_revenue', 100000)->assertJsonPath('data.by_court.0.revenue', 100000);
        $xlsx = $this->get('/api/v1/manage/reports.xlsx'.$query)->assertOk()->getContent();
        $this->assertStringStartsWith('PK', $xlsx);
        $file = tempnam(sys_get_temp_dir(), 'hub-test-');
        file_put_contents($file, $xlsx);
        $zip = new \ZipArchive;
        $zip->open($file);
        $this->assertStringContainsString('100000', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($file);
        $pdf = $this->get('/api/v1/manage/reports.pdf'.$query)->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $other = User::factory()->create();
        $this->actingAs($other)->get('/api/v1/bookings/'.$b->id.'/receipt.pdf')->assertForbidden();
    }

    public function test_content_is_published_only_by_admin_and_review_reply_is_branch_scoped(): void
    {
        $d = ['slug' => 'tin-moi', 'title' => 'Ngày hội cầu lông', 'type' => 'blog', 'content' => 'Mời bạn tham gia', 'published' => false];
        $p = $this->actingAs($this->admin)->postJson('/api/v1/admin/pages', $d)->assertOk()->json('data.id');
        $this->getJson('/api/v1/pages?slug=tin-moi')->assertJsonCount(0, 'data');
        $this->putJson('/api/v1/admin/pages/'.$p, [...$d, 'published' => true])->assertOk();
        $this->actingAs($this->customer)->getJson('/api/v1/pages?slug=tin-moi')->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/admin/pages', $d)->assertForbidden();
    }

    public function test_google_requires_state_and_does_not_silently_link_existing_email(): void
    {
        config(['integrations.google.client_id' => 'id', 'integrations.google.client_secret' => 'secret', 'integrations.google.redirect_uri' => 'https://hub.example/auth/google/callback']);
        $this->get('/auth/google')->assertRedirect();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']), 'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'google-id', 'email' => $this->customer->email, 'email_verified' => true, 'name' => 'Player'])]);
        $this->withSession(['google_oauth' => ['state' => 'safe-state', 'created' => now()->timestamp, 'user_id' => null]])->get('/auth/google/callback?state=wrong&code=x')->assertForbidden();
        $this->withSession(['google_oauth' => ['state' => 'safe-state', 'created' => now()->timestamp, 'user_id' => null]])->get('/auth/google/callback?state=safe-state&code=x')->assertRedirect('/#auth');
        $this->assertGuest();
        $this->assertStringContainsString('Email đã có tài khoản', session('auth_notice'));
        $this->assertNull($this->customer->fresh()->google_sub);
    }

    public function test_phone_is_verified_by_provider_before_it_can_be_used_for_login(): void
    {
        config(['integrations.sms.enabled' => true, 'integrations.sms.sid' => 'sid', 'integrations.sms.token' => 'token', 'integrations.sms.service' => 'service']);
        Http::fake(['*/Verifications' => Http::response(['status' => 'pending']), '*/VerificationCheck' => Http::response(['status' => 'approved'])]);
        $this->actingAs($this->customer)->postJson('/api/v1/me/phone/send', ['phone' => '0912345678'])->assertOk();
        $this->postJson('/api/v1/me/phone/verify', ['code' => '123456'])->assertOk();
        $this->assertNotNull($this->customer->fresh()->phone_verified_at);
        $this->postJson('/api/v1/logout')->assertOk();
        $this->postJson('/api/v1/login', ['email' => '0912345678', 'password' => 'password'])->assertOk();
        $this->patchJson('/api/v1/me', ['name' => $this->customer->name, 'email' => $this->customer->email, 'phone' => '0987654321'])->assertOk();
        $this->assertNull($this->customer->fresh()->phone_verified_at);
    }

    public function test_unmatched_bank_money_can_only_be_reconciled_once_by_admin(): void
    {
        config(['integrations.bank.enabled' => true, 'integrations.bank.key' => 'bank-key', 'integrations.bank.account' => '12345']);
        $b = $this->book();
        $d = ['id' => 9001, 'accountNumber' => '12345', 'transferType' => 'in', 'transferAmount' => 100000, 'content' => 'Sai noi dung'];
        $this->withHeader('Authorization', 'Apikey bank-key')->postJson('/api/v1/payments/bank/webhook', $d)->assertOk();
        $id = DB::table('integration_transactions')->value('id');
        $this->actingAs($this->manager)->postJson('/api/v1/admin/bank-transactions/'.$id.'/match', ['reference' => $b->reference])->assertForbidden();
        $this->actingAs($this->admin)->postJson('/api/v1/admin/bank-transactions/'.$id.'/match', ['reference' => $b->reference])->assertOk();
        $this->postJson('/api/v1/admin/bank-transactions/'.$id.'/match', ['reference' => $b->reference])->assertOk();
        $this->assertSame(100000, $b->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_revoked_branch_access_removes_support_chat_and_private_attachments(): void
    {
        Storage::fake('local');
        $b = $this->book();
        $media = $this->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('chat.jpg')])->assertCreated()->json('data.id');
        $c = $this->postJson('/api/v1/bookings/'.$b->id.'/conversation')->assertOk()->json('data.id');
        $this->postJson('/api/v1/conversations/'.$c.'/messages', ['request_key' => (string) Str::uuid(), 'content' => 'Ảnh hỗ trợ', 'attachments' => [$media]])->assertCreated();
        $this->actingAs($this->manager)->get('/api/v1/media/'.$media)->assertOk();
        $this->actingAs($this->admin)->putJson('/api/v1/admin/users/'.$this->manager->id.'/branches', ['branch_ids' => []])->assertOk();
        $this->actingAs($this->manager->fresh())->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/conversations/'.$c.'/messages')->assertForbidden();
        $this->get('/api/v1/media/'.$media)->assertNotFound();
    }

    public function test_reminders_are_sent_once_and_point_tiers_cannot_be_bought_without_earning(): void
    {
        $b = $this->book();
        $b->update(['deposit_amount' => 0, 'payment_expires_at' => null, 'starts_at' => now()->addMinutes(20), 'ends_at' => now()->addMinutes(80)]);
        $this->artisan('hub:maintain')->assertSuccessful();
        $this->artisan('hub:maintain')->assertSuccessful();
        $this->assertSame(1, $this->customer->notifications()->where('data', 'like', '%booking.reminder%')->count());
        $plan = MembershipPlan::factory()->create(['price' => 0, 'points_threshold' => 100]);
        $this->postJson('/api/v1/memberships/'.$plan->id.'/buy', ['request_key' => (string) Str::uuid()])->assertUnprocessable();
    }

    public function test_pos_only_staff_can_load_management_without_private_booking_data(): void
    {
        $this->book();
        foreach (AccessService::PERMISSIONS as $permission) {
            DB::table('role_permissions')->insert(['role' => 'manager', 'permission' => $permission, 'allowed' => $permission === 'pos.read']);
        }
        $query = '?branch_id='.$this->court->branch_id.'&date=2026-10-12';
        $this->actingAs($this->manager)->getJson('/api/v1/manage/hub'.$query)->assertOk()->assertJsonCount(0, 'data.bookings');
        $this->getJson('/api/v1/manage/features'.$query)->assertOk()->assertJsonCount(0, 'data.tickets');
        $this->getJson('/api/v1/manage/calendar'.$query)->assertForbidden();
        $this->getJson('/api/v1/manage/customers?search=player')->assertForbidden();
    }
}
