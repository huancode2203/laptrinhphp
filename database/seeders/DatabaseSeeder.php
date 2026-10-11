<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Court;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FeatureDefaultsSeeder::class);
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
                if (! $branch->phone) {
                    $phones = ['Quận 1' => '028 3822 1108', 'Quận 3' => '028 3930 8847', 'Bình Thạnh' => '028 3514 2233', 'Phú Nhuận' => '028 3845 7762', 'Gò Vấp' => '028 3894 5521', 'Tân Bình' => '028 3812 3340', 'Thủ Đức' => '028 3720 8811', 'Quận 7' => '028 5416 9203'];
                    $branch->update(['phone' => $phones[$district]]);
                }
                for ($number = 1; $number <= $count; $number++) {
                    Court::firstOrCreate(['branch_id' => $branch->id, 'name' => sprintf('Sân %02d', $number)], ['status' => 'active', 'hourly_rate' => $rate, 'peak_rate' => 180000, 'weekend_rate' => 200000]);
                }
                foreach ($services as [$name, $unit, $price]) {
                    Service::firstOrCreate(['branch_id' => $branch->id, 'name' => $name], ['unit' => $unit, 'price' => $price, 'active' => true]);
                }
                foreach ([['WTR-001', 'Aquafina 500ml', 'Nước uống', '💧', 10000, 48], ['WTR-002', 'Pocari Sweat 500ml', 'Nước uống', '🥤', 15000, 24], ['SHT-001', 'Yonex AS-30 (12 quả)', 'Cầu lông', '🏸', 120000, 15], ['SHT-003', 'RSL Tourney (6 quả)', 'Cầu lông', '🏸', 55000, 30], ['RAC-001', 'Vợt Yonex Astrox 88S', 'Vợt', '🎾', 2800000, 3], ['ACC-001', 'Grip cuộn Yonex AC102', 'Phụ kiện', '🎀', 35000, 42]] as [$sku,$name,$category,$icon,$price,$stock]) {
                    Product::firstOrCreate(['branch_id' => $branch->id, 'sku' => $sku], compact('name', 'category', 'icon', 'price', 'stock') + ['active' => true]);
                }
            }
            foreach ([['Silver', 199000, 5], ['Gold', 399000, 10], ['VIP', 799000, 20]] as [$name,$price,$discount]) {
                MembershipPlan::firstOrCreate(['name' => $name], ['price' => $price, 'discount_percent' => $discount, 'duration_days' => 30, 'active' => true]);
            }
            $managerBranch = Branch::where('district', 'Bình Thạnh')->firstOrFail();
            foreach ([['admin', 'Nguyễn Đức Hùng'], ['manager', 'Trần Hoàng Phúc'], ['staff', 'Lê Ngọc Mai'], ['cashier', 'Phạm Khánh Linh'], ['customer', 'Nguyễn Minh Anh']] as [$role, $name]) {
                $user = User::firstOrCreate(['email' => $role.'@badminton.test'], ['name' => $name, 'password' => 'Demo@12345']);
                $user->forceFill(['role' => $role, 'branch_id' => in_array($role, ['manager', 'staff', 'cashier'], true) ? $managerBranch->id : null])->save();
            }
        });
    }
}
