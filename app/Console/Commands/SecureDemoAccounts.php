<?php

namespace App\Console\Commands;

use App\BookingService;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('hub:secure-demo-accounts {--output= : Tệp riêng lưu thông tin đăng nhập mới} {--force : Xác nhận đổi mật khẩu mặc định}')]
#[Description('Đổi mật khẩu mặc định của các tài khoản mẫu trước khi mở website công khai')]
class SecureDemoAccounts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $output = $this->option('output');
        if (! $this->option('force') || ! is_string($output) || $output === '' || ! is_dir(dirname($output)) || file_exists($output)) {
            $this->error('Cần --force và --output trỏ đến tệp chưa tồn tại trong thư mục riêng đã giới hạn quyền truy cập.');

            return self::FAILURE;
        }
        $count = DB::transaction(function () use ($output): int {
            $accounts = User::where('email', 'like', '%@badminton.test')->orderBy('id')->lockForUpdate()->get()->filter(fn ($user) => Hash::check('Demo@12345', $user->password));
            $text = "Badminton Hub · Thông tin đăng nhập riêng sau triển khai\r\nKhông đưa tệp này lên GitHub hoặc gửi trong chat.\r\n\r\n";
            foreach ($accounts as $account) {
                $password = Str::password(24, symbols: false);
                $text .= $account->name.' · '.$account->role.' · '.$account->email.' · '.$password."\r\n";
                $account->forceFill(['password' => $password, 'remember_token' => null])->save();
                app(BookingService::class)->audit($account, 'account.secured', $account->id, ['reason' => 'Đổi mật khẩu mặc định khi triển khai công khai'], 'user');
            }
            if ($accounts->isNotEmpty()) {
                DB::table('sessions')->whereIn('user_id', $accounts->pluck('id'))->delete();
            }
            $stream = fopen($output, 'x');
            if ($stream === false) {
                throw new \RuntimeException('Chưa tạo được tệp riêng; chưa đổi mật khẩu.');
            }
            try {
                if (fwrite($stream, $text) !== strlen($text)) {
                    throw new \RuntimeException('Chưa ghi đủ thông tin đăng nhập; giao dịch đổi mật khẩu được hủy.');
                }
            } finally {
                fclose($stream);
            }

            return $accounts->count();
        });
        $this->info('Đã bảo vệ '.$count.' tài khoản mẫu. Thông tin mới lưu trong tệp riêng; không in mật khẩu ra màn hình.');

        return self::SUCCESS;
    }
}
