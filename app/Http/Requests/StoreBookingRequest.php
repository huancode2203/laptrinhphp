<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:30'],
            'hold_id' => ['sometimes', 'integer', 'exists:court_holds,id'],
            'deposit_only' => 'sometimes|boolean',
            'pay_with_wallet' => ['sometimes', 'boolean'],
            'services' => ['sometimes', 'array', 'list', 'max:20'],
            'services.*.id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
