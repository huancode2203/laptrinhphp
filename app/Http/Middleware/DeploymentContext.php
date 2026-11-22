<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeploymentContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(parse_url((string) config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            config(['app.url' => rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/')]);
        }
        config(['session.secure' => $request->isSecure(), 'session.path' => $request->getBaseUrl() ?: '/']);
        app(Vite::class)->createAssetPathsUsing(fn (string $path): string => $request->getBaseUrl().'/'.$path);
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
