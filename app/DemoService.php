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
        return $this->active() && ! app(IntegrationStatus::class)->ready($service);
    }
}
