<?php

namespace App\Http\Controllers;

use App\BankSettings;
use App\DemoService;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\MomoService;
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
            $user = User::create(['email' => 'google-demo@badminton.test', 'name' => 'Người chơi mẫu', 'password' => Str::random(60)]);
            $user->forceFill(['google_sub' => 'demo:google-customer'])->save();

            return $user;
        });
        Auth::login($user);
        $r->session()->regenerate();

        return response()->json(['message' => 'Đang dùng tài khoản demo. Không đăng nhập vào Google thật.']);
    }

    public function bank(Request $r, Booking $booking, MomoService $m): JsonResponse
    {
        abort_unless((app(BankSettings::class)->details()['mode'] ?? null) === 'demo', 404);
        $d = $r->validate(['request_key' => 'required|uuid', 'deposit_only' => 'sometimes|boolean']);

        return response()->json(['data' => $m->create($r->user(), [...$d, 'purpose' => 'booking', 'booking_id' => $booking->id], 'DEMO_BANK')]);
    }

    public function payment(Request $r, PaymentAttempt $attempt, MomoService $m): JsonResponse
    {
        abort_unless(app(DemoService::class)->active() && $m->isDemo($attempt), 404);
        abort_unless($attempt->user_id === $r->user()->id, 403);
        $d = $r->validate(['success' => 'required|boolean']);

        return response()->json(['data' => $m->completeDemo($attempt, $d['success']), 'message' => $d['success'] ? 'Đã ghi nhận giao dịch demo, không chuyển tiền thật.' : 'Đã hủy giao dịch demo.']);
    }
}
