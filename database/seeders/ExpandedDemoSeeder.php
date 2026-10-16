<?php

namespace Database\Seeders;

use App\BookingService;
use App\CommerceService;
use App\DemoCatalog;
use App\LocationService;
use App\LoyaltyService;
use App\Models\Branch;
use App\Models\GameEvent;
use App\Models\User;
use App\TournamentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class ExpandedDemoSeeder extends Seeder
{
    private array $timestamps = [];

    private array $counts = [];

    private array $players = [];

    private array $coaches = [];

    private array $bookings = [];

    private array $names = ['Nguyễn Minh Khôi', 'Trần Gia Hân', 'Lê Quốc Bảo', 'Phạm Ngọc Diệp', 'Võ Thanh Phong', 'Đặng Bảo Trâm', 'Bùi Tuấn Anh', 'Huỳnh Minh Châu', 'Đỗ Thiên Phúc', 'Ngô Thùy Dương', 'Dương Nhật Hào', 'Mai Khánh Linh', 'Lý Đức Anh', 'Phan Thanh Vy', 'Trương Quốc Minh', 'Hồ Ngọc Ánh', 'Lâm Hải Đăng', 'Cao Ngọc Nhi', 'Đinh Hoàng Nam', 'Tạ Hà My', 'Nguyễn Quang Huy', 'Trần Hoài An', 'Lê Minh Đức', 'Phạm Phương Thảo', 'Võ Anh Khoa', 'Đặng Tú Uyên', 'Bùi Thanh Sơn', 'Huỳnh Kim Ngân', 'Đỗ Bảo Long', 'Ngô Trúc Quỳnh', 'Dương Gia Huy', 'Mai Thanh Hà', 'Lý Hoàng Phúc', 'Phan Bảo Ngân', 'Trương Anh Dũng', 'Hồ Minh Tâm', 'Lâm Khánh Duy', 'Cao Phương Anh', 'Đinh Nhật Quang', 'Tạ Thu Trang'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Chỉ tạo dữ liệu thử trong local hoặc testing.');
        }
        if (! Branch::exists()) {
            $this->call(DatabaseSeeder::class);
        }
        DB::transaction(function (): void {
            if (DB::table('demo_data_batches')->where('key', 'expanded-demo-v2')->lockForUpdate()->exists()) {
                $this->refineFixtures();
                $this->command?->info('Bộ dữ liệu mở rộng đã có; giữ nguyên mọi thay đổi và không tạo trùng.');

                return;
            }
            DB::table('demo_data_batches')->insert(['key' => 'expanded-demo-v2', 'counts' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            $this->renameAccounts();
            $hash = Hash::make('Demo@12345');
            foreach ($this->names as $i => $name) {
                $this->players[] = User::findOrFail($this->add('users', ['name' => $name, 'email' => sprintf('rally%02d@badminton.test', $i + 1), 'phone' => sprintf('080300%04d', $i + 1), 'password' => $hash, 'role' => 'customer', 'active' => true, 'public_profile' => true, 'skill_level' => ['Sơ cấp', 'Trung cấp', 'Cao cấp'][$i % 3]]));
            }
            $this->players[] = User::where('email', 'customer@badminton.test')->firstOrFail();
            $districts = ['Quận 10', 'Quận 11', 'Quận 4', 'Quận 5', 'Quận 6', 'Quận 8', 'Quận 12', 'Bình Tân', 'Tân Phú', 'Nhà Bè', 'Bình Chánh', 'Hóc Môn', 'Củ Chi', 'Thủ Đức', 'Bình Thạnh', 'Tân Bình', 'Quận 7', 'Gò Vấp', 'Thủ Đức', 'Bình Chánh', 'Tân Phú', 'Bình Tân'];
            $locations = ['Thành Thái', 'Lạc Long Quân', 'Khánh Hội', 'An Dương Vương', 'Bình Phú', 'Phạm Hùng', 'Tân Thới Hiệp', 'Tên Lửa', 'Lũy Bán Bích', 'Phú Xuân', 'Phong Phú', 'Bà Điểm', 'Tân Phú Trung', 'An Phú', 'Thanh Đa', 'Bàu Cát', 'Tân Phong', 'Phường An Hội', 'Linh Trung', 'Vĩnh Lộc', 'Sơn Kỳ', 'An Lạc'];
            foreach ($locations as $i => $location) {
                $branch = Branch::findOrFail($this->add('branches', ['name' => 'Badminton Hub – '.$location, 'district' => $districts[$i], 'address' => 'Khu thể thao '.$location.', '.$districts[$i].', TP. Hồ Chí Minh', 'phone' => sprintf('080400%04d', $i + 1), 'amenities' => json_encode(['Parking', 'WiFi', 'Shower']), 'active' => true, 'opens_minute' => 360, 'closes_minute' => 1380]));
                for ($n = 1; $n <= 4 + $i % 3; $n++) {
                    $this->add('courts', ['branch_id' => $branch->id, 'name' => sprintf('Sân %02d', $n), 'status' => 'active', 'hourly_rate' => 90000 + $i % 5 * 10000, 'peak_rate' => 160000 + $i % 3 * 10000, 'weekend_rate' => 180000 + $i % 3 * 10000]);
                }
                foreach ([['Nước suối', 'chai', 10000], ['Thuê vợt', 'buổi', 30000], ['Locker', 'buổi', 15000]] as [$name, $unit, $price]) {
                    $this->add('services', compact('name', 'unit', 'price') + ['branch_id' => $branch->id, 'active' => true]);
                }
                foreach ([['WTR-001', 'Aquafina 500ml', 'Nước uống', 10000, 100], ['ACC-001', 'Grip cuộn Yonex AC102', 'Phụ kiện', 35000, 60]] as [$sku, $name, $category, $price, $stock]) {
                    $this->add('products', compact('sku', 'name', 'category', 'price', 'stock') + ['branch_id' => $branch->id, 'active' => true]);
                }
            }
            $branches = Branch::with('courts')->orderBy('id')->limit(30)->get();
            $admin = User::where('email', 'admin@badminton.test')->firstOrFail();
            foreach ($branches as $i => $branch) {
                $role = ['manager', 'staff', 'cashier'][$i % 3];
                $staff = $this->add('users', ['name' => $this->names[($i + 5) % 40], 'email' => sprintf('team%02d@badminton.test', $i + 1), 'password' => $hash, 'role' => $role, 'branch_id' => $branch->id, 'active' => true]);
                $this->put('user_branches', ['user_id' => $staff, 'branch_id' => $branch->id]);
                $this->coaches[$branch->id] = $this->add('coaches', ['branch_id' => $branch->id, 'name' => $this->names[($i + 12) % 40], 'bio' => 'Hướng dẫn bước di chuyển, kỹ thuật đánh cầu và chiến thuật đánh đôi. Lớp nhóm nhỏ cho người mới và người chơi phong trào.', 'hourly_rate' => 180000 + $i % 4 * 20000, 'active' => true]);
                $this->add('pricing_rules', ['branch_id' => $branch->id, 'name' => 'Ưu đãi sáng thứ Hai', 'weekday' => 1, 'from_minute' => 420, 'to_minute' => 660, 'hourly_rate' => max(60000, $branch->courts->first()->hourly_rate - 10000), 'priority' => 10, 'active' => true]);
                $this->add('coupons', ['code' => sprintf('RALLY%02d', $i + 1), 'branch_id' => $branch->id, 'kind' => $i % 2 ? 'percent' : 'fixed', 'value' => $i % 2 ? 10 : 20000, 'minimum_amount' => 150000, 'max_discount' => 50000, 'usage_limit' => 100, 'starts_at' => now()->subWeek(), 'expires_at' => now()->addDays(60), 'active' => true]);
                $start = now()->toImmutable()->addDays(35 + $i % 5)->setTime(12, 0);
                $last = $branch->courts->last();
                app(BookingService::class)->assertAvailable($last, $start, $start->addHours(2));
                $this->add('maintenances', ['court_id' => $last->id, 'starts_at' => $start, 'ends_at' => $start->addHours(2), 'reason' => ['Vệ sinh và kiểm tra độ bám mặt sân', 'Bảo dưỡng đèn chiếu sáng', 'Thay lưới và kiểm tra cột sân'][$i % 3], 'status' => 'scheduled']);
                $holdStart = now()->toImmutable()->addDays(50)->setTime(7, 0);
                app(BookingService::class)->assertAvailable($last, $holdStart, $holdStart->addHour());
                $this->add('court_holds', ['request_key' => $this->uuid('hold-'.$i), 'user_id' => $this->players[$i]->id, 'court_id' => $last->id, 'starts_at' => $holdStart, 'ends_at' => $holdStart->addHour(), 'expires_at' => now()->subMinutes(20), 'released' => true]);
                for ($day = -10; $day < 10; $day++) {
                    $hours = $day === 0 && $i < 8 ? [7, 9, 13, 16, 18, 20] : [9, 18];
                    foreach ($hours as $slot => $hour) {
                        $this->booking($branch, $admin, $i, $day, $slot, $hour);
                    }
                }
            }
            $this->community($branches, $admin);
            $this->commerce($branches, $admin);
            $this->events($branches, $admin);
            DB::table('demo_data_batches')->where('key', 'expanded-demo-v2')->update(['counts' => json_encode($this->counts), 'updated_at' => now()]);
            $this->command?->info('Đã bổ sung '.array_sum($this->counts).' bản ghi liên kết; '.count($this->bookings).' lịch đặt sân.');
        }, 5);
        app(LocationService::class)->updateDemoAddresses();
        app(DemoCatalog::class)->refreshPrices();
    }

    private function booking(Branch $branch, User $admin, int $branchIndex, int $day, int $slot, int $hour): void
    {
        $n = count($this->bookings);
        $start = now()->toImmutable()->addDays($day)->setTime($hour, 0);
        $end = $start->addHours($slot % 2 ? 2 : 1);
        $court = null;
        foreach ($branch->courts->shuffle($n + 100) as $candidate) {
            try {
                app(BookingService::class)->assertAvailable($candidate, $start, $end);
                $court = $candidate;
                break;
            } catch (ValidationException) {
                continue;
            }
        }
        if (! $court) {
            return;
        }
        $owner = $n % 8 === 0 ? $this->players[40] : $this->players[($n + $branchIndex) % 40];
        $service = DB::table('services')->where('branch_id', $branch->id)->where('name', 'Nước suối')->first();
        $quote = app(BookingService::class)->quote($court, $start, $end, [['id' => $service->id, 'quantity' => 2 + $n % 4]]);
        $status = $end->lte(now()) ? 'completed' : ($start->lte(now()) ? 'checked_in' : 'confirmed');
        $cancel = $n % 11 === 0;
        if ($cancel) {
            $status = 'cancelled';
        }
        $coach = $n % 31 === 1 ? DB::table('coaches')->find($this->coaches[$branch->id]) : null;
        $coachFee = $coach ? (int) round($coach->hourly_rate * $start->diffInMinutes($end) / 60) : 0;
        $total = $quote['total_amount'] + $coachFee;
        $deposit = (int) ceil($total * $branch->deposit_percent / 100);
        $paid = in_array($status, ['completed', 'checked_in'], true) ? $total : ($cancel ? 0 : [$total, $deposit, 0][$n % 3]);
        $created = $start->lt(now()) ? $start->subDays(2) : now()->subMinutes(5);
        $booking = $this->add('bookings', ['reference' => sprintf('RALLY-BH-%04d', $n + 1), 'request_key' => $this->uuid('booking-'.$n), 'user_id' => $owner->id, 'court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'status' => $status, 'court_amount' => $quote['court_amount'], 'service_amount' => $quote['service_amount'] + $coachFee, 'total_amount' => $total, 'paid_amount' => $paid, 'source' => $n % 2 ? 'counter' : 'online', 'deposit_amount' => $deposit, 'payment_expires_at' => $status === 'confirmed' && $paid < $deposit ? now()->addMinutes(30) : null, 'checked_in_at' => in_array($status, ['completed', 'checked_in'], true) ? $start : null, 'notes' => $coachFee ? 'Buổi học kỹ thuật cùng huấn luyện viên' : ['Nhóm bạn đánh đôi', 'Giao lưu sau giờ làm', 'Tập kỹ thuật và di chuyển', 'Buổi chơi cùng gia đình'][$n % 4], 'created_at' => $created, 'updated_at' => $created]);
        $this->bookings[] = $booking;
        foreach ($quote['items'] as $id => $item) {
            $this->add('booking_services', ['booking_id' => $booking, 'service_id' => $id] + $item);
        }
        if ($coachFee) {
            $coach = DB::table('coaches')->find($this->coaches[$branch->id]);
            $serviceId = $this->add('services', ['branch_id' => $branch->id, 'name' => 'Buổi học cùng '.$coach->name.' #'.$booking, 'unit' => 'buổi', 'price' => $coachFee, 'active' => false]);
            $this->add('booking_services', ['booking_id' => $booking, 'service_id' => $serviceId, 'name' => 'Buổi học cùng '.$coach->name, 'quantity' => 1, 'unit_price' => $coachFee, 'amount' => $coachFee]);
            $this->add('coach_sessions', ['coach_id' => $coach->id, 'booking_id' => $booking, 'user_id' => $owner->id, 'starts_at' => $start, 'ends_at' => $end, 'amount' => $coachFee, 'status' => $cancel ? 'cancelled' : 'confirmed']);
        }
        $method = $cancel || $n % 5 === 0 ? 'demo_momo' : ($n % 5 === 1 ? 'bank' : 'cash');
        if ($paid > 0 || $cancel) {
            $amount = $cancel ? $total : $paid;
            $this->add('payments', ['booking_id' => $booking, 'received_by' => $admin->id, 'request_key' => $this->uuid('payment-'.$n), 'kind' => 'payment', 'method' => $method, 'amount' => $amount, 'notes' => 'Giao dịch thử; không chuyển tiền thật', 'created_at' => $created, 'updated_at' => $created]);
            if ($cancel) {
                $this->add('payments', ['booking_id' => $booking, 'received_by' => $admin->id, 'request_key' => $this->uuid('refund-payment-'.$n), 'kind' => 'refund', 'method' => $method, 'amount' => $amount, 'notes' => 'Hoàn tiền dữ liệu thử', 'created_at' => $created->addHour(), 'updated_at' => $created->addHour()]);
            }
            $attempt = null;
            if ($method === 'demo_momo') {
                $attempt = $this->add('payment_attempts', ['request_key' => $this->uuid('attempt-'.$n), 'order_id' => 'DEMO_RALLY_'.$booking, 'user_id' => $owner->id, 'booking_id' => $booking, 'purpose' => 'booking', 'partner_code' => 'DEMO_MOMO', 'amount' => $amount, 'order_info' => 'Thanh toán thử RALLY-BH-'.sprintf('%04d', $n + 1), 'status' => 'paid', 'trans_id' => 'DEMO_RALLY_TRANS_'.$booking, 'expires_at' => $created->addMinutes(30), 'result_code' => 0, 'created_at' => $created, 'updated_at' => $created]);
            }
            if ($method === 'bank') {
                $this->add('integration_transactions', ['provider' => 'demo_bank', 'transaction_id' => 'RALLY_'.$booking, 'booking_id' => $booking, 'amount' => $amount, 'status' => 'paid', 'content' => 'Đối soát dữ liệu thử', 'created_at' => $created, 'updated_at' => $created]);
            }
            if ($cancel && $attempt) {
                $this->add('refund_requests', ['booking_id' => $booking, 'payment_attempt_id' => $attempt, 'request_key' => $this->uuid('refund-'.$n), 'order_id' => 'DEMO_RALLY_REFUND_'.$booking, 'amount' => $amount, 'status' => 'paid', 'message' => 'Đã hoàn tiền trong dữ liệu thử']);
            }
        }
        if ($status === 'completed') {
            app(LoyaltyService::class)->award($owner, $paid, 'expanded-complete-'.$booking, 'Hoàn tất '.sprintf('RALLY-BH-%04d', $n + 1));
            if ($n % 3 === 0) {
                $this->add('reviews', ['booking_id' => $booking, 'branch_id' => $branch->id, 'user_id' => $owner->id, 'rating' => [5, 4, 5, 3][$n % 4], 'content' => ['Mặt sân êm, ánh sáng tốt, nhóm mình chơi rất thoải mái.', 'Đặt sân nhanh, nhân viên hỗ trợ đúng giờ.', 'Sân sạch và có chỗ gửi xe. Sẽ quay lại cùng đồng đội.', 'Giờ cao điểm khá đông, nên đến sớm để khởi động.'][$n % 4], 'visible' => true, 'created_at' => $end->addHour(), 'updated_at' => $end->addHour()]);
            }
        }
        if ($status === 'confirmed' && $n % 4 === 0) {
            $this->add('waitlist_entries', ['court_id' => $court->id, 'user_id' => $this->players[($n + 5) % 40]->id, 'starts_at' => $start, 'ends_at' => $end, 'status' => 'waiting']);
        }
        app(BookingService::class)->audit($admin, 'booking.seeded', $booking, ['batch' => 'expanded-demo-v2'], 'booking');
    }

    private function community(Collection $branches, User $admin): void
    {
        for ($i = 0; $i < 60; $i++) {
            $owner = $i % 8 === 0 ? $this->players[40] : $this->players[$i % 40];
            $branch = $branches[$i % 30];
            $pending = $i % 6 === 0;
            $post = $this->add('community_posts', ['user_id' => $owner->id, 'branch_id' => $branch->id, 'title' => ['Tìm bạn đánh đôi buổi tối', 'Giao lưu cuối tuần', 'Tuyển đồng đội tập cầu', 'Kinh nghiệm chọn vợt nhẹ'][$i % 4].' · '.$branch->district, 'content' => ['Nhóm mình chơi vui, cần thêm hai bạn cùng trình độ. Chia tiền sân theo số người, đến sớm để khởi động nhé.', 'Mời các bạn giao lưu cầu lông. Người mới cũng được chào đón, cùng nhau tập kỹ thuật và chơi an toàn.', 'Tụi mình tập đều hai buổi mỗi tuần. Rất vui được gặp đồng đội có tinh thần thể thao và đúng giờ.', 'Mình đang tập chuyển từ vợt nhẹ sang vợt cân bằng. Mọi người thường chọn độ căng dây thế nào cho người mới?'][$i % 4], 'type' => ['Tìm người đánh', 'Giao lưu', 'Tuyển thành viên', 'Chia sẻ'][$i % 4], 'skill_level' => ['Sơ cấp', 'Trung cấp', 'Cao cấp'][$i % 3], 'starts_at' => now()->addDays(1 + $i % 12)->setTime(18, 0), 'capacity' => 6, 'status' => 'open', 'moderation_status' => $pending ? 'pending' : 'approved', 'moderated_at' => $pending ? null : now(), 'moderated_by' => $pending ? null : $admin->id]);
            if ($pending) {
                continue;
            }
            for ($j = 1; $j <= 2; $j++) {
                $visitor = $this->players[($i + $j + 3) % 40];
                if ($visitor->id === $owner->id) {
                    continue;
                }
                $this->add('post_joins', ['community_post_id' => $post, 'user_id' => $visitor->id, 'status' => $pending || $j === 2 ? 'pending' : 'accepted']);
                $this->add('post_comments', ['community_post_id' => $post, 'user_id' => $visitor->id, 'content' => $j === 1 ? 'Mình chơi ở mức trung cấp, rất muốn giao lưu cùng nhóm!' : 'Nhóm còn chỗ không? Mình có thể đến đúng giờ.']);
                $this->put('post_likes', ['community_post_id' => $post, 'user_id' => $visitor->id]);
                $this->put('saved_posts', ['community_post_id' => $post, 'user_id' => $visitor->id]);
            }
            $this->add('content_reports', ['community_post_id' => $post, 'user_id' => $this->players[($i + 9) % 40]->id, 'reason' => 'Đề nghị kiểm tra thông tin lịch giao lưu', 'status' => 'resolved']);
            $conversation = $this->add('conversations', ['community_post_id' => $post, 'title' => 'Đồng đội · '.$branch->district]);
            $guest = $this->players[($i + 4) % 40];
            foreach ([$owner, $guest] as $member) {
                $this->put('conversation_members', ['conversation_id' => $conversation, 'user_id' => $member->id]);
            }
            $this->add('chat_messages', ['conversation_id' => $conversation, 'user_id' => $owner->id, 'request_key' => $this->uuid('chat-owner-'.$i), 'content' => 'Chào bạn, nhóm mình hẹn tại quầy trước giờ chơi 10 phút nhé.']);
            $this->add('chat_messages', ['conversation_id' => $conversation, 'user_id' => $guest->id, 'request_key' => $this->uuid('chat-guest-'.$i), 'content' => 'Mình xác nhận tham gia. Hẹn gặp mọi người ở sân!']);
        }
        foreach ($this->players as $i => $player) {
            $this->put('favorite_courts', ['user_id' => $player->id, 'court_id' => $branches[$i % 30]->courts->first()->id]);
            if ($i < 30) {
                $this->put('blocked_users', ['user_id' => $player->id, 'blocked_id' => $this->players[($i + 20) % 40]->id]);
            }
            $resolved = $i % 3 === 0;
            $this->add('support_tickets', ['user_id' => $player->id, 'branch_id' => $branches[$i % 30]->id, 'subject' => ['Hỏi thông tin gửi xe', 'Cần hỗ trợ đổi giờ chơi', 'Đăng ký lớp kỹ thuật cơ bản'][$i % 3], 'content' => 'Nhờ chi nhánh hỗ trợ thông tin cho buổi chơi sắp tới. Mình muốn chuẩn bị trước để đến sân đúng giờ.', 'status' => $resolved ? 'resolved' : 'open', 'reply' => $resolved ? 'Chi nhánh đã tiếp nhận và hỗ trợ yêu cầu. Bạn có thể liên hệ quầy khi đến sân.' : null, 'replied_by' => $resolved ? $admin->id : null]);
        }
    }

    private function commerce(Collection $branches, User $admin): void
    {
        $plans = DB::table('membership_plans')->where('price', '>', 0)->where('active', true)->get();
        foreach ($this->players as $i => $owner) {
            $branch = $branches[$i % 30];
            app(CommerceService::class)->wallet($admin, $owner, 500000, 'topup', $this->uuid('wallet-'.$i), 'Nạp ví tại quầy · Dữ liệu thử', $branch->id);
            $plan = $plans[$i % $plans->count()];
            $member = $this->add('memberships', ['user_id' => $owner->id, 'membership_plan_id' => $plan->id, 'request_key' => $this->uuid('membership-'.$i), 'amount' => $plan->price, 'method' => 'cash', 'starts_at' => now()->subDays(3), 'expires_at' => now()->addDays($plan->duration_days - 3)]);
            $this->add('commerce_payments', ['branch_id' => $branch->id, 'user_id' => $owner->id, 'actor_id' => $admin->id, 'subject_type' => 'membership', 'subject_id' => $member, 'kind' => 'payment', 'method' => 'cash', 'amount' => $plan->price]);
            $product = DB::table('products')->where('branch_id', $branch->id)->where('sku', 'WTR-001')->first();
            $sale = $this->add('sales', ['request_key' => $this->uuid('sale-'.$i), 'reference' => 'RALLY-POS-'.sprintf('%03d', $i + 1), 'branch_id' => $branch->id, 'clerk_id' => $admin->id, 'user_id' => $owner->id, 'total_amount' => 20000, 'method' => 'cash']);
            $this->add('sale_items', ['sale_id' => $sale, 'product_id' => $product->id, 'name' => $product->name, 'quantity' => 2, 'unit_price' => $product->price, 'amount' => 20000]);
            DB::table('products')->where('id', $product->id)->decrement('stock', 2);
            $this->add('inventory_adjustments', ['request_key' => $this->uuid('inventory-'.$i), 'product_id' => $product->id, 'user_id' => $admin->id, 'delta' => -2, 'reason' => 'Xuất hàng '.$sale]);
            $this->add('commerce_payments', ['branch_id' => $branch->id, 'user_id' => $owner->id, 'actor_id' => $admin->id, 'subject_type' => 'sale', 'subject_id' => $sale, 'kind' => 'payment', 'method' => 'cash', 'amount' => 20000]);
            app(LoyaltyService::class)->award($owner, 20000, 'expanded-sale-'.$sale, 'Mua hàng RALLY-POS-'.sprintf('%03d', $i + 1));
        }
        foreach ($this->guides() as $i => [$title, $content]) {
            $this->add('content_pages', ['slug' => 'huong-dan-rally-'.($i + 1), 'title' => $title, 'type' => 'faq', 'content' => $content, 'published' => true]);
        }
    }

    private function events(Collection $branches, User $admin): void
    {
        foreach ($branches as $i => $branch) {
            $start = now()->toImmutable()->addDays(20 + $i % 10)->setTime(8, 0);
            $court = $branch->courts->last();
            app(BookingService::class)->assertAvailable($court, $start, $start->addHours(4));
            $league = $i % 2 === 0;
            $event = $this->add('game_events', ['branch_id' => $branch->id, 'created_by' => $admin->id, 'title' => ($league ? 'Giải cầu lông phong trào' : 'Ngày hội giao lưu').' · '.$branch->district, 'type' => $league ? 'league' : 'friendly', 'description' => 'Cùng giao lưu, rèn kỹ thuật và chia sẻ tinh thần thể thao. Có hướng dẫn khởi động và chia nhóm theo trình độ.', 'format' => $league ? 'round_robin' : 'Giao lưu', 'skill_level' => 'Mọi trình độ', 'starts_at' => $start, 'ends_at' => $start->addHours(4), 'capacity' => 16, 'fee' => 50000, 'status' => 'published']);
            $this->add('court_blocks', ['court_id' => $court->id, 'game_event_id' => $event, 'starts_at' => $start, 'ends_at' => $start->addHours(4), 'active' => true]);
            for ($j = 0; $j < 8; $j++) {
                $owner = $this->players[($i + $j) % 40];
                $reg = $this->add('event_registrations', ['game_event_id' => $event, 'user_id' => $owner->id, 'amount' => 50000, 'paid_amount' => 50000, 'method' => 'cash', 'status' => 'confirmed']);
                $this->add('commerce_payments', ['branch_id' => $branch->id, 'user_id' => $owner->id, 'actor_id' => $admin->id, 'subject_type' => 'event_registration', 'subject_id' => $reg, 'kind' => 'payment', 'method' => 'cash', 'amount' => 50000]);
            }
            if ($league) {
                $game = GameEvent::findOrFail($event);
                app(TournamentService::class)->draw($game, $game->registrations()->where('status', 'confirmed')->pluck('user_id')->all());
                $this->counts['tournament_matches'] = ($this->counts['tournament_matches'] ?? 0) + $game->matches()->count();
            }
        }
    }

    private function guides(): array
    {
        return [
            ['Cách chọn giờ sân ít đông', 'Mở Đặt sân, chọn chi nhánh và ngày chơi rồi xem các ô trống trong ma trận. So sánh khung giờ buổi sáng hoặc đầu giờ chiều; giá được hiển thị trước khi xác nhận.'],
            ['Chuẩn bị trước buổi chơi', 'Kiểm tra ngày, giờ, chi nhánh và mã nhận sân trong Lịch của tôi. Mang giày phù hợp, vợt và nước uống; đến sớm để làm thủ tục tại quầy.'],
            ['Gửi xe khi đến sân', 'Xem tiện ích và địa chỉ của chi nhánh trước khi đến. Phí gửi xe và lối vào phụ thuộc từng địa điểm; hỏi chi nhánh qua mục Hỗ trợ nếu cần hướng dẫn.'],
            ['Khởi động cổ tay', 'Khởi động nhẹ cổ tay, vai và khuỷu tay trước khi vào sân. Tăng cường độ từ từ; nếu có đau hoặc chấn thương, dừng chơi và nhờ người có chuyên môn hướng dẫn.'],
            ['Bước di chuyển ngang', 'Tập bước nhỏ và trở về vị trí chuẩn bị sau mỗi lần di chuyển. Chọn buổi học cùng huấn luyện viên nếu muốn được chỉnh động tác trực tiếp.'],
            ['Đánh đôi an toàn', 'Thống nhất cách gọi cầu với đồng đội trước khi chơi. Giữ khoảng cách khi vung vợt và dừng pha cầu nếu có người đi vào sân.'],
            ['Chọn giày phù hợp', 'Dùng giày thể thao phù hợp mặt sân trong nhà và kiểm tra độ bám trước khi chơi. Tránh giày có đế hỏng hoặc để lại vết trên mặt sân.'],
            ['Bảo quản vợt', 'Lau khô vợt sau buổi chơi và cất trong túi ở nơi thoáng. Tránh để vợt trong xe nóng hoặc va chạm mạnh; kiểm tra khung và dây trước khi sử dụng.'],
            ['Chọn độ căng dây', 'Độ căng dây nên phù hợp sức tay, trình độ và giới hạn của khung vợt. Trao đổi với nơi căng dây hoặc huấn luyện viên trước khi đổi mức căng.'],
            ['Luyện giao cầu', 'Tập giao cầu ngắn và dài theo từng nhóm nhỏ, theo dõi độ ổn định trước khi tăng tốc độ. Huấn luyện viên có thể giúp chỉnh tư thế và điểm tiếp xúc cầu.'],
            ['Uống nước giữa buổi', 'Mang theo nước và nghỉ giữa các lượt chơi. Nếu cần mua nước hoặc thuê vợt, chọn dịch vụ khi đặt sân hoặc liên hệ quầy để bổ sung vào hóa đơn.'],
            ['Quy tắc đổi sân', 'Trong Lịch của tôi, mở lịch chưa thanh toán và chọn đổi lịch nếu còn đủ điều kiện. Sân mới phải cùng chi nhánh, còn trống; hệ thống tính lại giá trước khi bạn xác nhận.'],
            ['Thời hạn thanh toán cọc', 'Xem số tiền cọc và thời hạn ngay trong chi tiết lịch. Lịch chưa đủ cọc sẽ bị hủy khi hết hạn; sau khi chuyển khoản, kiểm tra trạng thái xác nhận trước giờ chơi.'],
            ['Cách kiểm tra lịch đặt', 'Vào Tài khoản → Lịch của tôi để lọc theo trạng thái. Mở từng lịch để xem giờ chơi, sân, khoản đã trả, hóa đơn và mã nhận sân.'],
            ['Tìm đồng đội cùng trình độ', 'Vào Cộng đồng để tìm bài phù hợp khu vực, giờ chơi và trình độ. Gửi yêu cầu tham gia; chủ bài xác nhận theo số chỗ còn lại.'],
            ['Xem lịch sử thanh toán', 'Mở chi tiết lịch để xem các khoản thu và hoàn. Với ví, xem sổ giao dịch trong Ví HUB; đối chiếu số tiền và thời gian trước khi gửi yêu cầu hỗ trợ.'],
            ['Đổi ảnh đại diện', 'Mở Hồ sơ cá nhân, bấm ảnh đại diện rồi chọn ảnh của bạn và lưu. Dùng ảnh rõ mặt; bạn có thể xóa ảnh để trở lại biểu tượng tên viết tắt.'],
            ['Bảo mật tài khoản', 'Dùng mật khẩu riêng cho website và giữ kín mã OTP. Đổi mật khẩu trong Hồ sơ cá nhân khi cần; đăng xuất sau khi dùng máy tính chung.'],
            ['Liên kết Google', 'Đăng nhập tài khoản hiện tại rồi mở Hồ sơ cá nhân → Google để liên kết khi dịch vụ khả dụng. Sau đó bạn có thể dùng cùng tài khoản Google để đăng nhập nhanh.'],
            ['Tích điểm sau buổi chơi', 'Điểm được cộng khi lịch sân đã hoàn tất hoặc mua hàng đủ điều kiện. Xem số dư điểm và lịch sử trong Quyền lợi & đã lưu; khoản nạp ví không tự tạo điểm mua hàng.'],
            ['Sử dụng ưu đãi', 'Nhập mã voucher tại bước xác nhận đặt sân. Hệ thống kiểm tra chi nhánh, thời hạn, giá trị tối thiểu và lượt sử dụng, rồi hiển thị số tiền giảm.'],
            ['Đăng ký sự kiện', 'Mở Giải đấu, chọn sự kiện còn nhận đăng ký và xem lịch, địa điểm, phí. Vé trả phí cần được thanh toán trong thời hạn để xác nhận chỗ.'],
            ['Chờ chỗ sự kiện', 'Nếu sự kiện đã đủ chỗ, tham gia danh sách chờ khi có tùy chọn này. Theo dõi thông báo mời khi có suất trống và xác nhận trong thời hạn ghi trên vé.'],
            ['Học cùng huấn luyện viên', 'Mở Học cầu lông, chọn huấn luyện viên và giờ còn trống. Kiểm tra chi phí sân và buổi tập trong tổng tiền trước khi xác nhận đặt.'],
            ['Gửi phản hồi về sân', 'Sau buổi chơi đã hoàn tất, mở lịch để đánh giá sân. Nếu cần xử lý vấn đề cụ thể, gửi phiếu trong Hỗ trợ kèm chi nhánh, mã lịch và nội dung cần hỗ trợ.'],
        ];
    }

    private function refineFixtures(): void
    {
        foreach ($this->guides() as $i => [$title, $content]) {
            DB::table('content_pages')->where('slug', 'huong-dan-rally-'.($i + 1))->where('content', $title.'. Xem thông tin và trạng thái hiện tại trong tài khoản trước khi xác nhận thao tác. Khi cần hỗ trợ, gửi yêu cầu tới chi nhánh trong mục Hỗ trợ; nhân viên sẽ kiểm tra và phản hồi cho bạn.')->update(['content' => $content, 'updated_at' => now()]);
        }
        $admin = User::where('email', 'admin@badminton.test')->firstOrFail();
        foreach (GameEvent::where('created_by', $admin->id)->where('format', 'round_robin')->where('title', 'like', 'Giải cầu lông phong trào · %')->get() as $event) {
            $matches = $event->matches()->orderBy('position')->get();
            $registered = $event->registrations()->where('status', 'confirmed')->pluck('user_id')->sort()->values();
            if ($matches->count() !== 4 || $matches->contains(fn ($m) => $m->round !== 1 || $m->winner_id !== null || $m->score_a !== null || $m->score_b !== null)
                || $registered->count() !== 8 || User::whereIn('id', $registered)->where('email', 'like', 'rally%@badminton.test')->count() !== 8
                || $matches->flatMap(fn ($m) => [$m->player_a, $m->player_b])->sort()->values()->all() !== $registered->all()) {
                continue;
            }
            $players = [...$matches->pluck('player_a')->all(), ...array_reverse($matches->pluck('player_b')->all())];
            for ($round = 1; $round < 8; $round++) {
                if ($round > 1) {
                    for ($position = 0; $position < 4; $position++) {
                        $event->matches()->create(['round' => $round, 'position' => $position, 'player_a' => $players[$position], 'player_b' => $players[7 - $position]]);
                    }
                }
                $fixed = array_shift($players);
                $last = array_pop($players);
                array_unshift($players, $fixed, $last);
            }
        }
    }

    private function renameAccounts(): void
    {
        foreach ([['admin', 'Quản trị viên', 'Nguyễn Đức Hùng'], ['manager', 'Quản lý Bình Thạnh', 'Trần Hoàng Phúc'], ['staff', 'Nhân viên Bình Thạnh', 'Lê Ngọc Mai'], ['cashier', 'Thu ngân Bình Thạnh', 'Phạm Khánh Linh'], ['customer', 'Khách hàng mẫu', 'Nguyễn Minh Anh'], ['google-demo', 'Người chơi mẫu', 'Võ Gia Hân']] as [$account, $old, $name]) {
            DB::table('users')->where('email', $account.'@badminton.test')->where('name', $old)->update(['name' => $name, 'updated_at' => now()]);
        }
    }

    private function uuid(string $key): string
    {
        return (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'badminton-hub/expanded-v2/'.$key);
    }

    private function add(string $table, array $data): int
    {
        if ($this->timestamps[$table] ??= Schema::hasColumn($table, 'created_at')) {
            $data += ['created_at' => now(), 'updated_at' => now()];
        }
        $this->counts[$table] = ($this->counts[$table] ?? 0) + 1;

        return DB::table($table)->insertGetId($data);
    }

    private function put(string $table, array $data): void
    {
        DB::table($table)->insert($data);
        $this->counts[$table] = ($this->counts[$table] ?? 0) + 1;
    }
}
