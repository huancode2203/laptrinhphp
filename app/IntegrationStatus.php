<?php

namespace App;

class IntegrationStatus
{
    public function ready(string $id): bool
    {
        $item = collect($this->all())->firstWhere('id', $id);

        return $item && $item['enabled'] && $item['configured'];
    }

    public function all(): array
    {
        $items = [];
        foreach (['search' => 'AI tìm sân', 'moderation' => 'AI duyệt bài'] as $task => $label) {
            $provider = config('ai.'.$task.'.provider');
            $fields = ['AI_'.strtoupper($task).'_MODEL' => config('ai.'.$task.'.model')];
            if ($provider === 'openai') {
                $fields['OPENAI_API_KEY'] = config('ai.openai_key');
            } elseif ($provider === 'ollama') {
                $fields['OLLAMA_URL'] = filter_var(config('ai.ollama_url'), FILTER_VALIDATE_URL);
            } else {
                $fields['AI_'.strtoupper($task).'_PROVIDER'] = false;
            }
            $items[] = $this->item('ai_'.$task, $label, true, $fields, $provider.' · '.config('ai.'.$task.'.model'), $provider === 'openai' ? 'https://platform.openai.com/api-keys' : 'https://ollama.com/download/windows');
        }
        $items[] = $this->item('momo', 'Thanh toán MoMo', (bool) config('momo.enabled'), [
            'MOMO_PARTNER_CODE' => config('momo.partner_code'), 'MOMO_ACCESS_KEY' => config('momo.access_key'), 'MOMO_SECRET_KEY' => config('momo.secret_key'),
            'MOMO_IPN_URL' => $this->publicHttps(config('momo.ipn_url')), 'MOMO_REDIRECT_URL' => $this->publicHttps(config('momo.redirect_url')),
            'MOMO_ENDPOINT' => in_array(config('momo.endpoint'), ['https://test-payment.momo.vn/v2/gateway/api/create', 'https://payment.momo.vn/v2/gateway/api/create'], true),
        ], str_contains(config('momo.endpoint'), 'test-payment') ? 'Thử nghiệm' : 'Chính thức', 'https://business.momo.vn/');
        $items[] = $this->item('sepay', 'Chuyển khoản SePay', (bool) config('integrations.bank.enabled'), [
            'SEPAY_WEBHOOK_KEY' => config('integrations.bank.key'), 'BANK_ACCOUNT_NUMBER' => config('integrations.bank.account'), 'BANK_ACCOUNT_NAME' => config('integrations.bank.name'), 'BANK_BIN' => config('integrations.bank.bank_bin'),
        ], 'VietQR và xác nhận giao dịch', 'https://my.sepay.vn/');
        $redirect = config('integrations.google.redirect_uri');
        $items[] = $this->item('google', 'Đăng nhập Google', true, [
            'GOOGLE_CLIENT_ID' => config('integrations.google.client_id'), 'GOOGLE_CLIENT_SECRET' => config('integrations.google.client_secret'),
            'GOOGLE_REDIRECT_URI' => $this->publicHttps($redirect) || (filter_var($redirect, FILTER_VALIDATE_URL) && in_array(parse_url($redirect, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)),
        ], 'Google OAuth', 'https://console.cloud.google.com/auth/clients');
        $items[] = $this->item('sms', 'Xác thực điện thoại', (bool) config('integrations.sms.enabled'), [
            'TWILIO_ACCOUNT_SID' => config('integrations.sms.sid'), 'TWILIO_AUTH_TOKEN' => config('integrations.sms.token'), 'TWILIO_VERIFY_SERVICE_SID' => config('integrations.sms.service'),
        ], 'Twilio Verify SMS', 'https://console.twilio.com/');
        $mailer = config('mail.default');
        $mailFields = ['MAIL_MAILER' => $mailer === 'smtp', 'MAIL_HOST' => config('mail.mailers.smtp.host'), 'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_FROM_ADDRESS' => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) && ! str_ends_with(config('mail.from.address'), '@example.com')];
        $items[] = $this->item('email', 'Email xác thực và khôi phục', true, $mailFields, $mailer === 'smtp' ? 'SMTP' : 'Chưa gửi email ra hộp thư', 'https://support.google.com/accounts/answer/185833');

        return $items;
    }

    public function probeAi(string $task): array
    {
        $id = 'ai_'.$task;
        if (! in_array($task, ['search', 'moderation'], true) || ! $this->ready($id)) {
            return ['id' => $id, 'success' => false, 'message' => 'Cần điền cấu hình AI trước khi kiểm tra.'];
        }
        $started = microtime(true);
        $details = [];
        try {
            if ($task === 'search') {
                $result = app(CourtSearchService::class)->search('Ngày mai tôi muốn đánh cầu từ 13h đến 15h ở toàn thành phố Hồ Chí Minh.');
                $details = ['filters' => $result['filters'], 'source' => $result['mode'], 'model_issue' => $result['model_issue'] ?? null];
                $success = $result['mode'] === 'model' && $result['filters']['date'] === now()->addDay()->toDateString() && $result['filters']['start_time'] === '13:00' && $result['filters']['end_time'] === '15:00' && ! $result['filters']['district'];
                $message = $success ? 'Đã nhận kết quả từ model; hệ thống chuẩn hóa ngày giờ và đối chiếu được '.($result['total_count'] ?? 0).' sân phù hợp trong cơ sở dữ liệu.' : 'Model chưa trả nhu cầu tìm sân đúng trong lượt kiểm tra.';
            } else {
                $result = app(ModerationService::class)->inspect('Tìm bạn giao lưu cầu lông vui vẻ vào chiều mai, chào đón mọi trình độ.');
                $flagged = app(ModerationService::class)->inspect('Mày là đồ vô dụng, cút khỏi sân này ngay, tao sẽ đánh mày.');
                $success = $result['source'] === 'model' && $result['decision'] === 'allow' && $flagged['source'] === 'model' && $flagged['decision'] === 'review';
                $details = ['samples' => ['appropriate' => $result['decision'], 'abusive' => $flagged['decision']]];
                $message = $success ? 'Model đề xuất duyệt bài giao lưu phù hợp và chuyển bài xúc phạm, đe dọa sang chờ người duyệt.' : 'Chưa có đề xuất chắc chắn từ model; bài vẫn cần người duyệt.';
            }
        } catch (\Throwable) {
            $success = false;
            $message = 'Chưa kết nối được model. Kiểm tra Ollama đang chạy, model đã tải hoặc khóa OpenAI.';
        }

        return ['id' => $id, 'success' => $success, 'message' => $message, 'seconds' => round(microtime(true) - $started, 2), 'checked_at' => now()->toIso8601String(), ...$details];
    }

    public function emailEnabled(): bool
    {
        return $this->ready('email') || (app()->environment('testing') && config('mail.default') === 'array');
    }

    private function item(string $id, string $label, bool $enabled, array $fields, string $mode, string $setupUrl): array
    {
        $missing = array_keys(array_filter($fields, fn ($value) => ! $value));

        return ['id' => $id, 'label' => $label, 'enabled' => $enabled, 'configured' => $missing === [], 'missing' => $missing, 'mode' => $mode, 'setup_url' => $setupUrl];
    }

    private function publicHttps(?string $url): bool
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        return ! in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }
}
