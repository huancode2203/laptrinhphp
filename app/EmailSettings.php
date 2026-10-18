<?php

namespace App;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailSettings
{
    public function credentials(): array
    {
        $environment = config('integrations.email', []);
        if (($environment['mailer'] ?? '') === 'smtp') {
            return $environment;
        }

        return $this->stored();
    }

    public function ready(): bool
    {
        $values = $this->credentials();

        return ! empty($values['host']) && filter_var($values['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) !== false
            && filter_var($values['from_address'] ?? '', FILTER_VALIDATE_EMAIL)
            && (! empty($values['username']) === ! empty($values['password']))
            && (($values['host'] ?? '') !== 'smtp.gmail.com' || (! empty($values['username']) && ! empty($values['password'])));
    }

    public function apply(): bool
    {
        if (! $this->ready()) {
            return false;
        }
        $values = $this->credentials();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport' => 'smtp', 'scheme' => $values['scheme'] ?? 'smtp', 'url' => null,
                'host' => $values['host'], 'port' => (int) $values['port'],
                'username' => $values['username'] ?? null, 'password' => $values['password'] ?? null,
                'timeout' => 15, 'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
            'mail.from.address' => $values['from_address'],
            'mail.from.name' => $values['from_name'] ?? 'Badminton Hub',
        ]);

        return true;
    }

    public function saveGmail(array $values): void
    {
        abort_if((config('integrations.email.mailer') ?? '') === 'smtp', 422, 'Email đang được cấu hình trên máy chủ. Hãy cập nhật cấu hình máy chủ để thay đổi kết nối.');
        $email = strtolower(trim($values['email']));
        $password = preg_replace('/\s+/', '', $values['password'] ?? '');
        $previous = $this->stored();
        if ($password === '' && ($previous['username'] ?? '') === $email) {
            $password = $previous['password'] ?? '';
        }
        if (! preg_match('/^[a-zA-Z0-9]{16}$/', $password)) {
            throw ValidationException::withMessages(['password' => 'Nhập mật khẩu ứng dụng Google gồm 16 ký tự. Không dùng mật khẩu đăng nhập Gmail.']);
        }
        $fields = ['mailer' => 'smtp', 'scheme' => 'smtp', 'host' => 'smtp.gmail.com', 'port' => 587, 'username' => $email, 'password' => $password, 'from_address' => $email, 'from_name' => trim($values['name'])];
        DB::table('integration_settings')->updateOrInsert(['key' => 'email_smtp'], ['value' => Crypt::encryptString(json_encode($fields, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function markTestSent(array $credentials): void
    {
        $proof = ['fingerprint' => $this->fingerprint($credentials), 'sent_at' => now()->toIso8601String()];
        DB::table('integration_settings')->updateOrInsert(['key' => 'email_smtp_check'], ['value' => json_encode($proof, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function status(): array
    {
        $credentials = $this->credentials();
        $proof = json_decode(DB::table('integration_settings')->where('key', 'email_smtp_check')->value('value') ?? '[]', true);

        return [
            'configured' => $this->ready(),
            'source' => (config('integrations.email.mailer') ?? '') === 'smtp' ? 'environment' : ($credentials ? 'database' : 'none'),
            'sender' => $credentials['from_address'] ?? '', 'sender_name' => $credentials['from_name'] ?? 'Badminton Hub',
            'password_saved' => ! empty($credentials['password']),
            'last_test_at' => ($proof['fingerprint'] ?? '') === $this->fingerprint($credentials) ? ($proof['sent_at'] ?? null) : null,
        ];
    }

    private function stored(): array
    {
        $stored = DB::table('integration_settings')->where('key', 'email_smtp')->value('value');
        try {
            $values = $stored ? json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR) : [];

            return is_array($values) ? $values : [];
        } catch (DecryptException|\JsonException) {
            return [];
        }
    }

    private function fingerprint(array $credentials): string
    {
        return hash('sha256', json_encode($credentials, JSON_THROW_ON_ERROR));
    }
}
