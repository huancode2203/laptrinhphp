<?php

namespace App;

class DemoService
{
    public function active(): bool
    {
        return (bool) config('demo.enabled') && app()->environment('local', 'testing');
    }

    public function uses(string $service): bool
    {
        return ! in_array($service, ['momo', 'sepay', 'bank', 'payos'], true) && $this->active() && ! app(IntegrationStatus::class)->ready($service);
    }
}
