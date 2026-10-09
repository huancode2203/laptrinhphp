<?php

namespace App\Http\Middleware;

use App\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {

        $user = $request->user();
        abort_unless($user, 403);
        if (str_starts_with($request->path(), 'api/v1/admin/')) {
            abort_unless($user->role === 'admin', 403);
        } else {
            $access = app(AccessService::class);
            $permission = $access->permission($request);
            $aggregate = in_array($request->path(), ['api/v1/manage/hub', 'api/v1/manage/features'], true);
            $allowed = $aggregate
                ? collect(AccessService::PERMISSIONS)->contains(fn ($p) => $access->allows($user, $p))
                : ($permission && $access->allows($user, $permission));
            abort_unless($allowed, 403, 'Bạn không có quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}
