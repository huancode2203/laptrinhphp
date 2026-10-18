<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\EmailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailSettingsController extends Controller
{
    public function settings(Request $request, EmailSettings $settings): JsonResponse
    {
        if ($request->isMethod('put')) {
            $values = $request->validate(['email' => 'required|email|max:254', 'name' => 'required|string|max:100', 'password' => 'nullable|string|max:64']);
            $settings->saveGmail($values);
            $settings->apply();
            Mail::purge('smtp');
            app(BookingService::class)->audit($request->user(), 'email.settings_updated', 0, [], 'integration');
        }

        return response()->json(['data' => $settings->status()]);
    }

    public function test(Request $request, EmailSettings $settings): JsonResponse
    {
        abort_unless($settings->apply(), 422, 'Hãy lưu địa chỉ Gmail và mật khẩu ứng dụng trước khi gửi thư thử.');
        $credentials = $settings->credentials();
        try {
            Mail::purge('smtp');
            Mail::raw('Kết nối gửi email Badminton Hub đã nhận yêu cầu gửi thư thử. Bạn có thể dùng email này để gửi mã xác nhận và khôi phục tài khoản trên website.', fn ($message) => $message->to($credentials['from_address'])->subject('Badminton Hub · Kiểm tra gửi email'));
        } catch (\Throwable) {
            abort(503, 'Chưa gửi được thư qua Gmail. Kiểm tra mật khẩu ứng dụng, xác minh 2 bước và kết nối mạng rồi thử lại.');
        }
        $settings->markTestSent($credentials);
        app(BookingService::class)->audit($request->user(), 'email.test_sent', 0, [], 'integration');

        return response()->json(['data' => $settings->status(), 'message' => 'Máy chủ email đã chấp nhận thư thử. Hãy kiểm tra Hộp thư đến và Thư rác của địa chỉ gửi.']);
    }
}
