<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiInput
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            // Cross-field date comparisons cannot safely parse arrays/objects.
            // Validate their shape first, before any endpoint compares dates.
            $rules = [];
            foreach (['starts_at', 'ends_at', 'expires_at', 'date', 'from', 'to', 'open_time', 'close_time', 'peak_time', 'start_time', 'end_time', 'filters.date', 'filters.start_time', 'filters.end_time'] as $field) {
                $rules[$field] = ['sometimes', 'nullable', 'string'];
            }
            $request->validate($rules);
        }
        if ($request->is('api/*') && $request->isMethod('GET')) {
            $request->validate(['page' => ['sometimes', 'integer', 'between:1,100000'], 'per_page' => ['sometimes', 'integer', 'between:1,100']]);
        }

        return $next($request);
    }
}
