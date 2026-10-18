<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ai', function (Request $request): array {
            $filters = $request->input('filters');
            if ($request->is('api/v1/ai/courts') && is_array($filters) && count($filters) > 0) {
                return [Limit::perMinute(60)->by('court-filters-minute:'.$request->ip()), Limit::perHour(600)->by('court-filters-hour:'.$request->ip())];
            }

            return [Limit::perMinute(6)->by('ai:'.$request->ip()), Limit::perHour(60)->by('ai:'.$request->ip())];
        });
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(6)->by('login:'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(3)->by('register:'.$request->ip()));
        RateLimiter::for('book', fn (Request $request) => Limit::perMinute(10)->by('book:'.$request->user()->id));
        RateLimiter::for('quote', fn (Request $request) => Limit::perMinute(30)->by('quote:'.$request->ip()));
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(5)->by('sensitive:'.$request->user()->id));
    }
}
