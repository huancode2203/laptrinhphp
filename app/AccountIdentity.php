<?php

namespace App;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class AccountIdentity
{
    public static function phone(?string $value): ?string
    {
        $phone = preg_replace('/[\s.()\-]/', '', $value ?? '');

        return $phone === '' ? null : (str_starts_with($phone, '0') ? '+84'.substr($phone, 1) : $phone);
    }

    public static function assertAvailable(User $user): void
    {
        if (! $user->isDirty('phone') || ! $user->phone) {
            return;
        }
        $duplicate = User::whereNotNull('phone')->where('id', '!=', $user->id ?? 0)->where(function ($query) use ($user): void {
            $query->where('phone_normalized', $user->phone)->orWhereNull('phone_normalized');
        })->get(['id', 'phone'])->contains(fn ($other) => self::phone($other->phone) === $user->phone);
        if ($duplicate) {
            throw ValidationException::withMessages(['phone' => 'Số điện thoại này đã có tài khoản. Vui lòng đăng nhập hoặc chọn khách hàng hiện có.']);
        }
    }
}
