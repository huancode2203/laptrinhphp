<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Court;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dữ liệu mẫu chỉ dành cho môi trường local hoặc testing.');
        }
        DB::transaction(function (): void {
            $branches = [
                ['Quận 1', '18 Nguyễn Bỉnh Khiêm, Q.1', 6, 120000],
                ['Quận 3', '210 Võ Văn Tần, Q.3', 8, 110000],
                ['Bình Thạnh', '72 Điện Biên Phủ, Bình Thạnh', 12, 100000],
                ['Phú Nhuận', '93 Phan Xích Long, Phú Nhuận', 7, 110000],
                ['Gò Vấp', '45 Quang Trung, Gò Vấp', 10, 95000],
                ['Tân Bình', '110 Cộng Hòa, Tân Bình', 9, 100000],
                ['Thủ Đức', '52 Võ Văn Ngân, Thủ Đức', 14, 90000],
                ['Quận 7', '88 Nguyễn Hữu Thọ, Q.7', 11, 105000],
            ];
            $services = [
                ['Thuê vợt', 'buổi', 30000], ['Cầu Yonex AS-30', 'hộp', 120000],
                ['Nước suối', 'chai', 10000], ['Pocari Sweat', 'chai', 15000],
                ['Thuê giày', 'buổi', 25000], ['Locker', 'buổi', 15000],
                ['Shower', 'lần', 30000], ['Huấn luyện viên', 'buổi', 200000],
            ];
            foreach ($branches as [$district, $address, $count, $rate]) {
                $branch = Branch::firstOrCreate(['district' => $district], [
                    'name' => 'Badminton Hub – '.$district, 'address' => $address, 'amenities' => ['Parking', 'Shower', 'Cafe', 'WiFi'], 'active' => true,
                ]);
                for ($number = 1; $number <= $count; $number++) {
                    Court::firstOrCreate(['branch_id' => $branch->id, 'name' => sprintf('Sân %02d', $number)], ['status' => 'active', 'hourly_rate' => $rate, 'peak_rate' => 180000, 'weekend_rate' => 200000]);
                }
                foreach ($services as [$name, $unit, $price]) {
                    Service::firstOrCreate(['branch_id' => $branch->id, 'name' => $name], ['unit' => $unit, 'price' => $price, 'active' => true]);
                }
            }
            $managerBranch = Branch::where('district', 'Bình Thạnh')->firstOrFail();
            foreach ([['admin', 'Quản trị viên'], ['manager', 'Quản lý Bình Thạnh'], ['customer', 'Khách hàng mẫu']] as [$role, $name]) {
                $user = User::firstOrCreate(['email' => $role.'@badminton.test'], ['name' => $name, 'password' => 'Demo@12345']);
                $user->forceFill(['role' => $role, 'branch_id' => $role === 'manager' ? $managerBranch->id : null])->save();
            }
        });
    }
}
