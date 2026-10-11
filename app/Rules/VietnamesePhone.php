<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class VietnamesePhone implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = is_string($value) ? preg_replace('/[\s.()-]/', '', $value) : '';
        if (! preg_match('/^(?:0|\+84)(?:[35789][0-9]{8}|2[0-9]{9})$/D', $phone)) {
            $fail('Số điện thoại không hợp lệ. Nhập số Việt Nam bắt đầu bằng 0 hoặc +84.');
        }
    }
}
