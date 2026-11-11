<?php

namespace Tests\Feature;

use App\BackupService;
use App\BookingReminderService;
use App\CourtSearchService;
use App\MediaRetention;
use App\Models\Booking;
use App\Models\CommunityPost;
use App\Models\Court;
use App\Models\User;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReliabilityAndDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_rotates_only_default_demo_passwords_and_never_prints_secrets(): void
    {
        Storage::fake('local');
        $weak = User::factory()->create(['email' => 'admin@badminton.test', 'role' => 'admin', 'password' => 'Demo@12345']);
        $personal = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Demo@12345']);
        $strong = User::factory()->create(['email' => 'manager@badminton.test', 'password' => 'AlreadyStrong@2026']);
        $file = Storage::disk('local')->path('credentials.txt');
        $this->artisan('hub:secure-demo-accounts', ['--force' => true, '--output' => $file])->assertSuccessful();
        $this->assertFalse(Hash::check('Demo@12345', $weak->fresh()->password));
        $this->assertTrue(Hash::check('Demo@12345', $personal->fresh()->password));
        $this->assertTrue(Hash::check('AlreadyStrong@2026', $strong->fresh()->password));
        $this->assertStringContainsString('admin@badminton.test', Storage::disk('local')->get('credentials.txt'));
        $this->assertStringNotContainsString('Demo@12345', Storage::disk('local')->get('credentials.txt'));
    }

    public function test_backup_is_encrypted_password_protected_and_inspectable(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'Strong@Password123']);
        $this->actingAs($admin)->postJson('/api/v1/admin/backups', ['password' => 'wrong'])->assertUnprocessable();
        $reference = $this->postJson('/api/v1/admin/backups', ['password' => 'Strong@Password123'])->assertCreated()->json('data.reference');
        $row = DB::table('backup_records')->where('reference', $reference)->first();
        $this->assertStringNotContainsString($admin->email, Storage::disk('local')->get($row->path));
        $this->postJson('/api/v1/admin/backups/'.$reference.'/inspect', ['password' => 'Strong@Password123'])->assertOk()->assertJsonPath('data.tables.users', 1);
        $this->postJson('/api/v1/admin/backups/'.$reference.'/restore', ['password' => 'Strong@Password123', 'confirmation' => 'KHOI PHUC CSDL'])->assertStatus(409);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/backups')->assertForbidden();
    }

    public function test_corrupt_backup_is_rejected_without_touching_data(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $record = app(BackupService::class)->create($admin);
        Storage::disk('local')->put($record->path, 'tampered');
        $this->expectException(HttpException::class);
        try {
            app(BackupService::class)->inspect($record->reference);
        } finally {
            $this->assertDatabaseHas('users', ['id' => $admin->id]);
        }
    }

    public function test_restore_in_test_database_replaces_rows_and_keeps_a_safety_copy(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $original = User::factory()->create(['name' => 'Nguyễn Minh Anh']);
        $service = app(BackupService::class);
        $snapshot = $service->create($admin);
        $original->update(['name' => 'Tên đã đổi']);
        $added = User::factory()->create();
        $mode = \Mockery::mock(MaintenanceMode::class);
        $mode->shouldReceive('active')->andReturn(true);
        $this->app->instance(MaintenanceMode::class, $mode);
        $result = $this->actingAs($admin)->postJson('/api/v1/admin/backups/'.$snapshot->reference.'/restore', ['password' => 'password', 'confirmation' => 'KHOI PHUC CSDL'])->assertOk()->json('data');
        $this->assertGuest();
        $this->assertNotEmpty($result['safety_backup']);
        $this->assertSame('Nguyễn Minh Anh', $original->fresh()->name);
        $this->assertDatabaseMissing('users', ['id' => $added->id]);
        $this->assertDatabaseCount('backup_records', 2);
    }

    public function test_quarantine_preserves_referenced_media_and_can_restore_unused_file(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $ids = [];
        foreach (['unused.jpg', 'post.jpg'] as $path) {
            Storage::disk('local')->put('uploads/'.$path, 'image');
            $ids[] = DB::table('media_uploads')->insertGetId(['user_id' => $owner->id, 'path' => 'uploads/'.$path, 'name' => $path, 'mime' => 'image/jpeg', 'size' => 5, 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)]);
        }
        CommunityPost::factory()->create(['user_id' => $owner->id, 'images' => [$ids[1]]]);
        $service = app(MediaRetention::class);
        $this->assertSame(1, $service->archiveUnused());
        Storage::disk('local')->assertExists('uploads/post.jpg');
        Storage::disk('local')->assertMissing('uploads/unused.jpg');
        $archive = DB::table('media_archives')->where('media_upload_id', $ids[0])->first();
        $service->restore($archive->id);
        $service->restore($archive->id);
        Storage::disk('local')->assertExists('uploads/unused.jpg');
    }

    public function test_reminders_require_opt_in_and_verified_contact_and_send_once(): void
    {
        config(['integrations.email' => ['mailer' => 'smtp', 'host' => 'smtp.example.test', 'port' => 587, 'from_address' => 'hub@example.test', 'username' => 'test', 'password' => 'test']]);
        $booking = Booking::factory()->create(['starts_at' => now()->addMinutes(20), 'ends_at' => now()->addMinutes(80), 'deposit_amount' => 50000, 'paid_amount' => 50000]);
        $service = app(BookingReminderService::class);
        $this->assertSame(0, $service->sendDue());
        $booking->user->forceFill(['email_reminders' => true, 'email_verified_at' => null])->save();
        $this->assertSame(0, $service->sendDue());
        $booking->user->forceFill(['email_verified_at' => now()])->save();
        Mail::shouldReceive('raw')->once()->andReturnNull();
        $this->assertSame(1, $service->sendDue());
        $this->assertSame(0, $service->sendDue());
        $this->assertDatabaseHas('booking_deliveries', ['booking_id' => $booking->id, 'channel' => 'email', 'status' => 'sent', 'attempts' => 1]);
    }

    public function test_subpath_uses_only_trusted_proxy_headers(): void
    {
        config(['app.asset_url' => null]);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('http://localhost/', ['X-Forwarded-Prefix' => '/badminton', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'pdh2203.id.vn'])->assertOk()->assertSee('content="/badminton"', false)->assertSee('href="/badminton/"', false)->assertSee('src="/badminton/build/assets/', false);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('http://localhost/', ['X-Forwarded-Prefix' => '/fake'])->assertOk()->assertDontSee('content="/fake"', false)->assertSee('src="/build/assets/', false);
    }

    public function test_ai_alternatives_do_not_mix_other_wards_into_exact_results(): void
    {
        config(['ai.search.provider' => 'local']);
        $this->travelTo(now()->startOfDay()->addHours(8));
        $court = Court::factory()->create();
        $court->branch->update(['ward' => 'Tân Phú']);
        $other = Court::factory()->create();
        $other->branch->update(['ward' => 'Tân Phú Trung']);
        Booking::factory()->create(['court_id' => $court->id, 'starts_at' => now()->setTime(13, 0), 'ends_at' => now()->setTime(14, 0), 'paid_amount' => 100000]);
        $result = app(CourtSearchService::class)->search('Hôm nay 13h tới 14h phường Tân Phú');
        $this->assertSame([], $result['courts']);
        $this->assertNotEmpty($result['alternatives']);
        foreach ($result['alternatives'] as $alternative) {
            $this->assertSame($court->id, $alternative['court_id']);
            $this->assertSame('Tân Phú', $alternative['ward']);
            $this->assertNotEmpty($alternative['reason']);
        }
    }
}
