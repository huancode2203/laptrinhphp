<?php

namespace Tests\Feature;

use App\FirebaseAuthService;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey = '';

    private array $claims;

    protected function setUp(): void
    {
        parent::setUp();
        config(['firebase' => ['apiKey' => 'AIza'.str_repeat('a', 35), 'authDomain' => 'hub-test.firebaseapp.com', 'projectId' => 'hub-test', 'appId' => '1:123:web:abc']]);
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $configFile = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($configFile)) {
            $options['config'] = $configFile;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $this->privateKey, null, $options);
        Cache::put('firebase.signing-certificates', ['key-1' => openssl_pkey_get_details($key)['key']], 3600);
        $this->claims = ['iss' => 'https://securetoken.google.com/hub-test', 'aud' => 'hub-test', 'sub' => 'firebase-user-1', 'iat' => time() - 5, 'exp' => time() + 3600, 'auth_time' => time() - 5, 'email' => 'new-google@example.test', 'email_verified' => true, 'name' => 'Nguyễn Minh Khôi', 'firebase' => ['sign_in_provider' => 'google.com', 'identities' => ['google.com' => ['google-user-1']]]];
        Http::preventStrayRequests();
        $this->lookup();
    }

    private function lookup(array $changes = [], int $status = 200): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://identitytoolkit.googleapis.com/*' => Http::response(['users' => [array_replace(['localId' => 'firebase-user-1', 'email' => 'new-google@example.test', 'emailVerified' => true, 'providerUserInfo' => [['providerId' => 'google.com', 'rawId' => 'google-user-1']]], $changes)]], $status)]);
    }

    private function token(array $changes = [], ?string $key = null): string
    {
        return JWT::encode(array_replace($this->claims, $changes), $key ?? $this->privateKey, 'RS256', 'key-1');
    }

    public function test_verified_google_creates_a_customer_and_does_not_expose_provider_identifiers(): void
    {
        $response = $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token(), 'role' => 'admin']);
        $response->assertOk()->assertJsonPath('data.name', 'Nguyễn Minh Khôi')->assertJsonPath('data.role', 'customer')->assertJsonPath('data.google_connected', true);
        $this->assertAuthenticated();
        $this->assertArrayNotHasKey('firebase_uid', $response->json('data'));
        $this->assertArrayNotHasKey('google_sub', $response->json('data'));
        $this->assertDatabaseHas('users', ['firebase_uid' => 'firebase-user-1', 'google_sub' => 'google-user-1']);
    }

    public function test_revoked_authentication_is_rejected_and_rotated_certificates_are_refreshed(): void
    {
        $this->lookup(['validSince' => (string) time()]);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertUnprocessable();
        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->lookup();
        $publicKey = Cache::get('firebase.signing-certificates')['key-1'];
        Cache::put('firebase.signing-certificates', ['retired-key' => $publicKey]);
        Http::fake([FirebaseAuthService::CERT_URL => Http::response(['key-1' => $publicKey], 200, ['Cache-Control' => 'public, max-age=1200'])]);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertOk();
        Http::assertSent(fn ($request) => $request->url() === FirebaseAuthService::CERT_URL);
        $this->assertArrayHasKey('key-1', Cache::get('firebase.signing-certificates'));
    }

    public function test_email_collision_requires_explicit_link_without_taking_over_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'new-google@example.test', 'role' => 'admin']);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertStatus(409);
        $this->assertGuest();
        $this->assertNull($user->fresh()->firebase_uid);
        $this->actingAs($user)->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.role', 'admin');
        $this->postJson('/api/v1/logout')->assertOk();
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.role', 'admin');
        $this->assertSame(1, User::count());
    }

    public function test_existing_oauth_identity_migrates_to_firebase_without_changing_email_or_role(): void
    {
        $user = User::factory()->create(['email' => 'kept@example.test', 'role' => 'manager', 'google_sub' => 'google-user-1']);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.email', 'kept@example.test')->assertJsonPath('data.role', 'manager');
        $this->assertSame('firebase-user-1', $user->fresh()->firebase_uid);
    }

    public function test_link_cannot_steal_another_users_google_identity_or_use_an_old_authentication(): void
    {
        User::factory()->create(['google_sub' => 'google-user-1', 'firebase_uid' => 'firebase-user-1']);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertUnprocessable();
        $this->assertNull($user->fresh()->google_sub);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token(['auth_time' => time() - 601])])->assertUnprocessable();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_signature_wrong_project_expiry_provider_and_unverified_email_are_rejected(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $configFile = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($configFile)) {
            $options['config'] = $configFile;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $otherKey, null, $options);
        $tokens = [$this->token([], $otherKey), $this->token(['aud' => 'other-project']), $this->token(['iss' => 'https://securetoken.google.com/other-project']), $this->token(['exp' => time() - 20]), $this->token(['auth_time' => time() + 100]), $this->token(['email_verified' => false]), $this->token(['firebase' => ['sign_in_provider' => 'password']]), 'invalid.token.value'];
        foreach ($tokens as $i => $token) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)]);
            $this->postJson('/api/v1/auth/firebase', ['id_token' => $token])->assertUnprocessable();
            $this->assertGuest();
        }
        $this->assertSame(0, User::count());
        Http::assertNothingSent();
    }

    public function test_firebase_lookup_and_local_disabled_accounts_are_rejected(): void
    {
        $this->lookup(['disabled' => true]);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertUnprocessable();
        $this->lookup();
        User::factory()->create(['google_sub' => 'google-user-1', 'active' => false]);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertForbidden();
        $this->assertGuest();
    }

    public function test_outage_and_missing_configuration_never_fall_back_to_demo_login(): void
    {
        $this->lookup([], 503);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertStatus(503);
        config(['firebase' => array_fill_keys(['apiKey', 'projectId', 'authDomain', 'appId'], null)]);
        $this->postJson('/api/v1/auth/firebase', ['id_token' => $this->token()])->assertStatus(503);
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_only_admin_can_save_public_web_config_and_environment_configuration_takes_precedence(): void
    {
        $body = config('firebase');
        $this->actingAs(User::factory()->create())->putJson('/api/v1/admin/integrations/firebase', $body)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/v1/admin/integrations/firebase', $body)->assertOk();
        $this->putJson('/api/v1/admin/integrations/firebase', array_replace($body, ['authDomain' => 'https://evil.invalid/script']))->assertUnprocessable();
        $this->assertSame('hub-test', app(FirebaseAuthService::class)->webConfig()['projectId']);
        config(['firebase.projectId' => 'env-project']);
        $this->assertSame('env-project', app(FirebaseAuthService::class)->webConfig()['projectId']);
        config(['firebase' => array_fill_keys(array_keys($body), null)]);
        $this->assertSame('hub-test', app(FirebaseAuthService::class)->webConfig()['projectId']);
        $this->getJson('/api/v1/hub')->assertOk()->assertJsonPath('data.google_enabled', true)->assertJsonPath('data.firebase.projectId', 'hub-test');
    }
}
