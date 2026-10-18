<?php

namespace App;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FirebaseAuthService
{
    public const CERT_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function webConfig(): ?array
    {
        $config = config('firebase', []);
        if (! $this->usesEnvironmentConfiguration()) {
            $saved = DB::table('integration_settings')->where('key', 'firebase_web')->value('value');
            $config = $saved ? json_decode($saved, true) : [];
        }

        return $this->completeWebConfig($config) ? array_intersect_key($config, array_flip(['apiKey', 'authDomain', 'projectId', 'appId'])) : null;
    }

    public function usesEnvironmentConfiguration(): bool
    {
        return $this->completeWebConfig(config('firebase', []));
    }

    private function completeWebConfig(mixed $values): bool
    {
        if (! is_array($values)) {
            return false;
        }
        foreach (['apiKey', 'authDomain', 'projectId', 'appId'] as $key) {
            if (! is_string($values[$key] ?? null) || trim($values[$key]) === '') {
                return false;
            }
        }

        return true;
    }

    /** @return array{uid: string, google_sub: string, email: string, name: string, auth_time: int} */
    public function verify(string $token): array
    {
        $config = $this->webConfig();
        abort_unless($config, 503, 'Đăng nhập Google hiện chưa được cấu hình.');
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new \UnexpectedValueException;
            }
            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
            if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
                throw new \UnexpectedValueException;
            }
            $certs = $this->certificates();
            if (! isset($certs[$header['kid']])) {
                Cache::forget('firebase.signing-certificates');
                $certs = $this->certificates();
            }
            if (! isset($certs[$header['kid']])) {
                throw new \UnexpectedValueException;
            }
            $claims = (array) JWT::decode($token, new Key($certs[$header['kid']], 'RS256'));
            $firebase = (array) ($claims['firebase'] ?? []);
            $identities = (array) ($firebase['identities'] ?? []);
            $google = $identities['google.com'][0] ?? null;
            if (($claims['aud'] ?? null) !== $config['projectId'] || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/'.$config['projectId']
                || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 128
                || ! is_int($claims['exp'] ?? null) || ! is_int($claims['iat'] ?? null) || $claims['iat'] > time()
                || ! is_int($claims['auth_time'] ?? null) || $claims['auth_time'] > time()
                || ($claims['email_verified'] ?? false) !== true || ! filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL)
                || ($firebase['sign_in_provider'] ?? null) !== 'google.com' || ! is_string($google) || $google === '') {
                throw new \UnexpectedValueException;
            }
            $reply = Http::connectTimeout(5)->timeout(10)->post('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.rawurlencode($config['apiKey']), ['idToken' => $token]);
            if (! $reply->successful()) {
                if ($reply->serverError() || $reply->status() === 429) {
                    abort(503, 'Google đang bận. Vui lòng thử lại sau.');
                }
                throw new \UnexpectedValueException;
            }
            $identity = $reply->json('users.0');
            $provider = collect($identity['providerUserInfo'] ?? [])->firstWhere('providerId', 'google.com');
            if (! $identity || ($identity['localId'] ?? null) !== $claims['sub'] || ($identity['disabled'] ?? false)
                || (int) ($identity['validSince'] ?? 0) > $claims['auth_time']
                || ($identity['emailVerified'] ?? false) !== true || ($provider['rawId'] ?? null) !== $google
                || ($identity['email'] ?? null) !== $claims['email']) {
                throw new \UnexpectedValueException;
            }

            return ['uid' => $claims['sub'], 'google_sub' => $google, 'email' => $claims['email'], 'name' => mb_substr($claims['name'] ?? strstr($claims['email'], '@', true), 0, 100), 'auth_time' => $claims['auth_time']];
        } catch (ConnectionException|RequestException) {
            abort(503, 'Chưa kết nối được Google. Vui lòng thử lại sau.');
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException|\JsonException|\TypeError) {
            throw ValidationException::withMessages(['id_token' => 'Phiên Google không hợp lệ hoặc đã hết hạn. Vui lòng đăng nhập lại.']);
        }
    }

    private function certificates(): array
    {
        $cached = Cache::get('firebase.signing-certificates');
        if (is_array($cached)) {
            return $cached;
        }
        $response = Http::connectTimeout(5)->timeout(10)->get(self::CERT_URL)->throw();
        $certificates = $response->json();
        if (! is_array($certificates)) {
            throw new \UnexpectedValueException;
        }
        $ttl = preg_match('/max-age=(\d+)/i', $response->header('Cache-Control'), $matches) ? min(86400, (int) $matches[1]) : 300;
        if ($ttl > 0) {
            Cache::put('firebase.signing-certificates', $certificates, $ttl);
        }

        return $certificates;
    }
}
