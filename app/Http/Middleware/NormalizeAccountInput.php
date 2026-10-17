<?php

namespace App\Http\Middleware;

use App\AccountIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeAccountInput
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/payments/*/webhook', 'api/v1/payments/momo/ipn')) {
            $values = [];
            if (is_string($request->input('phone'))) {
                $values['phone'] = AccountIdentity::phone($request->input('phone'));
            }
            if (is_string($request->input('email'))) {
                $values['email'] = mb_strtolower(trim($request->input('email')));
            }
            $request->merge($values);
        }

        return $next($request);
    }
}
