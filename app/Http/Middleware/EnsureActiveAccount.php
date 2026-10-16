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
        if ($request->user() && $request->user()->role !== 'customer' && ($request->is('api/v1/account', 'api/v1/features/account', 'api/v1/loyalty/redeem', 'api/v1/coaching', 'api/v1/memberships/*/buy', 'api/v1/courts/*/favorite', 'api/v1/waitlist') || ($request->isMethod('POST') && $request->is('api/v1/events/*/register', 'api/v1/community/posts', 'api/v1/community/posts/*/join')))) {
            abort(403, 'Tài khoản làm việc sử dụng khu quản trị hoặc điều hành chi nhánh. Chức năng này dành cho khách hàng.');
        }
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
