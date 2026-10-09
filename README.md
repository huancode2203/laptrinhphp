# Badminton Hub · Laravel / MySQL / Laragon

Website quản lý sân cầu lông: Laravel/PHP xử lý nghiệp vụ, MySQL lưu dữ liệu, React/HTML/CSS dựng giao diện. Bản nâng cấp có tìm sân bằng ngôn ngữ tự nhiên, duyệt bài bán tự động và các chức năng vận hành.

Nguồn giao diện do chủ dự án cung cấp: [SNCUCAHuNRose](https://github.com/huancode2203/SNCUCAHuNRose), commit `ae1c406618c1f60590640dce41f7a3c17d709ed7`, từ [Figma Make](https://www.figma.com/make/8hdgt0w0TDycN12gVlnouv/). Theo yêu cầu mới, giao diện được điều chỉnh theo phong cách thể thao, xanh đậm/lime. Lịch trống, giá, hóa đơn, điểm, chat và trạng thái thanh toán đọc/tính từ máy chủ.

## Chạy trên Laragon

Yêu cầu PHP **8.3+**, MySQL **8.0+**, Composer 2, Node **22.12+** hoặc Node 24. Bật PHP `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `fileinfo`, `dom`, `gd`, **`zip`**. Đã kiểm tra với PHP 8.3.33, MySQL 8.4.3, Laravel 13.35.0 và Node 24.

1. Bật MySQL của Laragon, mở Terminal tại project.
2. Cài thư viện, tạo cấu hình nếu chưa có:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   ```

   Giữ `.env` hiện có khi nâng cấp. Điền `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

3. Tạo database trống:

   ```sql
   CREATE DATABASE badminton_hub_20261008 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Cài bảng và danh mục, biên dịch giao diện:

   ```powershell
   php artisan migrate --seed
   php artisan db:seed --class=RealisticDemoSeeder
   npm ci
   npm run build
   ```

5. Chạy trong hai Terminal:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8088
   ```

   ```powershell
   php artisan schedule:work
   ```

   Mở **http://127.0.0.1:8088**. `chay-website.bat` tự khởi động lịch tác vụ ẩn rồi mở website. Với Apache Laragon, DocumentRoot phải là **public**, `APP_URL` trùng địa chỉ truy cập. Khởi động lại Apache sau khi đổi extension PHP.

Scheduler chạy mỗi phút để hủy lịch quá hạn cọc, giải phóng sân, báo danh sách chờ và nhắc trước giờ chơi. Nhắc lịch hiện gửi trong tài khoản, chưa qua SMS/email. Trên server cấu hình gọi `php artisan schedule:run` mỗi phút.

Nâng cấp hệ thống đang có dữ liệu: sao lưu database, chạy `composer install`, `php artisan migrate`, `php artisan db:seed --class=FeatureDefaultsSeeder`, `npm ci`, `npm run build`, `php artisan optimize:clear`. Không chạy lại seeder tài khoản mẫu trên hệ thống đang vận hành.

ZIP có sẵn `public/build` nên có thể bỏ npm nếu không sửa giao diện. Git lưu mã nguồn, clone mới cần biên dịch. `database/badminton_hub.sql` có cấu trúc và dữ liệu mẫu sạch, có thể import vào database **trống** thay cho `migrate --seed`.

## Tài khoản và dữ liệu thử

Seeder tạo 8 chi nhánh, 77 sân, 64 dịch vụ, 48 sản phẩm, gói hội viên, 3 hạng điểm, 2 phần thưởng và hướng dẫn. Đây là **danh mục thử từ thiết kế**, chưa phải xác nhận thông tin sân đang kinh doanh. Khi vận hành, cập nhật địa chỉ, điện thoại, giá, giờ mở và tồn kho thật. Bộ dữ liệu giao dịch dưới đây chỉ dùng để trải nghiệm và kiểm thử, không phải doanh thu kinh doanh.

Mật khẩu dùng thử: **Demo@12345**.

| Email | Vai trò |
| --- | --- |
| admin@badminton.test | Quản trị toàn hệ thống |
| manager@badminton.test | Quản lý Bình Thạnh |
| staff@badminton.test | Nhân viên Bình Thạnh |
| cashier@badminton.test | Thu ngân Bình Thạnh |
| customer@badminton.test | Khách hàng |

Seeder tài khoản chỉ dành cho local/testing. Quản trị tạo nhân sự trong **Tài khoản & quyền**, phân nhiều chi nhánh và chỉnh ma trận quyền trong **Vận hành & nội dung**.

## 280 bản ghi trải nghiệm

Lệnh `php artisan db:seed --class=RealisticDemoSeeder` tạo **đúng 280 bản ghi liên kết**, ngoài danh mục cơ bản: 20 khách, 60 lịch sân, 50 khoản thu, 20 đánh giá, 20 bài cộng đồng, 20 bình luận (gồm trả lời), 8 huấn luyện viên, 8 sự kiện, 8 lịch chặn sân sự kiện, 16 vé, 26 giao dịch vé/hội viên, 8 voucher, 10 gói hội viên đã mua và 6 lịch bảo trì.

Dữ liệu có lịch đã hoàn tất, lịch sắp tới, lịch đã hủy và bài chờ duyệt; ngày được tính theo ngày chạy seeder. Có dữ liệu cho tài khoản `customer@badminton.test`. Khách mới: `player01@badminton.test` đến `player20@badminton.test`, mật khẩu `Demo@12345`. Chạy lại không tạo trùng, không thay thế dữ liệu đã có. Seeder chỉ chạy trong `local` hoặc `testing`; mọi khoản thu đều là dữ liệu mẫu, không gọi cổng thanh toán.

SQL đi kèm được xuất từ database cài mới riêng: 62 bảng, 25 tài khoản, 8 chi nhánh, 77 sân và bộ 280 bản ghi. Không chứa phiên đăng nhập, upload, lịch thử từ database đang vận hành hoặc khóa dịch vụ. SQL chứa ngày cố định tại lúc xuất; dùng seeder trên database mới để có lịch mẫu theo ngày cài đặt.

Các luồng đã đối chiếu với source `huancode2203/SNCUCAHuNRose` và hai chat “Kiểm tra hệ thống đặt sân”, “Kiểm tra tính năng layout”: ma trận công khai chỉ hiện trạng thái; bước chọn sân giữ đúng ngày/giờ; quản lý chọn nhiều ô để đặt nhanh bằng tên/số điện thoại; mở lịch đã đặt từ ma trận theo quyền; POS gộp vào hóa đơn sân và trừ kho một lần; khách theo chi nhánh có lịch/chi tiêu/hội viên; báo cáo toàn hệ thống; thông báo mở đối tượng liên quan/xóa; voucher, bài/trận cá nhân, bình luận lồng nhau và danh sách chờ sự kiện.

HUB AI ghim đầu danh sách chat, lưu lịch sử riêng và nhớ tiêu chí cho câu hỏi tiếp theo. Gợi ý sử dụng giờ trống và giá từ MySQL; khi chọn sân, hệ thống kiểm tra lại lịch. Cấu hình model nằm trong mục dưới.

## Chức năng

- Tìm sân bằng tiếng Việt hoặc ngày/giờ/khu vực/ngân sách; kết quả có sân trống, giá, cọc và chuyển thẳng sang đặt đúng sân.
- Giờ mở, thời hạn đặt trước, cao điểm, giá theo ngày/thứ/sân, giảm lịch cố định cấu hình theo chi nhánh. PHP tính lại phí, hội viên, coupon và dịch vụ.
- Giữ chỗ 5 phút, chống trùng lịch/giữ chỗ/bảo trì/sự kiện. Đặt 30 phút–6 giờ trong một ngày; chuỗi cố định 2–12 tuần được tạo trong transaction.
- Cọc/thu đủ, hạn thanh toán, hủy quá hạn; ví/MoMo/ngân hàng khi đã cấu hình. Đổi lịch chưa trả sang sân cùng chi nhánh, tính lại giá/hạn cọc.
- Lịch cá nhân, hóa đơn PDF, QR nhận sân, hủy/hoàn, đánh giá ảnh và phản hồi chi nhánh, danh sách chờ, chat theo lịch đặt.
- Ví có sổ giao dịch; nạp tại quầy/MoMo, trả sân/POS/hội viên/vé. UUID chống thu, hoàn và cộng điểm lặp.
- Tích 1 điểm/10.000đ đủ điều kiện khi hoàn tất lịch sân hoặc POS; xét hạng theo tổng điểm, đổi quà bằng số dư điểm. Không mua hạng điểm để bỏ qua điều kiện.
- Hồ sơ, avatar, đăng nhập email/điện thoại đã xác thực/Google, OTP email, quên/đổi mật khẩu, xử lý phiên hết hạn.
- Sân yêu thích, bài đã lưu, chặn người dùng, bài ảnh, thích/chia sẻ, bình luận trả lời, tìm đồng đội và duyệt thành viên theo sức chứa.
- Kiểm duyệt model/bộ lọc: bài nghi vi phạm chờ quản trị, lưu lý do/nhãn/độ tin cậy; sửa bài phải kiểm tra lại; bình luận không phù hợp bị từ chối.
- Chat riêng/đội/theo lịch, ảnh/PDF, đang nhập/đã xem/trạng thái hoạt động, thu hồi. SSE cập nhật/kết nối lại; máy chủ kiểm tra thành viên, chi nhánh và chặn.
- Huấn luyện viên có hồ sơ, giá, lịch; đặt buổi tập kiểm tra trùng người dạy/sân. Quản lý cần tạo hồ sơ thật trước khi khách đặt.
- Sự kiện/lớp/giao lưu/giải: vé, sức chứa, khóa sân, hủy/hoàn, bốc thăm/tỷ số. Loại trực tiếp, vòng tròn và Swiss có xếp hạng, tránh lặp cặp Swiss.
- Ma trận sân 30 phút, lịch tuần/tháng, kéo lịch chưa trả sang ngày/sân và xác nhận lại; nhận sân bằng QR nhập/dán hoặc camera khi người dùng cho phép.
- Nhiều chi nhánh, sân, dịch vụ, bảo trì, SKU, nhập/xuất kho, POS, khách nhanh tại quầy, thu/hoàn, quyền theo vai trò.
- Phiếu hỗ trợ/trả lời, FAQ/giới thiệu; blog/tuyển dụng/nhượng quyền do quản trị xuất bản. Chưa có nội dung thì hiển thị trạng thái trống.
- Báo cáo thu/hoàn/doanh thu ròng/công nợ, theo sân/chi nhánh/dịch vụ/sản phẩm, công suất, nguồn đặt, giờ phổ biến; biểu đồ/PDF/XLSX/CSV. Nạp ví không tính là doanh thu bán hàng.
- Nhật ký quản trị, đối soát chuyển khoản sai nội dung, xem và kiểm tra lại yêu cầu hoàn MoMo.

Lịch trả hoàn toàn bằng ví được tự hoàn phần còn lại khi đủ điều kiện hủy. Tiền mặt/ngân hàng do quầy xử lý; MoMo có API hoàn. Không tự chuyển tiền ra ngân hàng.

## OpenAI và Ollama

Hai tác vụ có provider/model **độc lập**, khóa chỉ ở server. Mặc định `local` là bộ phân tích/bộ lọc quy tắc, **không phải model AI đang chạy**.

Ollama cho cả hai tác vụ:

```dotenv
AI_SEARCH_PROVIDER=ollama
AI_SEARCH_MODEL=qwen3:4b
AI_MODERATION_PROVIDER=ollama
AI_MODERATION_MODEL=qwen3:4b
OLLAMA_URL=http://127.0.0.1:11434
AI_TIMEOUT=60
AI_AUTO_APPROVE=true
```

Cài [Ollama Windows](https://docs.ollama.com/windows), tải model bằng `ollama pull qwen3:4b` hoặc model phù hợp bộ nhớ máy. Model phải hỗ trợ chat và JSON schema. Adapter gọi [Ollama /api/chat](https://docs.ollama.com/api/chat), `stream=false`, `think=false`. Ollama/model **không kèm ZIP và chưa cài ở máy này**.

Ví dụ tìm sân OpenAI, duyệt bài Ollama:

```dotenv
OPENAI_API_KEY=
AI_SEARCH_PROVIDER=openai
AI_SEARCH_MODEL=gpt-4.1-mini
AI_MODERATION_PROVIDER=ollama
AI_MODERATION_MODEL=qwen3:4b
```

Có thể đặt `AI_MODERATION_PROVIDER=openai` và `AI_MODERATION_MODEL=gpt-4.1-mini` hoặc model khác hỗ trợ Structured Outputs. Adapter dùng Responses, JSON schema, `store=false`. [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini) hỗ trợ Responses/Structured Outputs. Sau khi sửa `.env` chạy `php artisan optimize:clear`.

Tìm sân gửi câu hỏi và khu vực cho model trích xuất tiêu chí. Model không truy cập SQL hoặc tạo sân/giá; Laravel kiểm tra MySQL, lịch, bảo trì, giữ chỗ, phí và ưu đãi. Model lỗi thì tìm sân dùng bộ phân tích hiện có; kiểm duyệt giữ chờ. Kết quả mơ hồ hoặc độ tin cậy dưới 0,8 cần người duyệt.

Kiểm duyệt gửi nội dung bài/bình luận cho provider đã chọn. `AI_AUTO_APPROVE=false` giữ mọi bài chờ người duyệt; `true` cho đăng bài được đánh giá phù hợp. Không bảo đảm phát hiện mọi cách viết xúc phạm/spam.

## MoMo và chuyển khoản

MoMo mặc định tắt. Điền merchant vào `.env` riêng:

```dotenv
MOMO_ENABLED=true
MOMO_PARTNER_CODE=
MOMO_ACCESS_KEY=
MOMO_SECRET_KEY=
MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
MOMO_REDIRECT_URL=https://ten-mien-cua-ban/#account
MOMO_IPN_URL=https://ten-mien-cua-ban/api/v1/payments/momo/ipn
```

Production dùng `https://payment.momo.vn/v2/gateway/api/create` và merchant production. IPN cần HTTPS công khai, localhost không nhận callback ngoài. Số tiền cọc do server tính; kiểm tra chữ ký/merchant/order/request/amount/extraData/transId. Redirect không tự ghi thanh toán; 9000 chưa coi là hoàn tất. Tiền đến muộn vào ví nếu lịch không còn hiệu lực.

API hoàn giới hạn theo khoản gốc và phần chưa hoàn. Retry chỉ truy vấn kết quả, không gửi khoản hoàn thứ hai. Timeout/từ chối/chưa rõ giữ trạng thái chờ và chặn hoàn trùng; cần đối soát merchant trước khi xử lý tiếp. Không coi lỗi mạng là đã hoàn.

[MoMo thanh toán](https://developers.momo.vn/v3/vi/docs/payment/api/wallet/onetime/), [IPN](https://developers.momo.vn/v3/vi/docs/payment/api/result-handling/notification/), [hoàn tiền](https://developers.momo.vn/v3/docs/payment/api/payment-api/refund/).

Chuyển khoản qua [SePay webhook](https://docs.sepay.vn/tich-hop-webhooks.html):

```dotenv
SEPAY_ENABLED=true
SEPAY_WEBHOOK_KEY=
BANK_ACCOUNT_NUMBER=
BANK_ACCOUNT_NAME=
BANK_BIN=
```

SePay gửi tới `https://ten-mien/api/v1/payments/bank/webhook`, header `Authorization: Apikey <khóa riêng>`. Chỉ ghi tiền đến đúng tài khoản, dedupe mã giao dịch, khớp mã lịch `BH-...`, tiền dư/muộn vào ví. Sai nội dung nằm ở hàng đối soát cho quản trị ghép lịch một lần. Quầy vẫn có thể ghi khoản ngân hàng thực nhận bằng thao tác thu tiền.

## Google, SMS, email và camera

Google: điền `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, đăng ký callback `/auth/google/callback`. Kiểm tra state/email; tài khoản trùng email cần người đã đăng nhập liên kết. [Google OAuth](https://developers.google.com/identity/protocols/oauth2/web-server).

SMS: `SMS_ENABLED=true`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_VERIFY_SERVICE_SID`. OTP do [Twilio Verify](https://www.twilio.com/docs/verify/api/verification-check) xác nhận; sau đó đăng nhập điện thoại + mật khẩu. Đổi số xóa xác thực cũ. Không giả lập OTP SMS trên giao diện.

Email mặc định `MAIL_MAILER=log`, mã nằm ở `storage/logs/laravel.log`, chưa gửi hộp thư. Cấu hình SMTP để gửi thật. Mã hết hạn 10 phút, khóa sau 5 lần sai; token đặt lại mật khẩu dùng một lần và xóa phiên cũ.

Camera QR chỉ bật sau thao tác quét và cho phép, cần HTTPS hoặc localhost. Vẫn có ô nhập/dán QR. Một số ảnh/font nguồn cần mạng; hình sân ở trang chủ dựng SVG/CSS.

**AI thực, OAuth, SMS, SMTP, SePay và MoMo chưa được chạy với dịch vụ thật** vì chưa có model/khóa/merchant. Chưa xuất bản lên hosting công khai.

## Phát triển và kiểm thử

```powershell
npm run dev
npm run typecheck
npm run build
npm run format
php vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Test mặc định SQLite in-memory. MySQL cần database riêng, đặt `DB_CONNECTION=mysql`, `DB_DATABASE=<database test>`, `DB_URL=` trước khi chạy. RefreshDatabase có thể xóa bảng: **không trỏ vào database vận hành**. Kết quả ở `docs/VALIDATION.md`.

API `/api/v1` cùng origin, JSON/cookie; GET `/csrf` lấy token, mutation gửi `X-CSRF-TOKEN`. IPN MoMo/SePay miễn CSRF nhưng xác thực riêng. Danh sách API: `php artisan route:list --path=api/v1`.

Git/ZIP không chứa `.env`, vendor/node_modules, khóa, session, nhật ký/upload riêng. Server thực dùng HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, quyền database riêng và scheduler.
