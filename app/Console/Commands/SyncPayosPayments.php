<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\PayosService;
use App\PayosSettings;
use Illuminate\Console\Command;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SyncPayosPayments extends Command
{
    protected $signature = 'hub:sync-payos {--limit=10 : Số giao dịch tối đa mỗi lượt, từ 1 đến 50} {--json : Xuất kết quả không chứa khóa}';

    protected $description = 'Đối soát giao dịch PayOS đang chờ, hỗ trợ máy chạy localhost chưa có webhook';

    public function handle(PayosService $payos, PayosSettings $settings): int
    {
        $limit = (string) $this->option('limit');
        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 50) {
            $this->error('Số giao dịch mỗi lượt phải từ 1 đến 50.');

            return self::INVALID;
        }
        $summary = ['configured' => $settings->ready(), 'checked' => 0, 'settled' => 0, 'failed' => 0, 'errors' => 0];
        if ($summary['configured']) {
            $attempts = PaymentAttempt::where('partner_code', 'PAYOS')->where('status', 'pending')
                ->where('expires_at', '>', now()->subDay())->orderBy('updated_at')->orderBy('id')->limit((int) $limit)->get();
            foreach ($attempts as $attempt) {
                $summary['checked']++;
                try {
                    $result = $payos->sync($attempt);
                    $summary['settled'] += in_array($result->status, ['paid', 'credited'], true) ? 1 : 0;
                    $summary['failed'] += $result->status === 'failed' ? 1 : 0;
                } catch (HttpExceptionInterface) {
                    $summary['errors']++;
                } finally {
                    PaymentAttempt::whereKey($attempt->id)->where('status', 'pending')->update(['updated_at' => now()]);
                }
            }
        }
        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR));
        } else {
            $this->info($summary['configured']
                ? 'Đã kiểm tra '.$summary['checked'].' giao dịch: '.$summary['settled'].' đã nhận tiền, '.$summary['failed'].' đã hủy/hết hạn, '.$summary['errors'].' chưa kiểm tra được.'
                : 'PayOS chưa có cấu hình; chưa gửi yêu cầu tới dịch vụ.');
        }

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
