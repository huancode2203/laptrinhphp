<?php

namespace App\Console\Commands;

use App\IntegrationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckIntegrations extends Command
{
    protected $signature = 'hub:integrations {--probe : Gọi thật hai model AI} {--email-to= : Gửi email thử tới địa chỉ chỉ định} {--json : Xuất trạng thái không chứa khóa}';

    protected $description = 'Kiểm tra cấu hình AI, thanh toán, Google, SMS và SMTP';

    public function handle(IntegrationStatus $integrations): int
    {
        $items = $integrations->all();
        $probes = [];
        $failed = false;
        if ($this->option('probe')) {
            foreach (['search', 'moderation'] as $task) {
                $probes[] = $result = $integrations->probeAi($task);
                $failed = $failed || ! $result['success'];
            }
        }
        if ($email = $this->option('email-to')) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $integrations->emailEnabled()) {
                $probes[] = ['id' => 'email', 'success' => false, 'message' => 'Cần cấu hình SMTP và nhập địa chỉ email hợp lệ.'];
                $failed = true;
            } else {
                try {
                    Mail::raw('Email kiểm tra kết nối Badminton Hub. Nếu bạn nhận được thư này, kết nối gửi email đã hoạt động.', fn ($message) => $message->to($email)->subject('Badminton Hub · Kiểm tra email'));
                    $probes[] = ['id' => 'email', 'success' => true, 'message' => 'SMTP đã nhận email thử. Kiểm tra hộp thư và thư rác để xác nhận nhận thư.'];
                } catch (\Throwable) {
                    $probes[] = ['id' => 'email', 'success' => false, 'message' => 'Chưa gửi được thư. Kiểm tra máy chủ SMTP, cổng và thông tin đăng nhập.'];
                    $failed = true;
                }
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode(['configuration' => $items, 'checks' => $probes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(['Dịch vụ', 'Trạng thái cấu hình', 'Còn thiếu'], array_map(fn ($item) => [$item['label'], ! $item['enabled'] ? 'Đang tắt' : ($item['configured'] ? 'Đã điền cấu hình' : 'Chưa đủ cấu hình'), implode(', ', $item['missing'])], $items));
            foreach ($probes as $probe) {
                $this->line(($probe['success'] ? 'OK: ' : 'Chưa đạt: ').$probe['message']);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
