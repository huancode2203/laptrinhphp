<?php

namespace App;

use Illuminate\Support\Facades\DB;

class BankSettings
{
    public function details(): ?array
    {
        $configured = collect(config('integrations.bank'))->only(['account', 'name', 'bank_bin'])->all();
        if (! empty($configured['account']) && ! empty($configured['name']) && ! empty($configured['bank_bin'])) {
            return [...$configured, 'mode' => app(IntegrationStatus::class)->ready('sepay') ? 'automatic' : 'manual'];
        }
        $record = DB::table('integration_settings')->where('key', 'personal_bank')->value('value');
        if ($record) {
            return [...json_decode($record, true), 'mode' => 'manual'];
        }

        return null;
    }
}
