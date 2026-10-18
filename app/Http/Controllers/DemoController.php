<?php

namespace App\Http\Controllers;

use App\DemoService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoController extends Controller
{
    public function google(Request $r): JsonResponse
    {
        abort_unless(app(DemoService::class)->uses('google'), 404);
        abort_if($r->user(), 409, 'Hãy đăng xuất trước khi thử tài khoản mẫu.');
        $user = DB::transaction(function (): User {
            $user = User::where('email', 'google-demo@badminton.test')->lockForUpdate()->first();
            if ($user) {
                abort_unless($user->role === 'customer' && $user->google_sub === 'demo:google-customer' && $user->active, 409);

                return $user;
            }
            $user = User::create(['email' => 'google-demo@badminton.test', 'name' => 'Võ Gia Hân', 'password' => Str::random(60)]);
            $user->forceFill(['google_sub' => 'demo:google-customer'])->save();

            return $user;
        });
        Auth::login($user);
        $r->session()->regenerate();

        return response()->json(['message' => 'Đang dùng tài khoản demo. Không đăng nhập vào Google thật.']);
    }
}
