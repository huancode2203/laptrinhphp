<?php

namespace App\Http\Controllers;

use App\Models\CourtHold;
use App\Models\User;
use App\Rules\VietnamesePhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrf(): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token()]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', new VietnamesePhone],
            'skill_level' => ['sometimes', 'string', 'max:40'], 'public_profile' => ['sometimes', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $user, 'message' => 'Đăng ký thành công.'], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:72'], 'remember' => ['sometimes', 'boolean']]);
        $login = $data['email'];
        $credentials = ['password' => $data['password'], 'active' => true];
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $login;
        } else {
            $phone = preg_replace('/[\s.-]/', '', $login);
            if (str_starts_with($phone, '0')) {
                $phone = '+84'.substr($phone, 1);
            }
            $verified = User::whereIn('phone', [$phone, str_starts_with($phone, '+84') ? '0'.substr($phone, 3) : $phone])->whereNotNull('phone_verified_at')->first();
            if (! $verified) {
                throw ValidationException::withMessages(['email' => 'Email/SĐT hoặc mật khẩu không đúng.']);
            }
            $credentials['email'] = $verified->email;
        }
        if (! Auth::attempt($credentials, $data['remember'] ?? false)) {
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng.']);
        }
        $request->session()->regenerate();

        return response()->json(['data' => $request->user(), 'message' => 'Đăng nhập thành công.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        CourtHold::where('user_id', $request->user()->id)->where('released', false)->update(['released' => true]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }
}
