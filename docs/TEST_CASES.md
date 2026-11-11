# Bộ test case nghiệp vụ

Có **144 test case** trong [TEST_CASES.csv](TEST_CASES.csv), mở bằng Excel với UTF-8. Lọc theo vai trò, nhóm và ưu tiên.

Kết quả thủ công để Chưa chạy cho tới khi thử trực tiếp; việc có test tự động liên quan không thay thế kiểm tra giao diện, nhận email hoặc chuyển khoản thật.

Chạy P0 trước: quyền tài khoản/chi nhánh, giữ chỗ, giá/cọc, thu tiền, gửi lặp và POS. Tiếp theo chạy các ca Người chơi, Quản lý và Admin; sau đó cộng đồng, chat, sự kiện, học cầu lông, nhập liệu, phân trang và mất mạng.

PHPUnit chỉ dùng SQLite trong bộ nhớ hoặc MySQL badminton_hub_codex_test_20261008. Không reset database ứng dụng. API thanh toán trong test dùng phản hồi ký giả lập; không chuyển tiền thật. Ghi phiên bản, dữ liệu dùng, kết quả thực tế và ảnh cho từng ca thủ công; không ghi mật khẩu, OTP hoặc khóa API.

Xem [BUSINESS_FLOWS.md](BUSINESS_FLOWS.md) về công dụng và giới hạn. Kết quả chạy code được cập nhật trong [VALIDATION.md](VALIDATION.md).

Kiểm thử tự động mới nhất: **214/214 bài, 5.223 assertions trên mỗi SQLite và MySQL test riêng**, ngày 19/10/2026. Danh mục từng bài ở [AUTOMATED_TEST_INDEX.csv](AUTOMATED_TEST_INDEX.csv). Cột kết quả thủ công chỉ cập nhật các ca đã thao tác đủ trên trình duyệt; ca gửi thư hoặc chuyển tiền thật vẫn chờ chủ tài khoản kiểm chứng.

Ngày 11/11/2026 bổ sung các ca thanh toán chuỗi, đối soát thực nhận, ca tiền mặt liên tiếp, nhập kho, đổi hàng, lương, sao lưu và triển khai dưới thư mục con. Các ca Google/SMTP/nhận tiền thật vẫn cần người có tài khoản dịch vụ thực hiện; không đánh dấu đã chạy chỉ dựa vào HTTP fake.

Kết quả 11/11/2026: toàn bộ 239/239 tests trên mỗi SQLite và MySQL; nhóm khôi phục/chạy web sửa cuối được chạy lại 8/8 trên hai DB. CSV tự động ghi kết quả mới nhất cho từng bài. Kiểm tra giao diện công khai đã xác nhận ba vai trò và localhost, không thay cho 144 ca thủ công.
