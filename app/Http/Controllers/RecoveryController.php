<?php

namespace App\Http\Controllers;

use App\IntegrationStatus;
use App\Mail\RecoveryCodeMail;
use App\Models\AuthCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecoveryController extends Controller
{
    public function send(Request $r): JsonResponse
    {
        abort_unless(app(IntegrationStatus::class)->emailEnabled(), 503, 'Email xác thực hiện chưa khả dụng. Vui lòng liên hệ hỗ trợ.');
        $d = $r->validate(['email' => ['required', 'email', 'max:255']]);
        $user = User::where('email', $d['email'])->where('active', true)->first();
        if ($user) {
            abort_if(AuthCode::where('user_id', $user->id)->where('created_at', '>', now()->subMinute())->exists(), 429, 'Vui lòng chờ một phút trước khi gửi lại.');
            $code = (string) random_int(100000, 999999);
            AuthCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
            $record = AuthCode::create(['user_id' => $user->id, 'email' => $user->email, 'code_hash' => Hash::make($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(10)]);
            try {
                Mail::to($user->email)->send(new RecoveryCodeMail($code));
            } catch (\Throwable $e) {
                $record->update(['used_at' => now()]);
                report($e);
                abort(503, 'Chưa gửi được email. Vui lòng thử lại sau.');
            }
        }

        return response()->json(['message' => 'Nếu email có tài khoản đang hoạt động, mã xác nhận đã được gửi.']);
    }

    public function verify(Request $r): JsonResponse
    {
        $d = $r->validate(['email' => ['required', 'email'], 'code' => ['required', 'digits:6']]);
        $token = Str::random(64);
        $ok = DB::transaction(function () use ($d, $token): bool {
            $record = AuthCode::where('email', $d['email'])->whereNull('used_at')->latest('id')->lockForUpdate()->first();
            if (! $record || $record->expires_at->isPast() || $record->attempts >= 5 || $record->verified_at) {
                return false;
            }
            $owner = User::lockForUpdate()->find($record->user_id);
            if (! $owner || ! $owner->active || $owner->email !== $record->email) {
                return false;
            }
            $record->increment('attempts');
            if (! Hash::check($d['code'], $record->code_hash)) {
                return false;
            }
            $record->update(['verified_at' => now(), 'reset_hash' => hash('sha256', $token)]);
            User::whereKey($record->user_id)->update(['email_verified_at' => now()]);

            return true;
        }, 5);
        if (! $ok) {
            throw ValidationException::withMessages(['code' => 'Mã không đúng hoặc đã hết hạn.']);
        }

        return response()->json(['data' => ['reset_token' => $token], 'message' => 'Đã xác nhận email.']);
    }

    public function reset(Request $r): JsonResponse
    {
        $d = $r->validate(['email' => ['required', 'email'], 'reset_token' => ['required', 'string', 'size:64'], 'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed']]);
        DB::transaction(function () use ($d): void {
            $record = AuthCode::where('email', $d['email'])->whereNull('used_at')->whereNotNull('verified_at')->latest('id')->lockForUpdate()->first();
            if (! $record || $record->expires_at->isPast() || ! hash_equals((string) $record->reset_hash, hash('sha256', $d['reset_token']))) {
                throw ValidationException::withMessages(['reset_token' => 'Phiên xác nhận đã hết hạn.']);
            }
            $user = User::whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($user->active && $user->email === $record->email, 403);
            $user->forceFill(['password' => $d['password'], 'remember_token' => Str::random(60)])->save();
            $record->update(['used_at' => now()]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return response()->json(['message' => 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.']);
    }
}
