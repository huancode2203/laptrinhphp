<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\FirebaseAuthService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FirebaseAuthController extends Controller
{
    public function login(Request $request, FirebaseAuthService $firebase): JsonResponse
    {
        $data = $request->validate(['id_token' => 'required|string|max:12000']);
        $identity = $firebase->verify($data['id_token']);
        $link = $request->user();
        $user = DB::transaction(function () use ($identity, $link): User {
            $owner = User::where('firebase_uid', $identity['uid'])->orWhere('google_sub', $identity['google_sub'])->lockForUpdate()->first();
            if ($link) {
                abort_unless($link->active, 403);
                if (time() - $identity['auth_time'] > 300) {
                    throw ValidationException::withMessages(['id_token' => 'Hãy đăng nhập lại Google để liên kết tài khoản.']);
                }
                $user = User::lockForUpdate()->findOrFail($link->id);
                if (($owner && $owner->id !== $user->id) || ($user->google_sub && $user->google_sub !== $identity['google_sub']) || ($user->firebase_uid && $user->firebase_uid !== $identity['uid'])) {
                    throw ValidationException::withMessages(['google' => 'Tài khoản Google này đã được liên kết với một tài khoản khác.']);
                }
            } elseif ($owner) {
                $user = $owner;
                abort_unless($user->active, 403, 'Tài khoản đang bị khóa.');
                if (($user->google_sub && $user->google_sub !== $identity['google_sub']) || ($user->firebase_uid && $user->firebase_uid !== $identity['uid'])) {
                    throw ValidationException::withMessages(['google' => 'Liên kết Google không khớp. Vui lòng liên hệ hỗ trợ.']);
                }
            } else {
                abort_if(User::where('email', $identity['email'])->exists(), 409, 'Email đã có tài khoản. Hãy đăng nhập bằng mật khẩu, sau đó liên kết Google trong Hồ sơ.');
                $user = User::create(['name' => $identity['name'], 'email' => $identity['email'], 'password' => Str::random(60)]);
            }
            $user->forceFill(['firebase_uid' => $identity['uid'], 'google_sub' => $identity['google_sub'], 'last_seen_at' => now()]);
            if ($user->email === $identity['email']) {
                $user->email_verified_at = now();
            }
            $user->save();
            app(BookingService::class)->audit($user, $link ? 'google.linked' : 'google.login', $user->id, ['provider' => 'firebase'], 'user');

            return $user;
        }, 5);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $user, 'message' => $link ? 'Đã liên kết Google với tài khoản của bạn.' : 'Đăng nhập Google thành công.']);
    }

    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'apiKey' => 'required|string|regex:/^AIza[A-Za-z0-9_-]{30,100}$/',
            'authDomain' => 'required|string|max:253|regex:/^[a-zA-Z0-9.-]+$/',
            'projectId' => 'required|string|regex:/^[a-z][a-z0-9-]{4,28}[a-z0-9]$/',
            'appId' => 'required|string|regex:/^1:[0-9]+:web:[a-f0-9]+$/',
        ]);
        DB::table('integration_settings')->updateOrInsert(['key' => 'firebase_web'], ['value' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
        app(BookingService::class)->audit($request->user(), 'firebase.settings_updated', 0, ['project_id' => $data['projectId']], 'integration');

        return response()->json(['message' => 'Đã lưu Firebase. Đảm bảo Google đã bật và tên miền website được cho phép trong Firebase Authentication.']);
    }
}
