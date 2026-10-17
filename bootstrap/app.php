<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\NormalizeAccountInput;
use App\Http\Middleware\ValidateApiInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

// Apache on Windows serves concurrent requests in one process. Keep .env
// values request-local instead of sharing putenv/getenv across its threads.
if (PHP_SAPI === 'apache2handler') {
    Env::disablePutenv();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->appendToGroup('web', EnsureActiveAccount::class);
        $middleware->appendToGroup('web', ValidateApiInput::class);
        $middleware->appendToGroup('web', NormalizeAccountInput::class);
        $middleware->validateCsrfTokens(except: ['api/v1/payments/momo/ipn', 'api/v1/payments/bank/webhook', 'api/v1/payments/payos/webhook']);
        $middleware->trimStrings(except: [fn (Request $r) => $r->is('api/v1/payments/momo/ipn', 'api/v1/payments/payos/webhook')]);
        $middleware->convertEmptyStringsToNull(except: [fn (Request $r) => $r->is('api/v1/payments/momo/ipn', 'api/v1/payments/payos/webhook')]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (UniqueConstraintViolationException $exception, Request $request) {
            if ($request->is('api/*') && str_contains($exception->getMessage(), 'phone_normalized')) {
                return response()->json(['message' => 'Số điện thoại này đã có tài khoản.', 'errors' => ['phone' => ['Số điện thoại này đã có tài khoản. Vui lòng chọn hồ sơ hiện có.']]], 422);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
