<?php

namespace App;

use Carbon\CarbonImmutable;

class AuditPresenter
{
    public function present(object $row): object
    {
        $actions = [
            'booking.created' => 'Tạo lịch sân', 'booking.rescheduled' => 'Đổi sân hoặc giờ chơi',
            'booking.payment' => 'Ghi nhận thu tiền sân', 'booking.refund' => 'Ghi nhận hoàn tiền trước đây',
            'booking.cancelled' => 'Hủy lịch chưa thanh toán', 'booking.checked_in' => 'Xác nhận khách nhận sân',
            'booking.completed' => 'Chốt hóa đơn buổi chơi', 'booking.services_added' => 'Thêm dịch vụ vào hóa đơn sân',
            'payos.received' => 'Ngân hàng xác nhận chuyển khoản', 'payos.checkout_created' => 'Tạo mã thanh toán PayOS cho khách',
            'payos.settings_updated' => 'Cập nhật kết nối PayOS', 'email.settings_updated' => 'Cập nhật email gửi thư',
            'email.test_sent' => 'Gửi thư kiểm tra kết nối', 'firebase.settings_updated' => 'Cập nhật kết nối Google',
            'google.login' => 'Đăng nhập bằng Google', 'google.linked' => 'Liên kết tài khoản Google',
            'user.created' => 'Tạo tài khoản', 'user.updated' => 'Cập nhật tài khoản', 'user.role_changed' => 'Thay đổi vai trò',
            'bank.matched' => 'Đối chiếu khoản chuyển ngân hàng', 'wallet.topup' => 'Ghi nhận nạp số dư tại quầy',
            'wallet.payos_topup' => 'Nạp số dư bằng chuyển khoản', 'wallet.booking' => 'Sử dụng số dư cho lịch sân',
            'booking.series_cancel' => 'Hủy các buổi chưa thanh toán trong chuỗi', 'booking.series_move' => 'Đổi lịch cố định',
            'membership.activated' => 'Kích hoạt hội viên', 'product.stock_adjusted' => 'Điều chỉnh tồn kho',
            'community.post_saved' => 'Lưu bài cộng đồng', 'community.moderated' => 'Kiểm duyệt bài cộng đồng',
            'ai.moderation_decided' => 'Xử lý đề xuất kiểm duyệt', 'branch.policy' => 'Cập nhật chính sách chi nhánh',
            'permission.updated' => 'Cập nhật quyền nhân sự', 'user.branches' => 'Phân công chi nhánh',
            'user.active' => 'Bật hoặc khóa tài khoản', 'review.visibility' => 'Cập nhật hiển thị đánh giá',
            'event.drawn' => 'Chia bảng giải đấu', 'event.bracket_drawn' => 'Tạo nhánh đấu', 'event.score_recorded' => 'Ghi kết quả trận đấu',
            'bank.settings_updated' => 'Cập nhật thông tin ngân hàng', 'bank.settings_removed' => 'Gỡ thông tin ngân hàng',
        ];
        $subjects = ['booking' => 'Lịch sân', 'user' => 'Tài khoản', 'branch' => 'Chi nhánh', 'court' => 'Sân', 'service' => 'Dịch vụ', 'product' => 'Sản phẩm', 'sale' => 'Hóa đơn bán hàng', 'wallet' => 'Giao dịch số dư', 'payment_attempt' => 'Giao dịch chuyển khoản', 'integration' => 'Kết nối dịch vụ', 'post' => 'Bài cộng đồng', 'membership' => 'Hội viên', 'membership_plan' => 'Gói hội viên', 'coupon' => 'Mã ưu đãi', 'pricing' => 'Bảng giá', 'maintenance' => 'Lịch bảo trì', 'event' => 'Sự kiện', 'match' => 'Trận đấu', 'role' => 'Quyền nhân sự', 'review' => 'Đánh giá'];
        $parts = explode('.', (string) $row->action);
        $verbs = ['created' => 'Tạo', 'updated' => 'Cập nhật', 'saved' => 'Lưu', 'deleted' => 'Gỡ', 'published' => 'Duyệt', 'rejected' => 'Từ chối', 'cancelled' => 'Hủy', 'payment' => 'Thu tiền', 'stock' => 'Điều chỉnh tồn kho', 'status_changed' => 'Đổi trạng thái'];
        $row->action_label = $actions[$row->action] ?? (($verbs[end($parts)] ?? 'Cập nhật').' '.mb_strtolower($subjects[$row->subject_type] ?? 'hoạt động hệ thống'));
        $row->subject_label = ($subjects[$row->subject_type] ?? 'Nội dung').($row->subject_id ? ' #'.$row->subject_id : '');
        $details = is_string($row->details) ? json_decode($row->details, true) : (array) $row->details;
        $summary = $this->summarize(is_array($details) ? $details : []);
        if (isset($details['previous']) && is_array($details['previous'])) {
            $previous = $this->summarize($details['previous']);
            if ($previous) {
                $summary[] = 'Trước thay đổi: '.implode('; ', $previous);
            }
        }
        $row->summary = implode(' · ', $summary) ?: $row->action_label.'. Thao tác đã được lưu vào lịch sử.';

        return $row;
    }

