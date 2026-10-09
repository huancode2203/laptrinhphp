<?php

namespace Database\Seeders;

use App\BookingService;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class RealisticDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Chỉ tạo dữ liệu mẫu trong local hoặc testing.');
        }
        if (! Branch::exists()) {
            $this->call(DatabaseSeeder::class);
        }
        DB::transaction(function (): void {
            $key = 'realistic-demo-v1';
            if (DB::table('demo_data_batches')->where('key', $key)->lockForUpdate()->exists()) {
                $this->command?->info('Bộ 280 bản ghi đã có. Không tạo trùng hoặc sửa dữ liệu hiện tại.');

                return;
            }
            DB::table('demo_data_batches')->insert(['key' => $key, 'counts' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            $counts = [];
            $insert = function (string $table, array $data) use (&$counts): int {
                $counts[$table] = ($counts[$table] ?? 0) + 1;

                return DB::table($table)->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
            };
            $uuid = fn (string $name): string => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'badminton-hub/demo-v1/'.$name);
            $names = ['Nguyễn Hoàng Anh', 'Trần Minh Khoa', 'Lê Bảo Ngọc', 'Phạm Thu Hà', 'Võ Đức Huy', 'Đặng Phương Linh', 'Bùi Gia Bảo', 'Huỳnh Thanh Tâm', 'Đỗ Nhật Minh', 'Ngô Khánh Vy', 'Dương Quốc Hưng', 'Mai Tú Anh', 'Lý Hải Nam', 'Phan Ngọc Hân', 'Trương Minh Quân', 'Hồ Quỳnh Như', 'Lâm Tuấn Kiệt', 'Cao Thảo My', 'Đinh Việt Anh', 'Tạ Minh Thư'];
            $users = [];
            $hash = Hash::make('Demo@12345');
            foreach ($names as $i => $name) {
                $users[] = $insert('users', ['name' => $name, 'email' => sprintf('player%02d@badminton.test', $i + 1), 'phone' => sprintf('080200%04d', $i + 1), 'password' => $hash, 'role' => 'customer', 'active' => true, 'public_profile' => true, 'skill_level' => ['Sơ cấp', 'Trung cấp', 'Nâng cao'][$i % 3], 'email_verified_at' => now()]);
            }
            $demo = User::where('email', 'customer@badminton.test')->firstOrFail();
            $admin = User::where('email', 'admin@badminton.test')->firstOrFail();
            $branches = Branch::with('courts')->orderBy('id')->limit(8)->get();
            if ($branches->count() !== 8) {
                throw new RuntimeException('Cần 8 chi nhánh từ DatabaseSeeder trước khi tạo dữ liệu mẫu.');
            }
            $bookings = [];
            for ($i = 0; $i < 60; $i++) {
                $branch = $branches[$i % 8];
                $court = $branch->courts[$i % 3];
                $owner = $i % 5 === 0 ? $demo->id : $users[$i % 20];
                $start = $i < 36 ? now()->toImmutable()->subDays(1 + intdiv($i, 8))->setTime(17, 0) : now()->toImmutable()->addDays(1 + intdiv($i - 36, 8))->setTime(18, 0);
                $end = $start->addHours(2);
                $engine = app(BookingService::class);
                $engine->assertAvailable($court, $start, $end);
                $quote = $engine->quote($court, $start, $end);
                $id = $insert('bookings', ['reference' => sprintf('DEMO-BH-%03d', $i + 1), 'request_key' => $uuid('booking-'.$i), 'user_id' => $owner, 'court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'status' => $i < 36 ? 'completed' : ($i < 50 ? 'confirmed' : 'cancelled'), 'court_amount' => $quote['court_amount'], 'total_amount' => $quote['total_amount'], 'paid_amount' => $i < 50 ? $quote['total_amount'] : 0, 'source' => $i % 2 ? 'online' : 'counter', 'deposit_amount' => $i % 2 ? (int) ceil($quote['total_amount'] * $branch->deposit_percent / 100) : 0, 'checked_in_at' => $i < 36 ? $start : null, 'notes' => 'Dữ liệu mẫu để trải nghiệm hệ thống', 'created_at' => $i < 36 ? $start->subDay() : now()->subHours(1), 'updated_at' => $i < 36 ? $start->subDay() : now()->subHours(1)]);
                $bookings[] = $id;
                if ($i < 50) {
                    $insert('payments', ['booking_id' => $id, 'received_by' => $admin->id, 'request_key' => $uuid('payment-'.$i), 'kind' => 'payment', 'method' => $i % 2 ? 'bank' : 'cash', 'amount' => $quote['total_amount'], 'notes' => 'Giao dịch mẫu, không qua cổng thanh toán', 'created_at' => $i < 36 ? $start->subDay() : now()->subHours(1), 'updated_at' => $i < 36 ? $start->subDay() : now()->subHours(1)]);
                }
            }
            for ($i = 0; $i < 20; $i++) {
                $booking = Booking::findOrFail($bookings[$i]);
                $insert('reviews', ['booking_id' => $booking->id, 'user_id' => $booking->user_id, 'branch_id' => $booking->court->branch_id, 'rating' => [5, 4, 5, 5, 4][$i % 5], 'content' => ['Sân sáng, mặt sân êm và có chỗ gửi xe.', 'Nhân viên hỗ trợ nhanh, nhóm mình sẽ quay lại.', 'Đặt giờ tiện, sân sạch và thoáng.', 'Buổi chơi vui, dễ tìm bạn cùng trình độ.'][$i % 4]]);
            }
            $posts = [];
            for ($i = 0; $i < 20; $i++) {
                $branch = $branches[$i % 8];
                $posts[] = $insert('community_posts', ['user_id' => $i % 5 === 0 ? $demo->id : $users[$i], 'branch_id' => $branch->id, 'title' => ['Tìm thêm bạn đánh đôi', 'Giao lưu cầu lông cuối tuần', 'Nhóm cầu lông sau giờ làm', 'Chia sẻ cách khởi động trước khi lên sân'][$i % 4].' · '.$branch->district, 'content' => ['Nhóm mình cần thêm hai bạn, trình độ sơ cấp đến trung cấp. Chơi vui, cùng chia tiền sân nhé!', 'Mời mọi người tham gia giao lưu. Mang theo vợt và nước uống, đến sớm 10 phút để khởi động.', 'Đội chơi đều mỗi tuần, chào đón bạn mới và ưu tiên tinh thần thể thao.', 'Mình dành 10 phút xoay khớp, chạy nhẹ và tập bước di chuyển trước buổi chơi. Mọi người có mẹo nào khác không?'][$i % 4], 'type' => ['Tìm người đánh', 'Giao lưu', 'Tuyển thành viên', 'Chia sẻ'][$i % 4], 'skill_level' => $i % 3 ? 'Trung cấp' : 'Sơ cấp', 'starts_at' => now()->addDays(2 + $i % 7)->setTime(18, 0), 'capacity' => 6, 'status' => 'open', 'moderation_status' => $i === 19 ? 'pending' : 'approved', 'created_at' => now()->subHours($i), 'updated_at' => now()->subHours($i)]);
            }
            for ($i = 0; $i < 10; $i++) {
                $parent = $insert('post_comments', ['community_post_id' => $posts[$i], 'user_id' => $users[($i + 2) % 20], 'content' => 'Mình muốn tham gia. Nhóm còn chỗ cho người mới không?']);
                $insert('post_comments', ['community_post_id' => $posts[$i], 'user_id' => CommunityPost::findOrFail($posts[$i])->user_id, 'parent_id' => $parent, 'content' => 'Còn bạn nhé! Bạn gửi yêu cầu tham gia để nhóm duyệt nha.']);
            }
            foreach ($branches as $i => $branch) {
                $insert('coaches', ['branch_id' => $branch->id, 'name' => ['Nguyễn Thành Long', 'Trần Hoàng Phúc', 'Lê Minh Huy', 'Phạm Ngọc Mai', 'Võ Quốc Đạt', 'Huỳnh Bảo Trân', 'Đặng Anh Tuấn', 'Bùi Minh Tâm'][$i], 'bio' => 'Hướng dẫn kỹ thuật cơ bản, bước di chuyển và chiến thuật đánh đôi. Nhận học viên mới và nhóm nhỏ.', 'hourly_rate' => 200000 + $i * 10000, 'active' => true]);
                $start = now()->addDays(7 + $i)->setTime(9, 0);
                $end = $start->copy()->addHours(3);
                $event = $insert('game_events', ['branch_id' => $branch->id, 'created_by' => $admin->id, 'title' => ['Giao lưu cuối tuần', 'Giải đôi phong trào', 'Lớp nền tảng cầu lông', 'Workshop di chuyển'][$i % 4].' · '.$branch->district, 'type' => ['friendly', 'league', 'training', 'workshop'][$i % 4], 'description' => 'Cùng lên sân, giao lưu và nâng cao kỹ năng. Có hướng dẫn khởi động, chia nhóm theo trình độ và nghỉ giữa buổi.', 'format' => $i % 4 === 1 ? 'round_robin' : 'Giao lưu', 'skill_level' => 'Mọi trình độ', 'starts_at' => $start, 'ends_at' => $end, 'capacity' => 16, 'fee' => 50000, 'status' => 'published']);
                $court = $branch->courts->last();
                app(BookingService::class)->assertAvailable($court, $start->toImmutable(), $end->toImmutable());
                $insert('court_blocks', ['court_id' => $court->id, 'game_event_id' => $event, 'starts_at' => $start, 'ends_at' => $end, 'active' => true]);
                foreach ([$demo->id, $users[$i]] as $owner) {
                    $reg = $insert('event_registrations', ['game_event_id' => $event, 'user_id' => $owner, 'amount' => 50000, 'paid_amount' => 50000, 'method' => 'cash', 'status' => 'confirmed']);
                    $insert('commerce_payments', ['branch_id' => $branch->id, 'user_id' => $owner, 'actor_id' => $admin->id, 'subject_type' => 'event_registration', 'subject_id' => $reg, 'kind' => 'payment', 'method' => 'cash', 'amount' => 50000]);
                }
                $insert('coupons', ['code' => 'HUBDEMO'.($i + 1), 'branch_id' => $branch->id, 'kind' => 'percent', 'value' => 10, 'minimum_amount' => 150000, 'max_discount' => 50000, 'usage_limit' => 100, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(60), 'active' => true]);
            }
            $plan = DB::table('membership_plans')->where('price', '>', 0)->orderBy('price')->first();
            for ($i = 0; $i < 10; $i++) {
                $owner = $i === 0 ? $demo->id : $users[$i - 1];
                $branch = $branches[$i % 8];
                $member = $insert('memberships', ['user_id' => $owner, 'membership_plan_id' => $plan->id, 'request_key' => $uuid('membership-'.$i), 'amount' => $plan->price, 'method' => 'cash', 'starts_at' => now()->subDays(2), 'expires_at' => now()->addDays($plan->duration_days - 2)]);
                $insert('commerce_payments', ['branch_id' => $branch->id, 'user_id' => $owner, 'actor_id' => $admin->id, 'subject_type' => 'membership', 'subject_id' => $member, 'kind' => 'payment', 'method' => 'cash', 'amount' => $plan->price, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
            }
            for ($i = 0; $i < 6; $i++) {
                $court = $branches[$i]->courts[3];
                $start = now()->addDays(3)->setTime(10, 0);
                $end = $start->copy()->addHours(2);
                app(BookingService::class)->assertAvailable($court, $start->toImmutable(), $end->toImmutable());
                $insert('maintenances', ['court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'reason' => 'Kiểm tra mặt sân và thay đèn định kỳ', 'status' => 'scheduled']);
            }
            if (array_sum($counts) !== 280) {
                throw new RuntimeException('Sai số lượng bản ghi mẫu.');
            }
            DB::table('demo_data_batches')->where('key', $key)->update(['counts' => json_encode($counts), 'updated_at' => now()]);
            $this->command?->info('Đã tạo đúng 280 bản ghi mẫu liên kết trên 8 chi nhánh.');
        }, 5);
    }
}
