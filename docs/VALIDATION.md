# Kiểm tra bản Laravel / MySQL

- PHP 8.3.33, MySQL 8.4.3, Node 24, Laravel 13.35.0.
- TypeScript kiểm tra tĩnh và Vite production build thành công; Pint chuẩn hóa PHP, npm audit không phát hiện lỗ hổng ở các dependency đã khóa phiên bản.
- PHPUnit: **58/58 bài kiểm thử, 356 assertions** trên SQLite in-memory và database MySQL riêng.
- Kiểm tra qua trình duyệt: đăng nhập khách, đặt sân với dịch vụ, tổng phí 380.000đ (2 giờ cao điểm + 2 chai nước), giữ chỗ 5 phút, hóa đơn/QR, hủy lịch cập nhật trạng thái; đăng nhập quản lý và xem dữ liệu theo chi nhánh.
- Không chạy giao dịch MoMo hay gửi email SMTP thật; test MoMo dùng HTTP fake, test email dùng Mail fake.
- Nguồn Figma Make: ae1c406618c1f60590640dce41f7a3c17d709ed7.
- Không tuyên bố khớp pixel cho mọi màn hình prototype; bố cục và CSS nguồn được giữ, các màn hình nghiệp vụ đã điều chỉnh để hoạt động với PHP/MySQL.
