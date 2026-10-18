<?php

namespace Tests\Feature;

use App\EmailSettings;
use App\IntegrationStatus;
use App\Mail\RecoveryCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['integrations.email' => ['mailer' => 'log'], 'mail.default' => 'log']);
        Http::preventStrayRequests();
    }

    private function values(array $overrides = []): array
    {
        return [...['email' => 'sender@gmail.com', 'name' => 'Badminton Hub', 'password' => 'abcd efgh ijkl mnop'], ...$overrides];
    }

    public function test_only_admin_can_read_save_and_test_email_configuration(): void
    {
        $this->getJson('/api/v1/admin/integrations/email')->assertUnauthorized();
        foreach (['customer', 'manager', 'staff', 'cashier'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/v1/admin/integrations/email')->assertForbidden();
            $this->putJson('/api/v1/admin/integrations/email', $this->values())->assertForbidden();
            $this->postJson('/api/v1/admin/integrations/email/test')->assertForbidden();
        }
        $this->assertDatabaseMissing('integration_settings', ['key' => 'email_smtp']);
    }

    public function test_saves_encrypted_gmail_and_applies_it_without_exposing_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->putJson('/api/v1/admin/integrations/email', $this->values());
        $response->assertOk()->assertJsonPath('data.configured', true)->assertJsonPath('data.source', 'database')->assertJsonPath('data.last_test_at', null)->assertDontSee('abcdefghijklmnop');
        $this->assertStringNotContainsString('abcdefghijklmnop', DB::table('integration_settings')->where('key', 'email_smtp')->value('value'));
        $this->assertSame('abcdefghijklmnop', app(EmailSettings::class)->credentials()['password']);
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertTrue(app(IntegrationStatus::class)->emailEnabled());
        $this->getJson('/api/v1/admin/integrations')->assertOk()->assertDontSee('abcdefghijklmnop');
        $this->getJson('/api/v1/hub')->assertOk()->assertDontSee('abcdefghijklmnop')->assertJsonPath('data.demo.email', false);
    }

    public function test_password_is_required_for_new_or_changed_sender_and_never_replaced_by_a_blank_value(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/v1/admin/integrations/email', $this->values(['password' => '']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/v1/admin/integrations/email', $this->values())->assertOk();
        $this->putJson('/api/v1/admin/integrations/email', $this->values(['password' => '', 'name' => 'Hẹn nhau trên sân']))->assertOk();
        $this->assertSame('abcdefghijklmnop', app(EmailSettings::class)->credentials()['password']);
        $this->putJson('/api/v1/admin/integrations/email', $this->values(['password' => '', 'email' => 'other@gmail.com']))->assertUnprocessable();
        $this->putJson('/api/v1/admin/integrations/email', $this->values(['password' => 'short']))->assertUnprocessable();
        $this->assertSame('sender@gmail.com', app(EmailSettings::class)->credentials()['username']);
    }

    public function test_test_email_only_goes_to_the_configured_sender_and_is_marked_after_acceptance(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/v1/admin/integrations/email', $this->values())->assertOk();
        Mail::shouldReceive('purge')->once()->with('smtp');
        Mail::shouldReceive('raw')->once()->withArgs(function (string $body, callable $callback): bool {
            $message = new Message(new Email);
            $callback($message);
            $this->assertSame('sender@gmail.com', $message->getSymfonyMessage()->getTo()[0]->getAddress());

            return str_contains($body, 'Badminton Hub');
        });
        $this->postJson('/api/v1/admin/integrations/email/test', ['to' => 'unrequested@example.test'])->assertOk()->assertJsonPath('data.configured', true)->assertDontSee('abcdefghijklmnop');
        $this->assertNotNull(app(EmailSettings::class)->status()['last_test_at']);
        app(EmailSettings::class)->saveGmail($this->values(['password' => 'ponmlkjihgfedcba']));
        $this->assertNull(app(EmailSettings::class)->status()['last_test_at']);
    }

    public function test_failure_is_sanitized_and_does_not_mark_email_as_tested(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/v1/admin/integrations/email/test')->assertUnprocessable();
        $this->putJson('/api/v1/admin/integrations/email', $this->values())->assertOk();
        Mail::shouldReceive('purge')->once();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Private error abcdefghijklmnop'));
        $this->postJson('/api/v1/admin/integrations/email/test')->assertStatus(503)->assertDontSee('abcdefghijklmnop');
        $this->assertNull(app(EmailSettings::class)->status()['last_test_at']);
    }

    public function test_saved_smtp_is_used_for_actual_recovery_mail_without_returning_a_demo_code(): void
    {
        app(EmailSettings::class)->saveGmail($this->values());
        config(['demo.enabled' => true]);
        $user = User::factory()->create();
        Mail::fake();
        $this->postJson('/api/v1/recovery/send', ['email' => $user->email])->assertOk()->assertJsonMissingPath('demo_code');
        Mail::assertSent(RecoveryCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertDatabaseHas('auth_codes', ['user_id' => $user->id, 'demo' => false]);
    }

    public function test_environment_configuration_has_priority_and_cannot_be_overwritten_from_the_form(): void
    {
        app(EmailSettings::class)->saveGmail($this->values());
        config(['integrations.email' => ['mailer' => 'smtp', 'host' => 'smtp.gmail.com', 'port' => 465, 'scheme' => 'smtps', 'username' => 'environment@gmail.com', 'password' => 'private-environment-password', 'from_address' => 'environment@gmail.com', 'from_name' => 'Configured sender']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/integrations/email')->assertOk()->assertJsonPath('data.source', 'environment')->assertJsonPath('data.sender', 'environment@gmail.com')->assertDontSee('private-environment-password');
        $this->putJson('/api/v1/admin/integrations/email', $this->values())->assertUnprocessable();
        $this->assertTrue(app(EmailSettings::class)->apply());
        $this->assertSame(465, config('mail.mailers.smtp.port'));
    }

    public function test_recovery_email_has_html_and_plain_text_content_with_the_code(): void
    {
        $mail = new RecoveryCodeMail('123456');
        $this->assertStringContainsString('123456', $mail->render());
        $this->assertSame('mail.recovery', $mail->build()->textView);

        Http::assertNothingSent();
    }
}
