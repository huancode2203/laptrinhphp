<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request, BookingService $engine): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'current_password' => ['sometimes', 'string', 'max:72'],
        ]);

        $user = DB::transaction(function () use ($request, $data, $engine): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($data['email'] !== $user->email) {
                if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Nhập đúng mật khẩu hiện tại để đổi email.']);
                }
                $user->email_verified_at = null;
            }
            $user->fill(collect($data)->except('current_password')->all())->save();
            $engine->audit($user, 'profile.updated', $user->id, [], 'user');

            return $user;
        }, 5);

        return response()->json(['data' => $user, 'message' => 'Đã cập nhật hồ sơ.']);
    }

    public function password(Request $request, BookingService $engine): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:72'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', 'different:current_password'],
        ]);
        DB::transaction(function () use ($request, $data, $engine): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
            }
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            $engine->audit($user, 'password.changed', $user->id, [], 'user');
        }, 5);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Đã đổi mật khẩu và kết thúc các phiên đăng nhập khác.']);
    }
}
