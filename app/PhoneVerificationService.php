<?php

namespace App;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PhoneVerificationService
{
    public function send(string $phone): void
    {
        $body = $this->request('Verifications', ['To' => $phone, 'Channel' => 'sms']);
        abort_unless(($body['status'] ?? null) === 'pending', 502, 'Chưa gửi được mã xác thực. Vui lòng thử lại sau.');
    }

    public function verify(string $phone, string $code): void
    {
        $body = $this->request('VerificationCheck', ['To' => $phone, 'Code' => $code]);
        abort_unless(($body['status'] ?? null) === 'approved', 422, 'Mã xác thực không đúng hoặc đã hết hạn.');
    }

    private function request(string $operation, array $data): array
    {
        abort_unless(app(IntegrationStatus::class)->ready('sms'), 503, 'Xác thực điện thoại hiện chưa khả dụng.');
        try {
            $response = Http::asForm()->withBasicAuth(config('integrations.sms.sid'), config('integrations.sms.token'))->connectTimeout(5)->timeout(15)
                ->post('https://verify.twilio.com/v2/Services/'.config('integrations.sms.service').'/'.$operation, $data);
        } catch (ConnectionException) {
            abort(503, 'Chưa kết nối được dịch vụ xác thực. Vui lòng thử lại sau.');
        }
        abort_if($response->status() === 429, 429, 'Đã gửi quá nhiều yêu cầu. Vui lòng chờ trước khi thử lại.');
        abort_if($operation === 'VerificationCheck' && $response->status() === 404, 422, 'Mã xác thực đã hết hạn. Vui lòng yêu cầu mã mới.');
        abort_unless($response->successful() && is_array($response->json()), 503, 'Dịch vụ xác thực hiện chưa khả dụng. Vui lòng thử lại sau.');

        return $response->json();
    }
}
