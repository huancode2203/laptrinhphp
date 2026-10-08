<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_preserves_role_and_requires_password_for_email_change(): void
    {
        $user = User::factory()->create(['password' => 'Test@12345', 'email_verified_at' => now()]);
        $data = ['name' => 'Tên mới', 'email' => $user->email, 'phone' => '0901234567', 'role' => 'admin', 'branch_id' => 1];
        $this->actingAs($user)->patchJson('/api/v1/me', $data)->assertOk()->assertJsonPath('data.role', 'customer')->assertJsonMissingPath('data.password');
        $this->assertNull($user->fresh()->branch_id);
        $data['email'] = 'changed@example.test';
        $this->patchJson('/api/v1/me', $data)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->patchJson('/api/v1/me', $data + ['current_password' => 'Test@12345'])->assertOk()->assertJsonPath('data.email', 'changed@example.test');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.updated', 'user_id' => $user->id]);
    }

    public function test_duplicate_email_and_unauthenticated_profile_updates_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $data = ['name' => 'Tên', 'email' => $other->email];
        $this->patchJson('/api/v1/me', $data)->assertUnauthorized();
        $this->actingAs($user)->patchJson('/api/v1/me', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNotSame($other->email, $user->fresh()->email);
    }

    public function test_password_change_checks_old_password_and_removes_only_other_sessions_of_this_user(): void
    {
        $user = User::factory()->create(['password' => 'Test@12345']);
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'different-user', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $data = ['current_password' => 'wrong', 'password' => 'New@12345', 'password_confirmation' => 'New@12345'];
        $this->actingAs($user)->putJson('/api/v1/me/password', $data)->assertUnprocessable();
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $data['current_password'] = 'Test@12345';
        $this->putJson('/api/v1/me/password', $data)->assertOk();
        $this->assertTrue(Hash::check('New@12345', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'different-user']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.changed', 'user_id' => $user->id]);
        $details = DB::table('audit_logs')->where('action', 'password.changed')->where('user_id', $user->id)->value('details');
        $this->assertSame([], json_decode($details, true, 512, JSON_THROW_ON_ERROR));
        $this->postJson('/api/v1/logout')->assertOk();
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'Test@12345'])->assertUnprocessable();
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'New@12345'])->assertOk();
    }

    public function test_notification_reading_is_private_and_read_all_preserves_other_user_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->notify(new HubNotification(['title' => 'Của tôi', 'body' => 'Nội dung']));
        $other->notify(new HubNotification(['title' => 'Riêng tư', 'body' => 'Của người khác']));
        $mine = $user->notifications()->firstOrFail();
        $theirs = $other->notifications()->firstOrFail();
        $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'data.data')->assertJsonMissing(['title' => 'Riêng tư']);
        $this->patchJson('/api/v1/notifications/'.$theirs->id.'/read')->assertNotFound();
        $this->patchJson('/api/v1/notifications/'.$mine->id.'/read')->assertOk();
        $this->getJson('/api/v1/notifications?unread=1')->assertOk()->assertJsonCount(0, 'data.data');
        $user->notify(new HubNotification(['title' => 'Mới']));
        $this->patchJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_retrying_booking_and_payment_creates_exactly_one_notification_per_action(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(9, 0));
        $court = Court::factory()->create();
        $customer = User::factory()->create();
        $manager = User::factory()->create(['role' => 'manager', 'branch_id' => $court->branch_id]);
        $data = ['request_key' => (string) Str::uuid(), 'court_id' => $court->id, 'starts_at' => '2026-10-12 10:00', 'ends_at' => '2026-10-12 11:00'];
        $id = $this->actingAs($customer)->postJson('/api/v1/bookings', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/bookings', $data)->assertOk();
        $this->assertSame(1, $customer->notifications()->count());
        $payment = ['request_key' => (string) Str::uuid(), 'amount' => 100000, 'method' => 'cash'];
        $this->actingAs($manager)->postJson('/api/v1/manage/bookings/'.$id.'/payments', $payment)->assertOk();
        $this->postJson('/api/v1/manage/bookings/'.$id.'/payments', $payment)->assertOk();
        $this->assertSame(2, $customer->notifications()->count());
        $this->patchJson('/api/v1/bookings/'.$id.'/status', ['status' => 'cancelled'])->assertOk();
        $payment['request_key'] = (string) Str::uuid();
        $payment['kind'] = 'refund';
        $this->postJson('/api/v1/manage/bookings/'.$id.'/payments', $payment)->assertOk();
        $this->assertSame(4, $customer->notifications()->count());
        $this->assertSame(0, Booking::findOrFail($id)->paid_amount);
    }
}
