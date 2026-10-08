# Badminton Hub · Laravel / MySQL / Laragon

Website quản lý sân cầu lông, chuyển từ mã nguồn Figma Make do chủ dự án cung cấp. Giao diện sử dụng HTML/CSS được dựng từ React của bản xuất Figma; Laravel/PHP xử lý nghiệp vụ, phân quyền và toàn bộ giao dịch MySQL.

Nguồn giao diện: [SNCUCAHuNRose](https://github.com/huancode2203/SNCUCAHuNRose), commit `ae1c406618c1f60590640dce41f7a3c17d709ed7`. Thiết kế: [Sân cầu của Huấn Rose](https://www.figma.com/make/8hdgt0w0TDycN12gVlnouv/). Trang chủ giữ bố cục, màu sắc, hình ảnh và CSS của nguồn; các màn hình nghiệp vụ được nối lại với dữ liệu thật. Số liệu, đánh giá, chat, hóa đơn và trạng thái thanh toán không dùng dữ liệu giả của prototype.

## Chạy trên Laragon

Yêu cầu PHP **8.3 trở lên**, MySQL **8.0 trở lên**, Composer 2, Node **22.12 trở lên** hoặc Node 24. Bản này đã chạy với PHP 8.3.33, MySQL 8.4.3, Laravel 13.35.0 và Node 24.

1. Bật MySQL trong Laragon. Mở **Terminal của Laragon** tại thư mục project.
2. Cài thư viện và tạo cấu hình:
   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```
   Nếu đã có `.env`, giữ cấu hình hiện có. Chỉnh DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD theo MySQL của bạn.
3. Tạo database:
   ```sql
   CREATE DATABASE badminton_hub_20261008 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Tạo bảng, dữ liệu danh mục và tài khoản dùng thử:
   ```powershell
   php artisan migrate --seed
   npm ci
   npm run build
   ```
5. Chạy:
   ```powershell
   php artisan serve --host=127.0.0.1 --port=8088
   ```
   Mở **http://127.0.0.1:8088**. Có thể nhấp đúp `chay-website.bat` sau khi cài thư viện. Khi dùng Apache của Laragon, DocumentRoot phải trỏ vào **public**, APP_URL trùng với tên miền local.

Bản ZIP bàn giao có sẵn `public/build`; sau `composer install` và cấu hình database, có thể bỏ qua bước npm nếu không sửa giao diện. Repository Git lưu mã nguồn, cần `npm ci` và `npm run build` khi clone mới.

File `database/badminton_hub.sql` là bản MySQL đầy đủ cấu trúc và dữ liệu danh mục sạch. Có thể import vào database trống để thay cho bước `migrate --seed`; không import đè lên dữ liệu đang sử dụng. Migration là cách cài đặt và nâng cấp được khuyến nghị.

## Tài khoản mẫu

Mật khẩu chung: **Demo@12345**. Seeder chỉ chạy trong local/testing. Chỉ tạo các tài khoản và danh mục chưa tồn tại; không nạp tiền hoặc tạo doanh thu mẫu.

| Email | Quyền |
| --- | --- |
| admin@badminton.test | Quản trị toàn hệ thống |
| manager@badminton.test | Quản lý Bình Thạnh |
| staff@badminton.test | Nhân viên Bình Thạnh |
| cashier@badminton.test | Thu ngân Bình Thạnh |
| customer@badminton.test | Khách hàng |

Quản trị tạo tài khoản nhân viên tại **Quản lý → Tài khoản & quyền**. Khách tự đăng ký. Đổi mật khẩu các tài khoản dùng thử khi dùng dữ liệu thực.

## Chức năng đã triển khai

- Trang chủ, chi nhánh, dịch vụ, lịch sân theo từng ô 30 phút, xem trên điện thoại và máy tính.
- Đăng ký, đăng nhập bằng session, hồ sơ, đổi mật khẩu, xác nhận email và quên mật khẩu bằng mã dùng một lần.
- Đặt sân từ 03:00–23:00, tối đa 6 giờ/lịch và 90 ngày tới. Giá thường, cao điểm, cuối tuần, hội viên, mã ưu đãi và dịch vụ được tính lại ở PHP.
- Giữ chỗ 5 phút, một giữ chỗ đang hoạt động/tài khoản, tối đa 2 lần/30 phút. Đặt trùng booking, bảo trì, giữ chỗ của người khác hoặc sự kiện bị từ chối.
- Đặt cố định cùng sân/giờ hàng tuần từ 2–12 tuần, giảm 15% phí sân. Tạo cả chuỗi và thanh toán bằng ví trong một transaction; có một tuần bị trùng hoặc ví không đủ thì toàn bộ yêu cầu được hoàn tác.
- Lịch cá nhân, hóa đơn từ dữ liệu thật, đổi lịch chưa thanh toán, hủy, đánh giá sau khi hoàn tất, danh sách chờ có thông báo khi sân được giải phóng.
- Ví HUB có sổ giao dịch, nạp tiền tại quầy hoặc MoMo, mua hội viên, trả tiền sân/POS/sự kiện. Gửi lại cùng UUID không thu hoặc cộng tiền lần nữa.
- QR nhận sân được mã hóa ở máy chủ. Nhân viên quét/dán nội dung QR tại trang quản lý; mã không thay thế kiểm tra quyền, giờ chơi và thanh toán.
- Thu tiền mặt, xác nhận chuyển khoản đã nhận, thu phần còn thiếu, hoàn tiền lịch đã hủy, check-in và hoàn tất.
- Quản lý sân/giá, dịch vụ, bảo trì, khách hàng, sản phẩm/SKU, nhập/xuất kho, POS và in hóa đơn. Không bán quá tồn; tên và giá bán được lưu tại thời điểm giao dịch.
- Bảng tin cộng đồng, tìm người chơi theo hồ sơ công khai/trình độ, xin vào đội, người tổ chức duyệt đúng sức chứa, thích, bình luận, báo cáo nội dung và kiểm duyệt.
- Chat riêng và chat đội: chỉ thành viên đọc/gửi được, lưu MySQL, cập nhật định kỳ, thu hồi tin của chính mình.
- Sự kiện giao lưu, lớp học, workshop, giải đấu: khóa sân, kiểm tra sức chứa, vé ví/tại quầy, xác nhận thu tiền, hủy/hoàn. Vé chờ trả tại quầy giữ chỗ 30 phút.
- Giải đấu **loại trực tiếp đơn**, 2–64 người đã xác nhận: bốc thăm, miễn đấu khi lẻ, nhập tỷ số và chuyển người thắng vào vòng sau. Chưa có Swiss/vòng tròn hay hệ thống trọng tài tự động.
- Quản trị chi nhánh, tài khoản/vai trò/trạng thái, hội viên, mã ưu đãi, bài bị báo cáo, đánh giá và nhật ký. Nhân viên/thu ngân chỉ thao tác quầy, quản lý được sửa danh mục của chi nhánh được giao.
- Báo cáo tiền thu/hoàn/doanh thu ròng/công nợ theo khoảng ngày và chi nhánh, gồm lịch sân, POS, hội viên, vé sự kiện; tải CSV. Nạp ví là tăng nghĩa vụ ví, không cộng thành doanh thu bán hàng.

Khách tự hủy lịch chưa thanh toán trước giờ chơi. Lịch trả hoàn toàn bằng ví được tự hoàn ví khi hủy trước tối thiểu 4 giờ. Tiền mặt, ngân hàng và MoMo được nhân viên xử lý hoàn tại quầy, lưu sổ giao dịch. Không tự chuyển tiền ngân hàng hoặc gọi API hoàn tiền MoMo.

## Cấu hình MoMo

Mặc định **tắt**. Có thể dùng các chức năng khác ngay. Khi có merchant, điền **trên máy/server của bạn** vào `.env`:

```dotenv
MOMO_ENABLED=true
MOMO_PARTNER_CODE=
MOMO_ACCESS_KEY=
MOMO_SECRET_KEY=
MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
MOMO_REDIRECT_URL=https://ten-mien-cua-ban/#account
MOMO_IPN_URL=https://ten-mien-cua-ban/api/v1/payments/momo/ipn
```

Sau đó chạy `php artisan optimize:clear`. Production sử dụng endpoint `https://payment.momo.vn/v2/gateway/api/create` với cấu hình merchant production. MoMo cần HTTPS công khai để gửi IPN; địa chỉ 127.0.0.1 của Laragon không nhận được callback từ bên ngoài.

Máy chủ tạo orderId/requestId, ký HMAC-SHA256 và tính số tiền từ booking. IPN xác minh chữ ký cùng merchant/order/request/amount/extraData; giao dịch và transId có khóa chống xử lý lại. Kết quả 9000 chưa được coi là thanh toán hoàn tất. Trang redirect không tự ghi thanh toán. Nếu tiền đến sau khi lịch bị hủy/thay đổi/đã trả bằng cách khác, hệ thống cộng vào ví và lưu trạng thái `credited` thay vì trả sai lịch.

Tài khoản có thể xem giao dịch MoMo và kiểm tra trạng thái ở tab Ví HUB. Kiểm thử MoMo trong project dùng HTTP giả lập; merchant thực và đường IPN thực **chưa được kiểm thử end-to-end** khi chưa có cấu hình. Đối soát bên merchant theo orderId/transId khi cần.

Tài liệu sử dụng: [MoMo – thanh toán thông thường](https://developers.momo.vn/v3/vi/docs/payment/api/wallet/onetime/) và [MoMo – thông báo kết quả](https://developers.momo.vn/v3/vi/docs/payment/api/result-handling/notification/).

Không commit `.env`, access key hoặc secret key.

## Email và QR

Mặc định `MAIL_MAILER=log` để thử local; mã email nằm trong `storage/logs/laravel.log`, không gửi ra hộp thư. Để gửi email thật, dùng `MAIL_MAILER=smtp` và điền MAIL_SCHEME, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS. Mã hết hạn sau 10 phút, khóa sau 5 lần sai; token đổi mật khẩu chỉ dùng một lần và xóa phiên đăng nhập cũ.

QR có thể quét bằng máy quét USB hoặc công cụ quét QR trên điện thoại rồi dán nội dung vào ô check-in. Project chưa truy cập camera trực tiếp trong trình duyệt. Ảnh Unsplash và font Google giữ từ nguồn Figma, cần mạng để tải.

## Phát triển và kiểm thử

```powershell
npm run dev
npm run typecheck
npm run build
npm run format
php vendor/bin/pint --dirty
php artisan test --compact
```

Test mặc định dùng SQLite in-memory. Để kiểm thử MySQL, tạo database riêng và đặt DB_CONNECTION=mysql, DB_DATABASE vào database test trước khi chạy. Test dùng RefreshDatabase; không trỏ test vào database đang sử dụng.

Kiểm tra trên MySQL: giá/scope/booking, giữ chỗ, ví/hoàn, hội viên/mã ưu đãi, chuỗi đặt nguyên tử, POS/tồn kho, sức chứa/nhánh đấu, quyền chat, OTP, tài khoản khóa, tạo nhân viên, chữ ký IPN và xử lý lại giao dịch. Kết quả kiểm thử cuối cùng được ghi trong `docs/VALIDATION.md`.

API cùng origin tại `/api/v1`, JSON và cookie session. Lấy token từ GET `/csrf`; mutation gửi X-CSRF-TOKEN. Chỉ đường IPN MoMo được miễn CSRF và phải có chữ ký hợp lệ. Xem danh sách API bằng `php artisan route:list --path=api/v1`.

Mã nguồn và ZIP không chứa vendor, node_modules, khóa ứng dụng, merchant, session hay nhật ký riêng. Chạy theo các bước cài đặt ở trên. Khi đưa lên máy chủ thật, đặt APP_ENV=production, APP_DEBUG=false, HTTPS, tài khoản MySQL của ứng dụng và cấu hình SMTP/MoMo thực.
