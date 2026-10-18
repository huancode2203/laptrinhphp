<?php

namespace Tests\Feature;

use App\AuditPresenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_readable_labels_and_money_without_raw_json_in_the_summary(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        DB::table('audit_logs')->insert(['user_id' => $user->id, 'action' => 'booking.payment', 'subject_type' => 'booking', 'subject_id' => 12, 'details' => json_encode(['amount' => 120000, 'method' => 'bank', 'request_key' => 'internal-key']), 'created_at' => now()]);
        $row = $this->actingAs($user)->getJson('/api/v1/admin/history/audits')->assertOk()->json('data.data.0');
        $this->assertSame('Ghi nhận thu tiền sân', $row['action_label']);
        $this->assertStringContainsString('120.000 đ', $row['summary']);
        $this->assertStringContainsString('Chuyển khoản tại quầy', $row['summary']);
        $this->assertStringNotContainsString('internal-key', $row['summary']);
        $this->assertStringNotContainsString('{', $row['summary']);
    }

    public function test_unknown_or_corrupt_legacy_details_have_a_safe_description_without_leaking_keys(): void
    {
        $row = app(AuditPresenter::class)->present((object) ['action' => 'system.changed', 'subject_type' => 'integration', 'subject_id' => 0, 'details' => 'not-json']);
        $this->assertNotEmpty($row->summary);
        $row = app(AuditPresenter::class)->present((object) ['action' => 'payos.settings_updated', 'subject_type' => 'integration', 'subject_id' => 0, 'details' => json_encode(['api_key' => 'private-secret', 'checksum_key' => 'secret-two'])]);
        $this->assertStringNotContainsString('private-secret', $row->summary);
        $this->assertStringNotContainsString('secret-two', $row->summary);
    }

    public function test_reschedule_and_catalog_details_explain_dates_prices_and_previous_values(): void
    {
        $row = app(AuditPresenter::class)->present((object) ['action' => 'booking.rescheduled', 'subject_type' => 'booking', 'subject_id' => 8, 'details' => json_encode(['starts_at' => '2026-10-20T13:00:00+07:00', 'total_amount' => 200000, 'previous' => ['starts_at' => '2026-10-19T13:00:00+07:00', 'total_amount' => 180000, 'api_key' => 'private-secret']])]);
        $this->assertStringContainsString('13:00 20/10/2026', $row->summary);
        $this->assertStringContainsString('Trước thay đổi:', $row->summary);
        $this->assertStringContainsString('180.000 đ', $row->summary);
        $this->assertStringNotContainsString('private-secret', $row->summary);
        $row = app(AuditPresenter::class)->present((object) ['action' => 'membership_plan.saved', 'subject_type' => 'membership_plan', 'subject_id' => 9, 'details' => json_encode(['name' => 'Gold', 'price' => 300000, 'active' => false])]);
        $this->assertSame('Lưu gói hội viên', $row->action_label);
        $this->assertStringContainsString('Giá: 300.000 đ', $row->summary);
        $this->assertStringContainsString('Đang hoạt động: Không', $row->summary);
    }
}
