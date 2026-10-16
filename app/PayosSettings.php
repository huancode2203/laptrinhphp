<?php

namespace App;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PayosSettings
{
    public function credentials(): array
    {
        $stored = DB::table('integration_settings')->where('key', 'payos')->value('value');
        try {
            $saved = $stored ? json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR) : [];
        } catch (DecryptException|\JsonException) {
            $saved = [];
        }

        return collect(['client_id', 'api_key', 'checksum_key'])->mapWithKeys(fn ($key) => [$key => config('services.payos.'.$key) ?: ($saved[$key] ?? '')])->all();
    }

    public function ready(): bool
    {
        return count(array_filter($this->credentials())) === 3;
    }

    public function save(array $values): void
    {
        $fields = collect($values)->only(['client_id', 'api_key', 'checksum_key'])->all();
        DB::table('integration_settings')->updateOrInsert(['key' => 'payos'], ['value' => Crypt::encryptString(json_encode($fields, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function status(): array
    {
        return ['configured' => $this->ready(), 'webhook_url' => url('/api/v1/payments/payos/webhook'), 'return_url' => url('/payments/payos/return')];
    }
}
