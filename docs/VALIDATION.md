# Kiểm tra bản Laravel / MySQL · 09/10/2026

- Môi trường: PHP 8.3.33, MySQL 8.4.3, Node 24, Laravel 13.35.0; PHP zip được bật.
- TypeScript kiểm tra tĩnh thành công, Vite build thành công. Trang quản trị, biểu đồ và camera QR tách thành các phần tải khi cần.
- Pint đã chuẩn hóa các file PHP thay đổi. Composer validate/audit và npm audit thành công, không có advisory được báo ở thời điểm kiểm tra.
- PHPUnit: **83/83 bài, 553 assertions**, đạt trên cả SQLite in-memory và MySQL database test riêng. Database chính không dùng cho test; database test không giữ tài khoản/giao dịch sau rollback.
- Kiểm thử mới bao phủ: kết quả tìm sân lấy từ MySQL/giá máy chủ; JSON schema OpenAI và Ollama dùng model độc lập; lỗi model không sinh sân/bài công khai; duyệt lại bài sửa; OTP điện thoại qua provider; OAuth state và bảo vệ liên kết email; cọc/quá hạn; tiền muộn; SePay chống ghi lặp/đối soát; hoàn MoMo truy vấn lại; điểm/hạng/đổi điểm; nhiều chi nhánh/thu hồi quyền; quyền đọc booking riêng; media/chat riêng; huấn luyện viên trùng giờ; vòng tròn/Swiss; báo cáo XLSX/PDF.
- Kiểm tra trình duyệt: đăng nhập khách; câu hỏi hôm nay 13h–15h TP.HCM dưới 300k trả 77 sân từ dữ liệu mẫu, sắp theo giá; chọn Thủ Đức Sân 01 chuyển đúng ngày/giờ/sân; một nước suối đưa tổng phí từ 180.000đ lên 190.000đ, cọc 95.000đ; giữ chỗ, tạo lịch chưa thanh toán, xem QR/hạn cọc, tải PDF và hủy thành công. Không thực hiện thanh toán thật.
- Đã mở quyền lợi/điểm thưởng; quản trị xem báo cáo, tải XLSX/PDF, lịch tuần/tháng, duyệt bài, đối soát. File tải từ trình duyệt có định dạng PDF hợp lệ; XLSX mở được ZIP và parse tất cả XML.
- Đã kiểm tra giao diện điện thoại 390×844 và máy tính. Trang chủ không tràn ngang; các bảng lớn cuộn trong vùng riêng. Điều hướng tài khoản/quản lý nằm ở thanh điều hướng, không phủ nội dung.
- Scheduler local đang chạy, tác vụ hub:maintain thực thi mỗi phút; nhắc lịch DB chỉ phát một lần trong test.
- File database/badminton_hub.sql được xuất từ database cài mới riêng, có 60 bảng, 8 chi nhánh, 77 sân, 5 tài khoản thử. Import thử vào database trống thành công; không có booking, session, thông tin merchant hay giao dịch thử.
- ZIP gồm mã nguồn, SQL, hướng dẫn và public/build; không chứa .env, vendor, node_modules, API key, session, nhật ký hay upload riêng.
- Nguồn Figma Make: ae1c406618c1f60590640dce41f7a3c17d709ed7. Giao diện đã điều chỉnh theo yêu cầu phong cách thể thao mới; không tuyên bố khớp pixel với mọi màn hình prototype.
- **Giới hạn xác minh:** các adapter AI/OpenAI/Ollama, Google, Twilio, SMTP, SePay và MoMo dùng phản hồi giả lập trong test. Chưa có API key, model Ollama cài tại máy, merchant hay callback HTTPS thực; chưa kiểm thử dịch vụ thật hoặc hosting công khai. Camera chưa được cấp quyền/quét phần cứng trong lần kiểm tra này.
- Dữ liệu chi nhánh/sân/địa chỉ/giá seed dành cho thử nghiệm; cần cập nhật thông tin kinh doanh trước khi sử dụng với khách thật. Không tạo huấn luyện viên/bài tin hoặc số liệu doanh thu giả.
