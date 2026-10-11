# Badminton Hub · Laravel / MySQL / Laragon

Website quản lý sân cầu lông: Laravel/PHP xử lý nghiệp vụ, MySQL lưu dữ liệu, React/HTML/CSS dựng giao diện. Bản nâng cấp có tìm sân bằng ngôn ngữ tự nhiên, duyệt bài bán tự động và các chức năng vận hành.

Nguồn giao diện do chủ dự án cung cấp: [SNCUCAHuNRose](https://github.com/huancode2203/SNCUCAHuNRose), commit `ae1c406618c1f60590640dce41f7a3c17d709ed7`, từ [Figma Make](https://www.figma.com/make/8hdgt0w0TDycN12gVlnouv/). Theo yêu cầu mới, giao diện được làm lại theo phong cách thể thao, nền sáng, xanh rừng/lime, ảnh cầu lông và biểu tượng SVG đồng bộ. Menu điện thoại thu gọn; đặt sân, chat, tài khoản, sự kiện, huấn luyện viên và quản lý dùng cùng hệ thống bố cục. Lịch trống, giá, hóa đơn, điểm, chat và trạng thái thanh toán đọc/tính từ máy chủ.

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
   php artisan db:seed --class=ExpandedDemoSeeder
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

Ba seeder tạo tổng cộng **30 chi nhánh, 186 sân, 95 tài khoản và 1.292 lịch đặt** trên database mới. Tên tài khoản là tên người Việt tự nhiên do bộ dữ liệu tạo; email `.test` và điện thoại dành cho thử nghiệm. Chi nhánh, lịch và khoản thu là dữ liệu demo, chưa phải thông tin sân đang kinh doanh. Khi vận hành, cập nhật địa chỉ, điện thoại, giá, giờ mở và tồn kho thật.

Mật khẩu dùng thử: **Demo@12345**.

| Email | Vai trò |
| --- | --- |
| admin@badminton.test | Quản trị toàn hệ thống |
| manager@badminton.test | Quản lý Bình Thạnh |
| staff@badminton.test | Nhân viên Bình Thạnh |
| cashier@badminton.test | Thu ngân Bình Thạnh |
| customer@badminton.test | Khách hàng |

Seeder tài khoản chỉ dành cho local/testing. Quản trị tạo nhân sự trong **Tài khoản & quyền**, phân nhiều chi nhánh và chỉnh ma trận quyền trong **Vận hành & nội dung**.

## Dữ liệu trải nghiệm

Lệnh `php artisan db:seed --class=RealisticDemoSeeder` tạo **đúng 280 bản ghi liên kết**, ngoài danh mục cơ bản: 20 khách, 60 lịch sân, 50 khoản thu, 20 đánh giá, 20 bài cộng đồng, 20 bình luận (gồm trả lời), 8 huấn luyện viên, 8 sự kiện, 8 lịch chặn sân sự kiện, 16 vé, 26 giao dịch vé/hội viên, 8 voucher, 10 gói hội viên đã mua và 6 lịch bảo trì.

Dữ liệu có lịch đã hoàn tất, lịch sắp tới, lịch đã hủy và bài chờ duyệt; ngày được tính theo ngày chạy seeder. Có dữ liệu cho tài khoản `customer@badminton.test`. Khách mới: `player01@badminton.test` đến `player20@badminton.test`, mật khẩu `Demo@12345`. Chạy lại không tạo trùng, không thay thế dữ liệu đã có. Seeder chỉ chạy trong `local` hoặc `testing`; mọi khoản thu đều là dữ liệu mẫu, không gọi cổng thanh toán.

`php artisan db:seed --class=ExpandedDemoSeeder` bổ sung **7.255 bản ghi liên kết**, trong đó **1.232 lịch sân**: lịch hôm nay, lịch hoàn tất, lịch sắp tới, hủy/hoàn, thu cọc/thu đủ, dịch vụ, huấn luyện viên, bảo trì và chờ sân. Có tối thiểu **30 dòng ở 46 bảng nghiệp vụ**, gồm ví/điểm/POS/kho, bài/đội/chat, vé/sự kiện/giải và phản hồi. Giải vòng tròn 8 người có đủ 7 vòng/28 trận. Bảng cấu hình, gói hội viên, migration, phiên, OTP và upload giữ số lượng phù hợp; không tạo thêm khóa hoặc phiên giả để đủ 30 dòng. Khách mới `rally01@badminton.test` đến `rally40@badminton.test`; nhân sự `team01@badminton.test` đến `team30@badminton.test` được phân chi nhánh.

Seeder mở rộng chỉ chạy local/testing, không gọi cổng thanh toán. Chạy lại không tạo trùng và giữ chỉnh sửa của người dùng; chỉ bổ sung các vòng đấu còn thiếu trong bộ fixture cũ chưa có kết quả và thay phần hướng dẫn mặc định cũ. Không reset database đang sử dụng. Hồ sơ hiển thị số buổi hoàn tất, giờ chơi và sân yêu thích từ dữ liệu của chính tài khoản.

SQL đi kèm được xuất từ database cài mới riêng: **63 bảng, 95 tài khoản, 30 chi nhánh, 186 sân, 1.292 booking**, không chứa phiên đăng nhập, upload hoặc thông tin liên kết Google của người dùng trên máy. Không xuất database đang vận hành. SQL chứa ngày cố định tại lúc xuất; dùng seeder trên database mới để có lịch theo ngày cài đặt.

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
AI_SEARCH_MODEL=qwen3:1.7b
AI_MODERATION_PROVIDER=ollama
AI_MODERATION_MODEL=qwen3:1.7b
OLLAMA_URL=http://127.0.0.1:11434
AI_SEARCH_TIMEOUT=60
AI_MODERATION_TIMEOUT=60
AI_AUTO_APPROVE=true
```

Cài [Ollama Windows](https://docs.ollama.com/windows), mở `chay-ai.bat` để bật dịch vụ trên `127.0.0.1:11434` và tải model `qwen3:1.7b` cho cả hai tác vụ. Có thể chạy `chay-ai.bat --full` để tải thêm `qwen3:4b`, rồi đặt riêng `AI_SEARCH_MODEL=qwen3:4b`; tổng dung lượng hai model khoảng 4 GB. Script nhận diện Ollama đã cài hoặc runtime local của workspace này; không sửa `.env`. Model phải hỗ trợ chat và JSON schema. Adapter gọi [Ollama /api/chat](https://docs.ollama.com/api/chat), `stream=false`, `think=false`, context 4096, giữ model 10 phút. Ollama/model **không kèm ZIP**.

Trên máy thực hiện dự án đã bật Ollama v0.40.2, tải xong `qwen3:1.7b` và kiểm tra suy luận thật cho tìm sân/duyệt bài ngày 11/10/2026. Hai tác vụ đang dùng cùng model này; chưa xác nhận tải xong 4b. Khi chạy trên máy khác, đặt provider `ollama` sau khi tải model và kiểm tra đạt. Runtime và dữ liệu model nằm ngoài Git, trong `work/ollama` của workspace.

Ví dụ tìm sân OpenAI, duyệt bài Ollama:

```dotenv
OPENAI_API_KEY=
AI_SEARCH_PROVIDER=openai
AI_SEARCH_MODEL=gpt-4.1-mini
AI_MODERATION_PROVIDER=ollama
AI_MODERATION_MODEL=qwen3:1.7b
```

Có thể đặt `AI_MODERATION_PROVIDER=openai` và `AI_MODERATION_MODEL=gpt-4.1-mini` hoặc model khác hỗ trợ Structured Outputs. Adapter dùng Responses, JSON schema, `store=false`. [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini) hỗ trợ Responses/Structured Outputs. Sau khi sửa `.env` chạy `php artisan optimize:clear`.

Tìm sân gửi câu hỏi và khu vực cho model trích xuất tiêu chí. Model không truy cập SQL hoặc tạo sân/giá; Laravel kiểm tra MySQL, lịch, bảo trì, giữ chỗ, phí và ưu đãi. Model lỗi thì tìm sân dùng bộ phân tích hiện có; kiểm duyệt giữ chờ. Kết quả mơ hồ hoặc độ tin cậy dưới 0,8 cần người duyệt.

Kiểm duyệt gửi nội dung bài/bình luận cho provider đã chọn. `AI_AUTO_APPROVE=false` giữ mọi bài chờ người duyệt; `true` cho đăng bài được đánh giá phù hợp. Không bảo đảm phát hiện mọi cách viết xúc phạm/spam.

## MoMo và chuyển khoản

### Demo môn học và ngân hàng cá nhân

`.env.example` bật `DEMO_ENABLED=true`. Demo chỉ hoạt động trong `APP_ENV=local/testing`; dịch vụ đủ cấu hình thật được ưu tiên. Khi thiếu merchant, MoMo có màn hình mô phỏng thu cọc/thu đủ/nạp ví; thiếu ngân hàng có mô phỏng chuyển khoản. Mỗi giao dịch ghi nhãn demo, chống ghi lặp, không chuyển tiền thật. Google có tài khoản khách mẫu; email/SMS có mã thử hiển thị trên màn hình, không đánh dấu email/số điện thoại đã xác thực thật. Khôi phục demo khi chưa đăng nhập chỉ dành cho tài khoản email `.test` và cùng phiên trình duyệt.

Để nhận chuyển khoản cá nhân: đăng nhập admin → **Quản lý → Vận hành & nội dung → Kết nối dịch vụ → Tài khoản nhận chuyển khoản**, chọn ngân hàng, nhập số tài khoản và tên chủ tài khoản thật. Khách nhận VietQR/nội dung riêng của lịch; quầy đối chiếu sao kê rồi ghi khoản ngân hàng thực nhận. Luồng thủ công không cần merchant MoMo hoặc SePay. Chưa nhập tài khoản thì không tạo QR ngân hàng giả.

Thông tin ngân hàng ở trang quản trị lưu trong database; bộ `BANK_*` đầy đủ trong `.env` có ưu tiên. Muốn xác nhận tự động bằng SePay, điền bộ `BANK_*`, `SEPAY_ENABLED` và khóa webhook trong `.env`. [Kết nối MB API của SePay](https://docs.sepay.vn/ket-noi-mb-api.html) hỗ trợ cá nhân; điều kiện ngân hàng khác cần kiểm tra riêng.

Email thật dùng SMTP ở mục dưới. Thiếu SMTP có OTP demo; đã cấu hình SMTP mà gửi lỗi sẽ báo lỗi và thu hồi mã, không giả vờ đã gửi. Số dư và báo cáo trong bản môn học là dữ liệu thử; dùng dữ liệu sạch, `DEMO_ENABLED=false` khi vận hành thật.

Danh sách đầy đủ chức năng, lỗi đã sửa và phần còn thiếu theo từng vai trò: [Báo cáo rà soát project](docs/PROJECT_AUDIT.md).

### Merchant MoMo tùy chọn

`MOMO_ENABLED` mặc định tắt adapter thật. Nếu cần merchant, điền `.env` riêng:

```dotenv
MOMO_ENABLED=true
MOMO_PARTNER_CODE=
MOMO_ACCESS_KEY=
MOMO_SECRET_KEY=
MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
MOMO_REDIRECT_URL=https://ten-mien-cua-ban/#account
MOMO_IPN_URL=https://ten-mien-cua-ban/api/v1/payments/momo/ipn
```

Đăng ký [MoMo for Business](https://business.momo.vn/), hoàn thiện [hồ sơ merchant](https://developers.momo.vn/v3/vi/docs/payment/onboarding/merchant-profile/). Trong **Thông tin tích hợp**, lấy Partner Code, Access Key, Secret Key của môi trường thử nghiệm. Dùng đúng bộ khóa và endpoint cùng môi trường. Chuyển sang chính thức cần MoMo duyệt merchant/UAT theo quy trình của họ.

Production dùng `https://payment.momo.vn/v2/gateway/api/create` và merchant production. IPN cần HTTPS công khai, localhost không nhận callback ngoài. Số tiền cọc do server tính; kiểm tra chữ ký/merchant/order/request/amount/extraData/transId. Redirect không tự ghi thanh toán; 9000 chưa coi là hoàn tất. Cọc được ghi nhận một phần vào lịch; chỉ tiền dư hoặc tiền đến khi lịch không còn hiệu lực mới vào ví. Nút **Kiểm tra thanh toán MoMo** gọi API truy vấn giao dịch từ máy chủ, không coi redirect hoặc thao tác khách là đã thanh toán. Query và IPN dùng cùng cơ chế ghi tiền một lần.

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

Đăng ký [SePay](https://my.sepay.vn/), thêm tài khoản ngân hàng của bạn, mở **Webhook** và tạo webhook giao dịch **Có tiền vào**. Chọn xác thực **API Key**, đặt khóa riêng và điền cùng giá trị vào `SEPAY_WEBHOOK_KEY`; đây là khóa webhook, không phải token API quản lý tài khoản. Điền số tài khoản, tên chủ tài khoản và mã BIN đúng ngân hàng. Có thể bắt đầu bằng [Test Mode](https://docs.sepay.vn/test-mode.html).

SePay gửi tới `https://ten-mien/api/v1/payments/bank/webhook`, header `Authorization: Apikey <khóa riêng>`. Chỉ ghi tiền đến đúng tài khoản, dedupe mã giao dịch, khớp mã lịch `BH-...`, tiền dư/muộn vào ví. Sai nội dung nằm ở hàng đối soát cho quản trị ghép lịch một lần. Quầy vẫn có thể ghi khoản ngân hàng thực nhận bằng thao tác thu tiền. Chi tiết lịch có VietQR với tiền cọc/còn thiếu và nội dung riêng, tự kiểm tra trạng thái mỗi 15 giây khi còn chờ thanh toán. QR dùng [quick link VietQR](https://www.vietqr.io/en/danh-sach-api/link-tao-ma-nhanh/), cần mạng; thông tin chuyển khoản dạng chữ vẫn hiển thị nếu ảnh không tải. Mẫu compact2 dành cho thử nghiệm; khi dùng quick link ở production cần mẫu riêng theo yêu cầu VietQR.

## Google, SMS, email và camera

**Đăng nhập Google**

Firebase Authentication là phương thức Google ưu tiên. Project môn học **`badminton-hub-huan-20261011`**, ứng dụng **Badminton Hub Web**, dùng SDK Firebase chính thức ở trình duyệt; Laravel xác minh chữ ký, project, thời hạn, provider, trạng thái và thu hồi tài khoản trước khi tạo phiên. MySQL tiếp tục lưu hồ sơ, quyền và toàn bộ nghiệp vụ. Tài khoản Google mới luôn là khách hàng; email trùng tài khoản cũ cần đăng nhập bằng mật khẩu rồi liên kết trong Hồ sơ để tránh chiếm tài khoản.

Đổi sang project Firebase của bạn: bật Google trong Authentication, cho phép `localhost` và `127.0.0.1` (không thêm cổng), rồi nhập cấu hình Web vào **Quản lý → Vận hành & nội dung → Kết nối dịch vụ → Firebase Google** hoặc bốn biến `.env`:

```dotenv
FIREBASE_API_KEY=
FIREBASE_AUTH_DOMAIN=
FIREBASE_PROJECT_ID=
FIREBASE_APP_ID=
```

Bộ bốn giá trị Web trong `.env.example` là cấu hình client công khai của project môn học; [Firebase API key không phải khóa quản trị](https://firebase.google.com/docs/projects/api-keys). Không đưa service-account private key, OAuth secret, ID token hoặc refresh token vào Git. Bộ `.env` đầy đủ có ưu tiên hơn cấu hình lưu tại quản trị. [Đăng nhập Google với Firebase](https://firebase.google.com/docs/auth/web/google-signin).

Nếu tiếp tục dùng OAuth Google trực tiếp đã có từ bản trước, bỏ bộ Firebase và cấu hình:

1. Mở [Google Cloud](https://console.cloud.google.com/auth/clients), tạo project.
2. Trong **Google Auth Platform**, cấu hình Branding, Audience (External nếu dùng Gmail cá nhân) và thêm email của bạn vào Test users khi đang thử nghiệm.
3. Mở **Clients → Create client → Web application**, đặt tên Badminton Hub.
4. Thêm **Authorized redirect URI** chính xác: `http://127.0.0.1:8088/auth/google/callback`. Không thêm `#account`, không đổi `127.0.0.1` thành `localhost` nếu đang truy cập địa chỉ trên.
5. Lấy Client ID và Client Secret của client vừa tạo, điền ba biến dưới vào `.env` riêng. Lưu secret lúc tạo theo hướng dẫn Google, không đưa JSON credentials vào Git.

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8088/auth/google/callback
```

Ứng dụng chỉ yêu cầu `openid email profile`, không cần Gmail API. Tài khoản trùng email cần đăng nhập bằng mật khẩu rồi **Liên kết Google** trong Hồ sơ. Lỗi kết nối quay về đăng nhập với thông báo; không chuyển nhầm sang tài khoản khác khi liên kết. [Hướng dẫn Google OAuth](https://developers.google.com/identity/protocols/oauth2/web-server#creatingcred).

**Email SMTP bằng Gmail**

1. Trong tài khoản Google, bật **Xác minh 2 bước**.
2. Mở [Mật khẩu ứng dụng](https://myaccount.google.com/apppasswords), tạo mật khẩu dành riêng cho Badminton Hub.
3. Điền SMTP như bên dưới; `MAIL_PASSWORD` là mật khẩu ứng dụng, không dùng mật khẩu đăng nhập Gmail. Nếu nhà cung cấp khác, dùng thông tin SMTP của họ.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=dia-chi-gmail-cua-ban
MAIL_PASSWORD=mat-khau-ung-dung-cua-ban
MAIL_FROM_ADDRESS=dia-chi-gmail-cua-ban
MAIL_FROM_NAME="Badminton Hub"
MAIL_TIMEOUT=15
```

Các giá trị `dia-chi-...`/`mat-khau-...` chỉ là chỗ cần thay, không phải thông tin dùng được. [Google giải thích mật khẩu ứng dụng](https://support.google.com/accounts/answer/185833): cần xác minh 2 bước, một số tài khoản tổ chức/Advanced Protection không có lựa chọn này. [Cấu hình Gmail SMTP](https://support.google.com/a/answer/176600).

`MAIL_MAILER=log` không gửi email thật và không ghi mã sống vào nhật ký. Khi bật demo local, mã thử hiện trên giao diện; khi tắt demo, cần SMTP để dùng luồng gửi thư. Mã hết hạn 10 phút, khóa sau 5 lần sai; gửi lỗi thu hồi mã, token đặt lại mật khẩu dùng một lần và xóa phiên cũ.

**SMS OTP qua Twilio Verify**

1. Đăng ký [Twilio Console](https://console.twilio.com/), lấy **Account SID** và **Auth Token**.
2. Vào **Verify → Services → Create Service**, lấy **Service SID** bắt đầu bằng `VA`, cấu hình mã 6 chữ số.
3. Bật kênh SMS và kiểm tra quyền gửi đến Việt Nam (Verify Geo Permissions). Tài khoản trial chỉ gửi được theo giới hạn thử nghiệm của Twilio; có thể cần xác minh số nhận/nâng cấp trước khi gửi thật.
4. Điền cấu hình, sau đó vào Hồ sơ, gửi mã tới chính số điện thoại của bạn và nhập mã nhận được.

```dotenv
SMS_ENABLED=true
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_VERIFY_SERVICE_SID=
```

[Twilio Verify](https://www.twilio.com/docs/verify/quickstarts/go) gửi/kiểm tra OTP thật, số Việt Nam được chuyển sang dạng `+84`. Có 60 giây chờ gửi lại, tối đa 5 lần kiểm tra trong 10 phút. Đổi số xóa xác thực cũ. Sau xác thực thật có thể đăng nhập điện thoại + mật khẩu. Thiếu cấu hình và bật demo local thì có mã thử trên màn hình; mã này không xác thực số thật.

**Kiểm tra sau khi điền cấu hình**

```powershell
php artisan optimize:clear
php artisan hub:integrations
php artisan hub:integrations --probe
php artisan hub:integrations --email-to=email-nhan-thu-cua-ban
```

Lệnh mặc định chỉ đọc trạng thái, không lộ khóa và không gọi dịch vụ. `--probe` gọi thật hai model và chỉ báo đạt khi dùng kết quả model đúng; `--email-to` gửi một thư SMTP tới địa chỉ bạn chỉ định. SMTP chấp nhận thư chưa chứng minh hộp thư nhận được; kiểm tra Inbox/Spam. Lệnh trả mã lỗi khi kiểm tra không đạt.

Quản trị mở **Quản lý → Vận hành & nội dung → Kết nối dịch vụ** để xem biến còn thiếu, hướng dẫn lấy thông tin và kiểm tra model. **Đã điền cấu hình** chỉ là đủ trường cần thiết, không phải bằng chứng dịch vụ đã kết nối thành công. MoMo/SePay cần endpoint HTTPS mà dịch vụ truy cập được để thử IPN/webhook; `127.0.0.1` của bạn không nhận thông báo từ máy chủ bên ngoài. Bản này chưa xuất bản công khai.

Camera QR chỉ bật sau thao tác quét và cho phép, cần HTTPS hoặc localhost. Vẫn có ô nhập/dán QR. Một số ảnh/font nguồn cần mạng; hình sân ở trang chủ dựng SVG/CSS.

**Firebase Google đã bật trên project thật, đã xác nhận provider/miền và kết nối HTTPS từ PHP; chưa thử thủ công một lượt Google sign-in tới phiên Laravel hoàn chỉnh.** Ollama đã có kết quả suy luận thật cho hai tác vụ trên một số câu thử. SMS, SMTP, SePay, merchant MoMo và OAuth Google trực tiếp chưa được xác minh với tài khoản/dịch vụ thật do chưa có bộ cấu hình riêng. Kiểm thử dùng phản hồi giả lập không chứng minh SMS, email hoặc giao dịch thật đã chạy. Chưa xuất bản hosting công khai.

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
