<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort_if($request->is('api/*'), 403, 'Tài khoản đang bị khóa.');
        }

        if ($request->user() && (! $request->user()->last_seen_at || $request->user()->last_seen_at->lt(now()->subMinute()))) {
            $request->user()->forceFill(['last_seen_at' => now()])->save();
        }

        return $next($request);
    }
}
