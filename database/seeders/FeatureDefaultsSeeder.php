<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Đổi 100 điểm lấy 10.000đ vào ví', 100, 10000], ['Đổi 500 điểm lấy 60.000đ vào ví', 500, 60000]] as [$name,$points,$wallet]) {
            if (! DB::table('loyalty_rewards')->where('name', $name)->exists()) {
                DB::table('loyalty_rewards')->insert(['name' => $name, 'points' => $points, 'wallet_amount' => $wallet, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach ([['Đồng', 100, 3, 3], ['Bạc', 500, 5, 7], ['Vàng', 1500, 8, 14]] as [$name,$points,$discount,$early]) {
            MembershipPlan::firstOrCreate(['name' => 'Hạng '.$name], ['price' => 0, 'discount_percent' => $discount, 'duration_days' => 365, 'active' => true, 'points_threshold' => $points, 'early_days' => $early]);
        }
        foreach ([
            ['dat-san', 'Đặt sân và giữ chỗ', 'faq', 'Chọn chi nhánh, ngày, khung giờ và sân trống. Kiểm tra tổng tiền trước khi xác nhận. Lịch đặt trực tuyến cần thanh toán phần đặt cọc trong thời hạn hiển thị. Lịch chưa đủ đặt cọc sẽ được tự giải phóng khi hết hạn.'],
            ['huy-lich', 'Hủy lịch và hoàn tiền', 'faq', 'Bạn có thể tự hủy lịch chưa thanh toán trước giờ chơi. Lịch thanh toán hoàn toàn bằng ví có thể tự hủy và nhận lại tiền vào ví trước giờ chơi ít nhất 4 tiếng. Với các phương thức khác, gửi yêu cầu hỗ trợ hoặc liên hệ chi nhánh để đối soát và hoàn tiền.'],
            ['diem-thuong', 'Tích điểm và quyền lợi hội viên', 'faq', 'Mỗi 10.000đ thanh toán cho buổi chơi đã hoàn tất hoặc mua hàng được tích 1 điểm. Đổi điểm thành tiền trong ví tại mục Quyền lợi. Hạng hội viên được xét theo tổng điểm đã tích; ưu đãi phù hợp được áp dụng khi tính giá sân.'],
            ['cong-dong', 'Chơi cùng nhau, tôn trọng nhau', 'faq', 'Đăng bài tìm bạn chơi, tham gia nhóm và trò chuyện. Bài có ngôn từ cần kiểm tra sẽ chờ người quản trị duyệt. Bạn có thể báo cáo nội dung, chặn tài khoản và gỡ bài của mình. Không chia sẻ thông tin riêng của người khác.'],
            ['ve-chung-toi', 'Kết nối sân chơi cầu lông', 'page', 'Badminton Hub giúp bạn tìm khung giờ phù hợp, đặt sân và kết nối người chơi. Xem thông tin từng chi nhánh trước khi lựa chọn; quản lý lịch, thanh toán và yêu cầu hỗ trợ trong tài khoản.'],
        ] as [$slug,$title,$type,$content]) {
            if (! DB::table('content_pages')->where('slug', $slug)->exists()) {
                DB::table('content_pages')->insert(compact('slug', 'title', 'type', 'content') + ['published' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