    private function summarize(array $details): array
    {
        $labels = ['amount' => 'Số tiền', 'paid_amount' => 'Đã thu', 'total_amount' => 'Tổng hóa đơn', 'total' => 'Tổng tiền', 'price' => 'Giá', 'hourly_rate' => 'Giá theo giờ', 'booking_paid' => 'Ghi vào tiền sân', 'wallet_credit' => 'Ghi vào số dư', 'method' => 'Cách thanh toán', 'reference' => 'Mã lịch', 'user_id' => 'Khách hàng', 'booking_id' => 'Lịch sân', 'branch_id' => 'Chi nhánh', 'court_id' => 'Sân', 'role' => 'Vai trò', 'status' => 'Trạng thái', 'name' => 'Tên', 'plan' => 'Gói hội viên', 'reason' => 'Lý do', 'notes' => 'Ghi chú', 'quantity' => 'Số lượng', 'delta' => 'Thay đổi tồn kho', 'balance_after' => 'Số dư sau giao dịch', 'project_id' => 'Project Google', 'provider' => 'Dịch vụ xác thực', 'starts_at' => 'Bắt đầu', 'ends_at' => 'Kết thúc', 'active' => 'Đang hoạt động', 'allowed' => 'Cho phép', 'permission' => 'Quyền', 'discount_percent' => 'Giảm giá (%)', 'deposit_percent' => 'Tiền cọc (%)', 'duration_days' => 'Thời hạn (ngày)'];
        $values = ['cash' => 'Tiền mặt tại quầy', 'bank' => 'Chuyển khoản tại quầy', 'payos' => 'Chuyển khoản PayOS', 'wallet' => 'Số dư tài khoản', 'customer' => 'Người chơi', 'admin' => 'Quản trị viên', 'manager' => 'Quản lý chi nhánh', 'staff' => 'Nhân viên', 'cashier' => 'Thu ngân', 'confirmed' => 'Đã tạo lịch', 'cancelled' => 'Đã hủy', 'completed' => 'Đã hoàn tất', 'checked_in' => 'Đang chơi', 'firebase' => 'Google qua Firebase'];
        $summary = [];
        foreach ($details as $key => $value) {
            if (! isset($labels[$key]) || ! is_scalar($value)) {
                continue;
            }
            $money = in_array($key, ['amount', 'paid_amount', 'total_amount', 'total', 'price', 'hourly_rate', 'booking_paid', 'wallet_credit', 'balance_after'], true);
            $display = $money ? number_format((int) $value, 0, ',', '.').' đ' : ($values[(string) $value] ?? mb_substr((string) $value, 0, 250));
            if (in_array($key, ['active', 'allowed'], true)) {
                $display = $value ? 'Có' : 'Không';
            }
            if (in_array($key, ['starts_at', 'ends_at'], true)) {
                try {
                    $display = CarbonImmutable::parse($value)->timezone(config('app.timezone'))->format('H:i d/m/Y');
                } catch (\Throwable) {
                    $display = 'Dữ liệu thời gian cũ cần kiểm tra';
                }
            }
            $summary[] = $labels[$key].': '.$display;
        }

        return $summary;
    }
}
