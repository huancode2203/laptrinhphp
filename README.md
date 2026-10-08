# Badminton Hub — Laravel + MySQL trên Laragon

**Trạng thái: phần backend đã chạy và kiểm thử; phần giao diện HTML/CSS theo Figma chưa hoàn tất.**
Trang `/` hiện vẫn là trang chào mặc định của Laravel. Đây chưa phải website hoàn thiện để bàn giao cho khách hàng.

Thiết kế: [Sân cầu của Huấn Rose](https://www.figma.com/make/8hdgt0w0TDycN12gVlnouv/).
Kết nối Figma trả danh sách tệp App.tsx, CustomerHome.tsx, BookingFlow.tsx, ManagerApp.tsx… nhưng không đọc được nội dung nguồn (Unknown resource). Phần giao diện chỉ được triển khai từ phản hồi chi tiết của `get_design_context` theo yêu cầu của chủ dự án. Cần khắc phục việc đọc tài nguyên Figma Make hoặc cung cấp liên kết khung màn hình Figma Design để lấy được phản hồi này, sau đó chuyển sang Blade/HTML/CSS và nối với backend bên dưới.

## Trên máy hiện tại

- Project nằm trong thư mục này, đã có thư viện Composer và khóa ứng dụng.
- PHP 8.3.33, MySQL 8.4.3 và Apache được dùng từ C:\\laragon.
- Laravel Framework 13.35.0.
- Cơ sở dữ liệu: `badminton_hub_20261008`; MySQL tại `127.0.0.1:3306`.
- Backend đang được phục vụ tại `http://127.0.0.1:8088`.
- Sau khi khởi động lại máy: mở Laragon, khởi động MySQL, rồi nhấp đúp `chay-backend.bat`.
- Chỉ đưa thư mục `public` vào DocumentRoot khi cấu hình Apache cho project.
- Bản ZIP là mã nguồn, không chứa vendor, .env hoặc dữ liệu phiên đăng nhập.

## Tài khoản mẫu

Mật khẩu dùng thử chung: `Demo@12345`.

| Tài khoản | Quyền |
| --- | --- |
| admin@badminton.test | Quản trị toàn bộ chi nhánh, tài khoản và phân quyền |
| manager@badminton.test | Quản lý riêng chi nhánh Bình Thạnh |
| customer@badminton.test | Đặt sân và xem/hủy lịch của chính mình |

Dữ liệu mẫu lấy tên, địa chỉ và số sân từ bản xem thử thiết kế: 8 chi nhánh, 77 sân và 8 dịch vụ cho mỗi chi nhánh. Sân mặc định mở 03:00–23:00. Giá thường theo chi nhánh, cao điểm từ 16:00 là 180.000 đồng/giờ, cuối tuần 200.000 đồng/giờ. Giá và trạng thái sân có thể đổi qua API quản lý.

## Chức năng backend đã triển khai

- Đăng ký, đăng nhập, đăng xuất bằng session; mật khẩu được băm.
- Khách hàng không tự gán quyền quản trị hay tạo booking cho người khác.
- Liệt kê chi nhánh, sân, dịch vụ; kiểm tra sân trống theo thời gian.
- Đặt sân theo bước 30 phút, tối đa 6 giờ, trong cùng một ngày.
- Giá được tính tại PHP theo từng nửa giờ; không tin giá hoặc trạng thái do trình duyệt gửi lên.
- Transaction và khóa dòng sân trước khi kiểm tra lịch đặt/bảo trì.
- Lưu giá và tên dịch vụ tại thời điểm đặt; đổi giá sau đó không sửa hóa đơn cũ.
- UUID request_key giúp tránh tạo lại booking hoặc ghi tiền hai lần khi gửi lại cùng yêu cầu.
- Khách hàng chỉ tự hủy lịch chưa thanh toán, trước giờ chơi.
- Quản lý thu tiền mặt hoặc xác nhận chuyển khoản **đã nhận tại quầy**, hoàn tiền và lưu sổ giao dịch.
- Ghi nhận thanh toán không tự thực hiện chuyển tiền qua ngân hàng.
- Check-in yêu cầu trả đủ tiền, từ 30 phút trước giờ chơi đến giờ kết thúc. Hoàn tất khi hết giờ chơi.
- Thêm/sửa sân, khóa sân, cập nhật giá, thêm/sửa/ẩn dịch vụ.
- Lịch bảo trì không chồng lên booking; sân có booking chưa kết thúc không được khóa.
- Lịch sân và báo cáo thu/hoàn tiền, tiền còn thiếu được giới hạn theo chi nhánh quản lý.
- Quản trị sửa chi nhánh và quyền tài khoản; lưu nhật ký các thao tác quan trọng.
- API public chỉ trả khả dụng và giá, không trả dữ liệu khách hàng.

Chưa triển khai: các màn hình Figma, cộng đồng/chat, giải đấu, gói hội viên, OTP/email, QR thực và cổng thanh toán trực tuyến. Những phần này cần nối thêm sau khi đọc được toàn bộ nguồn thiết kế.

## API

Tất cả endpoint ở dưới tiền tố `/api/v1`, cùng origin với Laravel, trả JSON.
Frontend cần gửi cookie session và `Accept: application/json`.

1. GET `/api/v1/csrf` để lấy `csrf_token` và cookie.
2. Với POST/PUT/PATCH, gửi `X-CSRF-TOKEN` từ token hiện hành.
3. POST `/api/v1/login` với email, password.
4. Sau đăng nhập/đăng xuất (session đổi token), GET `/api/v1/csrf` lại.

| Phương thức | Endpoint | Mục đích |
| --- | --- | --- |
| POST | /register | Đăng ký: name, email, phone?, password, password_confirmation |
| POST | /login | Đăng nhập |
| POST | /logout | Đăng xuất |
| GET | /me | Tài khoản đang đăng nhập |
| GET | /branches | Chi nhánh đang hoạt động; district? |
| GET | /branches/{id} | Sân và dịch vụ của chi nhánh |
| GET | /branches/{id}/availability | starts_at và ends_at |
| POST | /courts/{id}/quote | Báo giá với starts_at, ends_at, services? |
| GET | /bookings | Lịch đặt của tài khoản hiện tại, phân trang |
| POST | /bookings | Đặt sân; payload bên dưới |
| GET | /bookings/{id} | Chi tiết booking được phép xem |
| PATCH | /bookings/{id}/status | status: cancelled, checked_in hoặc completed |
| GET | /manage/calendar | date=YYYY-MM-DD, branch_id?, status? |
| GET | /manage/booking-search | reference=BH-... |
| GET | /manage/customers | search (ít nhất 3 ký tự), phục vụ đặt tại quầy |
| POST | /manage/bookings/{id}/payments | amount, method (cash/bank), request_key UUID, kind? (payment/refund), notes? |
| GET/POST | /manage/courts | Liệt kê hoặc thêm sân |
| PUT | /manage/courts/{id} | branch_id, name, status, hourly_rate, peak_rate, weekend_rate |
| GET/POST | /manage/services | Liệt kê hoặc thêm dịch vụ |
| PUT | /manage/services/{id} | branch_id, name, unit, price, active |
| GET/POST | /manage/maintenances | Xem hoặc tạo lịch với court_id, starts_at, ends_at, reason |
| PATCH | /manage/maintenances/{id} | status: completed hoặc cancelled |
| GET | /manage/reports | from/to=YYYY-MM-DD, branch_id?; tối đa một năm |
| POST/PUT | /admin/branches, /admin/branches/{id} | name, district, address, phone?, amenities?, active |
| GET | /admin/users | Danh sách tài khoản |
| PATCH | /admin/users/{id}/role | role, branch_id (bắt buộc cho manager); không tự đổi quyền |

Ví dụ đặt sân (thay ngày bằng ngày tương lai; giờ theo Việt Nam):

```json
{
  "request_key": "9335f83b-7a9e-4f25-a1a8-c931171b2040",
  "court_id": 1,
  "starts_at": "2026-10-12 18:00",
  "ends_at": "2026-10-12 20:00",
  "services": [{"id": 3, "quantity": 2}],
  "notes": "Đánh đôi"
}
```

Khách hàng không gửi user_id. Quản lý đặt tại quầy có thể gửi user_id của khách hàng, nhưng chỉ đặt sân thuộc chi nhánh mình quản lý. Sử dụng UUID mới cho mỗi thao tác mới; khi thử lại cùng thao tác, giữ nguyên UUID và nội dung.

## Khôi phục mã nguồn trên môi trường Laragon khác

Mở Terminal của Laragon, tải project vào thư mục `www`, rồi cài thư viện:

```text
cd C:\laragon\www
git clone https://github.com/huancode2203/laptrinhphp.git
cd laptrinhphp
composer install
copy .env.example .env
php artisan key:generate
```

Tạo database MySQL `badminton_hub_20261008` với utf8mb4, điền đúng DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD trong .env, rồi:

```text
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8088
```

Tệp `database/badminton_hub.sql` là bản cấu trúc và dữ liệu mẫu đã xuất, có thể import vào database trống thay cho migrate/seed. Seeder chỉ chạy trong local/testing.

## Kiểm thử

```text
php artisan test --compact
```

23 bài kiểm thử, 105 assertions, đã đạt trên SQLite và database MySQL thử riêng `badminton_hub_codex_test_20261008`. Database thật của project không bị dùng để chạy RefreshDatabase. Các kiểm thử bao phủ chồng lịch, giá, dịch vụ khác chi nhánh, dữ liệu riêng tư, phân quyền, CSRF, giới hạn đăng nhập, thu tiền lặp, trả thừa, hoàn tiền, báo cáo và bảo trì.
