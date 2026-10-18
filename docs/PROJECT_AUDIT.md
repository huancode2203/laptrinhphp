# Rà soát toàn bộ Badminton Hub · 17/10/2026

## Email và giao diện tài khoản làm việc · 18/10/2026

Đã bổ sung form Gmail riêng cho Admin, lưu mật khẩu ứng dụng mã hóa và gửi thử về chính địa chỉ gửi. SMTP máy chủ có ưu tiên; API không trả mật khẩu. Thư xác nhận có HTML và text. Trang kết nối bỏ thẻ PayOS/Google lặp, tách cấu hình email, hướng dẫn chuyển khoản tại quầy đúng với cấu hình riêng của PayOS. Form Firebase dùng từng trường và không báo lưu cấu hình khác thành công khi máy chủ đang ưu tiên `.env`.

Hồ sơ làm việc được làm lại với phần nhận diện, liên hệ, vai trò và đăng nhập/bảo mật; Admin và nhân sự có thể liên kết Google với tài khoản đang dùng, không chuyển sang vai trò khách hàng. Toàn bộ **211 tests / 5.193 assertions** trên SQLite đạt. Kiểm chứng email thực nhận và Google sign-in thật còn chờ thao tác của chủ tài khoản; không đồng nhất kiểm thử giả lập hoặc trạng thái đủ cấu hình với kết nối thành công. Chi tiết cập nhật ở VALIDATION.

## Trạng thái PayOS mới nhất · 18/10/2026

Đã kiểm tra MB hiển thị **Đã liên kết**, tạo kênh **Badminton Hub** sau xác nhận của chủ dự án và lưu khóa mã hóa vào database vận hành trên máy. API merchant thật đã chấp nhận tạo link 1.000đ; truy vấn trạng thái và chữ ký phản hồi hợp lệ. Link kiểm tra đã hủy trên PayOS, đồng bộ về trạng thái không thành công trong ứng dụng, số tiền đã trả bằng 0 và ví không thay đổi. Chưa thực hiện chuyển khoản thật hoặc kiểm chứng thu tiền/cộng ví với giao dịch ngân hàng đã thanh toán.

Webhook công khai vẫn chưa được triển khai. Localhost dùng scheduler mỗi phút và thao tác kiểm tra giao dịch; cần giữ máy chủ/scheduler hoạt động. GitHub, SQL mẫu và ZIP không chứa khóa hoặc tài khoản nhận tiền riêng. Những trạng thái chưa kết nối trong các mục ngày 16–17/10 bên dưới là lịch sử trước lần kết nối này.

Mã nguồn đã thêm nạp ví PayOS, đối soát định kỳ trên máy chủ khi chưa có webhook công khai, mô tả ngân hàng 9 ký tự và hạn link cọc bám theo lịch sân. Kiểm thử provider vẫn dùng HTTP giả lập có chữ ký: toàn bộ SQLite **201 tests / 5.103 assertions**, nhóm PayOS/an toàn tích hợp trên MySQL **24 tests / 142 assertions**. Không coi xác thực danh tính hoặc kiểm thử giả lập là bằng chứng đã kết nối ngân hàng/nhận tiền. Chi tiết ở VALIDATION.

## Hoàn thiện vận hành · 17/10/2026

Đã chuẩn hóa đầu vào điện thoại tài khoản về `+84`, chống trùng cả dữ liệu `0...` cũ và kiểm tra email/điện thoại khi Admin sửa tài khoản. Không tự gộp tài khoản hoặc sửa số cũ không hợp lệ; trên database đang dùng còn **1 hồ sơ cần kiểm tra**, được gắn nhãn trong danh sách Admin. Có điểm vào nhận tài khoản tại quầy qua xác minh email/khôi phục; gửi thư thật vẫn cần SMTP.

Đổi lịch có báo giá chênh lệch/hoàn và kiểm tra lại giá trước ghi; hỗ trợ lịch đã trả hoàn toàn bằng ví trước ít nhất 4 giờ, cùng chi nhánh. Nhân sự có thể ghi nhận hoàn ví hoặc xác nhận đã hoàn tiền mặt/ngân hàng theo quyền. Đổi/hủy toàn bộ buổi chưa bắt đầu của chuỗi có xem trước từng buổi, kiểm tra xung đột nguyên tử và chống gửi lặp. Lịch có coupon/huấn luyện viên vẫn bị giới hạn. Hết giờ chơi chuyển nhãn **Chờ chốt hóa đơn**, không tự thu tiền, hoàn tất hoặc phát điểm.

Phân bổ khoản thu/hoàn mới được lưu cố định tại thời điểm ghi nhận; **1.218 khoản thu/hoàn cũ** trên database đang dùng được backfill từ dữ liệu hiện có và đánh dấu ước tính, không khẳng định tái dựng chính xác hóa đơn tại thời điểm cũ. Báo cáo gộp chi nhánh được giao, công suất trừ hợp thời gian bảo trì/khóa sân; mẫu số vẫn dùng trạng thái sân và giờ mở hiện tại, chưa có sổ lịch sử thay đổi trạng thái để tái dựng toàn bộ quá khứ.

Đã đồng bộ chặn ở bài đã lưu/bài đã tham gia và chat nhóm; tải tin cũ theo trang, tôn trọng xóa cá nhân. Báo cáo vi phạm chống gửi lặp khi đang chờ, có bộ lọc trạng thái và được đóng khi người duyệt quyết định. Lịch sử ví/thanh toán/điểm/audit/báo cáo và danh sách tài khoản có phân trang phía máy chủ, tìm kiếm; lịch sử có lọc ngày, audit xuất CSV. Kiểm chứng: **196 tests / 5.069 assertions** trên cả SQLite và MySQL riêng. PayOS thật được hoãn theo yêu cầu; các phần mở rộng còn thiếu ghi bên dưới.

## Phạm vi hoàn thiện mới · 16/10/2026

Admin đã tách giao diện khỏi khách/điều hành; dùng chung thành phần báo cáo, cùng bảng/màu xanh/thống kê và phân trang, thẻ bài chờ duyệt mở danh sách có đủ trạng thái. PayOS có trang cấu hình riêng trong mục thanh toán, xử lý xác thực/đối soát thật và giữ khóa phía máy chủ. Phần còn chờ để hoạt động thật là đăng nhập/kênh thanh toán PayOS và HTTPS webhook; không đánh dấu đã kết nối khi chưa có khóa.

Đã bổ sung dịch vụ trong buổi chơi vào hóa đơn, giữ giảm giá chỉ trên sân; chọn sân trống cùng chi nhánh khi đổi lịch; biểu mẫu khách mới có kiểm tra trùng; lưu bước đặt sân và giới hạn thời lượng; phân trang chi nhánh/sân/huấn luyện viên/bảng dữ liệu; làm lại chi tiết bài, trả lời bình luận có focus, thông báo từ chối và khóa sửa nội dung đã duyệt; làm rõ đường dẫn viết đánh giá sau buổi chơi và sửa cửa sổ đặt học. Gỡ bài cũ qua bộ lọc thời gian là ẩn có lưu lịch sử, chưa có dọn ảnh/tin nhắn vật lý tự động. Giới hạn đổi lịch có coupon/huấn luyện viên hoặc đã bắt đầu vẫn giữ; khoản thu vượt giá mới được bổ sung xử lý hoàn ngày 17/10.

Địa chỉ mẫu dùng phường/xã theo [danh sách chính thức TP.HCM](https://tphcm.baochinhphu.vn/danh-sach-chinh-thuc-168-phuong-xa-cua-tphcm-sau-sap-xep-101250617000144317.htm). Đây là dữ liệu môn học, không phải danh bạ sân đã kiểm chứng địa chỉ thực. Google Maps hiện dùng [Maps URLs](https://developers.google.com/maps/documentation/urls/get-started), không phải bản đồ nhúng/Directions API tính phí. Admin có trường tọa độ và Place ID để chỉnh vị trí đúng.


Phạm vi: mã Laravel/React hiện tại, dữ liệu MySQL, 5 vai trò, nguồn giao diện `huancode2203/SNCUCAHuNRose` tại `ae1c406618c1f60590640dce41f7a3c17d709ed7` và hai chat đã xác nhận: “Kiểm tra hệ thống đặt sân”, “Kiểm tra tính năng layout”. Đây là bản đồ chức năng và các điểm phát hiện khi đọc mã, chạy kiểm thử và thử một số luồng trên trình duyệt; không phải cam kết đã thử thủ công mọi tổ hợp thao tác.

Yêu cầu bản demo: dùng ngân hàng cá nhân và email thật khi chủ dự án nhập cấu hình; dịch vụ chưa cấu hình dùng demo có nhãn. Chưa triển khai hosting công khai. Hướng dẫn cài đặt và tài khoản mẫu ở [README](../README.md), bằng chứng kiểm tra ở [VALIDATION](VALIDATION.md).

## 1. Trạng thái dịch vụ

| Dịch vụ | Hiện dùng được | Phần còn cần |
| --- | --- | --- |
| Ngân hàng cá nhân | Admin nhập ngân hàng/số tài khoản/tên chủ tài khoản; khách xem VietQR và nội dung chuyển; quầy xác nhận khoản thực nhận. Không cần merchant MoMo để dùng luồng này | Chưa có tài khoản ngân hàng thật của chủ dự án. Xác nhận tự động cần SePay và webhook truy cập được từ bên ngoài |
| Ngân hàng khi chưa nhập tài khoản | Giao dịch DEMO_BANK, có nút mô phỏng thành công/hủy, không tạo QR tài khoản giả | Chỉ dùng dữ liệu môn học; không chứng minh đã chuyển tiền |
| MoMo | Merchant đủ cấu hình dùng adapter thật; chưa có merchant thì DEMO_MOMO cho nạp ví/thu đủ/thu cọc | Chưa kiểm thử giao dịch, IPN hay hoàn tiền với merchant thật |
| Email | SMTP đã tích hợp để gửi OTP/khôi phục; khi thiếu SMTP có mã demo gắn với phiên hiện tại | Cần tài khoản SMTP/app password của chủ dự án và kiểm tra thư thực nhận. Demo không đánh dấu email đã xác thực |
| SMS | Twilio Verify đã tích hợp; thiếu cấu hình dùng mã demo, có hạn/cooldown/giới hạn lần sai | Chưa gửi/nhận SMS thật. Demo không cập nhật số điện thoại đã xác thực |
| Google | Firebase Authentication Google đã bật trên project Badminton Hub riêng, cho phép localhost/127.0.0.1; SDK và Laravel xác minh danh tính/liên kết theo chủ tài khoản. OAuth trực tiếp vẫn có khi cấu hình riêng | Chưa thử thủ công một lượt Google sign-in tới phiên Laravel hoàn chỉnh. Google demo chỉ hiện khi thiếu cấu hình thật |
| AI tìm sân | Ollama `qwen3:1.7b` đã chạy suy luận; Laravel chuẩn hóa ngày/giờ rõ ràng và kiểm tra lịch/giá trong MySQL | Cần mở rộng đánh giá câu mơ hồ, tiếng lóng, nhiều ràng buộc và câu hỏi tiếp nối |
| AI duyệt bài | Ollama `qwen3:1.7b` đã thử bài phù hợp và bài xúc phạm/đe dọa; trường hợp nghi ngờ chuyển người duyệt | Chưa có bộ đánh giá lớn. Không bảo đảm phát hiện mọi nội dung vi phạm |
| OpenAI | Adapter có cấu hình riêng cho từng tác vụ, kiểm thử bằng phản hồi giả lập | Chưa có API key và chưa xác minh gọi dịch vụ thật |

Hai tác vụ AI có provider/model độc lập. Máy hiện dùng cùng một model 1.7b cho cả hai; chưa tuyên bố model 4b đã tải thành công. Dịch vụ được điền đủ cấu hình có ưu tiên hơn demo. Lỗi kết nối của dịch vụ đã cấu hình trả lỗi, không tự biến thành giao dịch/email thật thành công.

Ngân hàng nhập trong trang quản trị được lưu tại `integration_settings` và dùng xác nhận thủ công. Bộ `BANK_*` đầy đủ trong `.env` có ưu tiên. Tự động SePay hiện cần bộ ngân hàng trong `.env` cùng khóa webhook. SePay có kết nối MB hỗ trợ cá nhân theo [tài liệu MB API](https://docs.sepay.vn/ket-noi-mb-api.html); điều kiện từng ngân hàng khác nhau, không mặc định mọi ngân hàng đều hỗ trợ cùng cách đăng ký.

`DEMO_ENABLED=true` chỉ có hiệu lực trong `local/testing`. Giao dịch được ghi bằng phương thức `demo_bank/demo_momo` hoặc nội dung `[DEMO]`, chống ghi lặp và kiểm tra chủ tài khoản. Số dư demo vẫn là số dư trong database môn học; không phải tiền đã nhận tại ngân hàng. Khi chuyển sang sử dụng thật, cần dữ liệu sạch và tắt demo.

## 2. Các lỗi đã sửa trong đợt này

POS mua thêm khi đang chơi: chọn sân/khách từ danh sách riêng theo cơ sở hiện tại, không dùng danh sách của ngày lịch quản lý; ưu tiên khách đã nhận sân. Tiền hàng được cộng vào hóa đơn kể cả khi sân đã trả đủ, giữ nguyên tiền đã thu, trừ kho một lần và thu phần còn thiếu sau. Phiếu ghi rõ gộp/thu riêng và mở được hóa đơn sân; form giữ request key khi thử lại. Có 3 bài mới và nhóm 39 kiểm thử/502 assertions đạt ở SQLite/MySQL. Chưa thực hiện giao dịch thủ công trên dữ liệu vận hành; xem phạm vi thử tại VALIDATION.

Đợt 12/10: tổng lịch cố định được tính cho tất cả các tuần ngay sau chọn sân, kiểm tra bảng giá/lịch trống từng ngày và tách giảm giá/cọc/tiền còn lại. Xung đột tuần sau có số buổi/ngày cụ thể; không tạo một phần chuỗi. Làm lại đăng nhập/đăng ký/khôi phục và form dùng chung cho các vai trò, lỗi cạnh ô nhập, thao tác lưu/hủy, focus bàn phím và bố cục điện thoại. Sửa đăng nhập từ mục quản lý mở trực tiếp quay về đúng mục; thông báo thời gian/kết nối được diễn đạt tiếng Việt. Bộ mới có 157 kiểm thử/4.699 assertions trên hai database; phạm vi thử thủ công và bằng chứng ở VALIDATION. Đợt này không thay đổi các giới hạn nghiệp vụ/dịch vụ thật nêu bên dưới.

Đợt ổn định phiên/đặt sân: sửa cấu hình môi trường dùng chung luồng Apache gây thiếu APP_KEY/nhầm SQLite; đầu vào array ngày gây 500; phản hồi async cũ và lazy chunk bị xóa khi build. Client giữ route, có nút thử lại, không tự trả về trang chủ khi dịch vụ lỗi. Khôi phục giữ chỗ theo server, lưu bản nháp theo người dùng, khóa cơ sở và xác nhận trước đổi, thêm nút xác nhận giờ và mục “Sân đang chờ” riêng. Sửa nhập số ở form chung/dịch vụ/POS/tuần cố định, bổ sung kiểm tra kiểu và giới hạn server; kiểm tra điện thoại đúng định dạng nhưng chưa chuẩn hóa toàn bộ dữ liệu cũ. Quản lý nhớ mục/cơ sở/ngày, bỏ dữ liệu phản hồi cũ khi đổi phạm vi, có tìm mục và nhóm thao tác. Đăng nhập mặc định dẫn nhân sự vào khu quản lý, khách vào tài khoản. Bộ 150 kiểm thử đạt ở hai database và kiểm tra HTTP đồng thời 172 lượt trên 5 vai trò; phạm vi thử thủ công nằm trong VALIDATION.

Hồ sơ đã làm lại, tách thông tin cá nhân và bảo mật; avatar đúng tỷ lệ trên desktop/mobile, tên không còn bị ẩn bởi kiểu menu. Thống kê đọc booking và sân yêu thích thật. Bộ dữ liệu mở rộng có tối thiểu 30 dòng ở 46 bảng nghiệp vụ; bản cài mới có 95 người dùng tên Việt tự nhiên, 30 chi nhánh, 186 sân và 1.292 lịch. Không reset database đang dùng. Bộ lọc khu vực xử lý được nhiều chi nhánh cùng quận. Các giải vòng tròn trong fixture có đủ lịch 7 vòng/28 trận cho 8 người. Chi tiết dữ liệu, lệnh cài và phạm vi xác minh Firebase nằm trong README/VALIDATION.

| Vấn đề trước sửa | Hành vi hiện tại |
| --- | --- |
| Nhân sự còn quyền theo chi nhánh có thể trừ ví khách dù quyền thu tiền bị thu hồi | Bắt buộc `payments.write`; không thay đổi số dư khi bị từ chối |
| Kích hoạt hội viên/xác nhận vé chưa được phân loại đúng quyền thu tiền | Hai thao tác này chịu quyền `payments.write` |
| Nhân sự có thể lấy QR lịch của người khác sau khi thu hồi quyền đọc lịch | Bắt buộc `bookings.read`; khách vẫn đọc được QR lịch của mình |
| Giữ chỗ chưa xét đầy đủ quyền ghi lịch của nhân sự | Bắt buộc `bookings.write` và đúng phạm vi chi nhánh |
| Quản lý không có phân công có nguy cơ đọc báo cáo toàn hệ thống | Từ chối; người có phân công ở bảng nhiều chi nhánh chỉ đọc chi nhánh được giao |
| Hóa đơn có hàng bổ sung lúc đang chơi không trả thêm được bằng ví/MoMo/SePay | Trạng thái `checked_in` được phép trả phần còn thiếu |
| Hủy sự kiện để lại người chờ hoặc vé hết hạn | Các đăng ký này cũng được chuyển sang đã hủy và thông báo |
| Thông báo lỗi hủy lịch nói chỉ lịch chưa trả được hủy | Diễn đạt đúng chính sách hoàn ví đủ điều kiện trước giờ chơi |
| Đã có tài khoản ngân hàng nhưng thiếu SePay khiến khách không dùng được chuyển khoản | Cho phép VietQR và xác nhận thủ công tại quầy |
| Model nhỏ có thể đổi “ngày mai” sang ngày sai hoặc đổi giờ/khu vực đã nêu | Server giữ các tiêu chí rõ ràng từ câu khách, dùng model cho diễn giải và chỉ gợi ý từ database |
| Ngân sách tiếng Việt `500.000đ` bị phân tích sai | Chuẩn hóa dấu phân nhóm và đơn vị nghìn/triệu |
| Chặn người chỉ ẩn danh sách nhưng vẫn mở/like/comment/join bài bằng ID | Kiểm tra chặn hai chiều tại các thao tác này, cả khi chủ bài chấp nhận thành viên. Vẫn cho rời đội; admin vẫn đọc để kiểm duyệt |
| Thiếu cấu hình khiến các luồng trình diễn không thử được | Có demo Google, email/SMS OTP, MoMo/ngân hàng; demo không xác thực danh tính thật hoặc gửi khoản hoàn tới merchant |

## 3. Vai trò và chức năng đã có

| Vai trò | Chức năng chính | Chức năng đi kèm |
| --- | --- | --- |
| Khách hàng | Tìm sân/AI, xem ma trận trống, đặt lẻ/cố định, cọc/thu đủ, lịch cá nhân, QR nhận sân, hóa đơn, hủy/hoàn theo chính sách | Ví, voucher, hội viên, điểm/quà, yêu thích, đánh giá, chat, cộng đồng/đội, sự kiện/vé/chờ, đặt huấn luyện viên, hồ sơ/OTP/khôi phục, hỗ trợ/thông báo |
| Nhân viên | Lịch sân chi nhánh, chọn nhiều ô/đặt nhanh, khách tại quầy, check-in, cập nhật lịch theo quyền | POS gộp hóa đơn sân, thu tiền theo quyền, khách hàng, phản hồi hỗ trợ, báo cáo được cấp |
| Thu ngân | Thu cọc/thu đủ/hoàn, ví khách và POS theo quyền | Hóa đơn, khách nhanh, báo cáo thu/hoàn, tra lịch/check-in nếu được cấp |
| Quản lý | Sân/giá/giờ mở, bảo trì, khóa sân, lịch, khách, dịch vụ/sản phẩm/kho/POS, báo cáo chi nhánh | Huấn luyện viên, sự kiện/vé/danh sách chờ, giải loại trực tiếp/vòng tròn/Swiss, hỗ trợ/phản hồi đánh giá |
| Quản trị | Chi nhánh/tài khoản/nhân sự, phân chi nhánh/ma trận quyền, báo cáo toàn hệ thống, hội viên/coupon/quà, kiểm duyệt | Nội dung website/FAQ/blog/tuyển dụng, nhật ký, báo cáo vi phạm, đối soát ngân hàng/hoàn MoMo, cấu hình ngân hàng và trạng thái dịch vụ |

Huấn luyện viên hiện là hồ sơ trong danh mục, chưa phải vai trò đăng nhập thứ sáu. Quyền thao tác thực tế còn phụ thuộc ma trận quyền và chi nhánh; có nút trên giao diện không đồng nghĩa mọi vai trò được phép gọi API.

Mặc định nhân viên và thu ngân đang có nhiều quyền giống nhau. Admin đã có giao diện chỉnh ma trận, nhưng cần chọn bộ quyền phù hợp để phân công hai vị trí khác nhau khi trình bày hoặc vận hành.

## 4. Các điểm chưa hợp lý còn lại

Các mục dưới là phát hiện đọc mã; không gộp vào những lỗi đã sửa. P1: nên xử lý trước khi thu tiền/vận hành thực. P2: cải thiện nghiệp vụ hoặc trải nghiệm.

| Mức | Khu vực/vai trò | Phát hiện và tác động | Hướng xử lý |
| --- | --- | --- | --- |
| P2 | Dữ liệu liên hệ cũ | Đã chuẩn hóa và chống trùng đầu vào mới; hồ sơ cũ không hợp lệ/trùng được giữ để tránh gộp sai chủ | Admin đối chiếu với khách và cập nhật các dòng được gắn nhãn cần kiểm tra |
| P2 | Báo cáo quá khứ | Khoản thu mới có phân bổ cố định; phân bổ cũ là ước tính. Công suất đã trừ bảo trì/khóa sân nhưng chưa có lịch sử trạng thái/giờ mở đầy đủ | Giữ nhãn ước tính, bổ sung sổ thay đổi nếu cần báo cáo vận hành dài hạn |
| P2 | Tài khoản khách tại quầy | Đã có nhận tài khoản qua email; khách thiếu email và SMTP chưa cấu hình vẫn cần quầy hỗ trợ | Xác minh email thật; không cấp quyền chỉ dựa vào số điện thoại chưa xác minh |
| P2 | AI | Chưa đánh giá rộng trên câu thiếu ngày/giờ, nhầm AM/PM, viết tắt địa phương, xúc phạm viết biến thể hoặc trích dẫn | Tạo bộ câu có đáp án, đo sai/đúng và giữ kiểm duyệt người với trường hợp không chắc |

## 5. Chức năng chưa xây dựng đầy đủ

| Nhóm | Vai trò liên quan | Phần còn thiếu |
| --- | --- | --- |
| Chuyển khoản thủ công | Khách, thu ngân, admin | Khách gửi “đã chuyển”, chứng từ và hàng chờ đối soát riêng. Hiện khách liên hệ quầy, quầy ghi khoản thực nhận |
| POS đổi/trả | Thu ngân, quản lý | Hủy/đổi/trả hóa đơn POS có hoàn tiền, trả tồn kho và điều chỉnh điểm trong cùng luồng; chỉnh kho thủ công không thay thế nghiệp vụ này |
| Ca thu ngân | Thu ngân, quản lý | Mở/đóng ca, tiền đầu ca, kiểm đếm, chênh lệch, bàn giao và đối soát theo ca |
| Hoàn hỗn hợp | Khách, thu ngân, admin | Tự chia hoàn theo các nguồn ví/tiền mặt/ngân hàng/MoMo của cùng hóa đơn; chưa tự chuyển tiền hoàn ra ngân hàng |
| Chuỗi lịch cố định | Khách, nhân viên | Đã đổi/hủy các buổi chưa bắt đầu cùng xem trước phí/hoàn; chưa đổi chuỗi có coupon/huấn luyện viên hoặc qua chi nhánh khác |
| Huấn luyện viên | Khách, quản lý | Tài khoản người dạy, ca làm/ngày nghỉ, lịch rảnh độc lập, khóa học nhiều buổi và quản lý học viên. Đã kiểm tra trùng sân/người dạy khi đặt một buổi |
| Giải cầu lông | Người chơi, quản lý | Đăng ký đôi/đội, chốt từng set 21 điểm/best-of-3/deuce, xếp giờ và sân cho từng trận, sửa kết quả có lịch sử. Hiện dùng từng tài khoản và tổng tỷ số |
| Nhắc lịch ngoài hệ thống | Khách, quản lý | Email/SMS nhắc trước buổi chơi. Scheduler hiện nhắc trong tài khoản; email/SMS adapter phục vụ OTP |
| Nhật ký vận hành | Admin | Đã tìm/lọc/phân trang/xuất CSV toàn bộ audit; chưa có giao diện sao lưu/khôi phục và so sánh mọi trường trước/sau |

Chi phí/lợi nhuận, bảng lương, nhà cung cấp/đơn nhập hàng, hóa đơn điện tử và máy in quầy chuyên dụng chưa được xây dựng. Đây là phần mở rộng, không bắt buộc cho demo môn học nếu thuyết minh rõ phạm vi.

## 6. Giới hạn nghiệp vụ hiện tại cần giải thích khi demo

- Đổi lịch tự phục vụ dành cho lịch chưa trả còn hiệu lực hoặc lịch trả hoàn toàn bằng ví trước ít nhất 4 giờ, cùng chi nhánh; lịch có coupon/huấn luyện viên cần xử lý riêng. Có xem trước giá/hoàn; khoản ngoài ví do nhân sự xử lý theo quyền. Hoàn ngân hàng/tiền mặt là ghi nhận khoản quầy đã trả, không tự chuyển tiền.
- Hủy tự hoàn chỉ với lịch thanh toán hoàn toàn bằng ví và đáp ứng mốc trước giờ chơi. Khoản khác hoặc trả hỗn hợp qua quầy; thao tác hủy không chứng minh đã hoàn ra ngân hàng.
- Danh sách chờ gửi lời mời khi có chỗ, không tự đặt hoặc tự trừ ví khách.
- Kết quả AI là sân đang thỏa tiêu chí tại thời điểm hỏi; chọn sân vẫn kiểm tra lại. Khi không có kết quả, chưa có bộ tối ưu gợi ý giờ/khu vực gần nhất.
- Báo cáo dòng tiền tính theo ngày thu/hoàn; lượng lịch/công nợ tính theo ngày chơi. Hai chỉ số không phải cùng một tập thời gian.
- Google demo vào một tài khoản mẫu cố định. OTP demo hiển thị trên màn hình, không chứng minh sở hữu email/điện thoại. Khôi phục demo khi chưa đăng nhập chỉ cho email `.test`; mã demo gắn với phiên và không dùng được khi tắt demo.

## 7. Thứ tự hoàn thiện đề xuất

1. Hoàn thiện quy trình chuyển khoản thủ công/đối soát khi chủ dự án tiếp tục phần thanh toán; sửa hồ sơ cũ được gắn nhãn sau khi xác minh với khách.
2. Hủy/đổi/trả POS và hoàn thanh toán hỗn hợp; phân bổ doanh thu cố định đã hoàn thành cho khoản thu mới.
3. Ca thu ngân và phân quyền nhân viên/thu ngân theo nhu cầu thực tế.
4. Tự động dọn media cũ có chính sách lưu giữ và khôi phục; chặn cộng đồng, hàng kiểm duyệt và phân trang lịch sử đã được đồng bộ.
5. Lịch huấn luyện viên, giải đôi/từng set, nhắc lịch email/SMS nếu nằm trong phạm vi chấm đồ án.

Không cần chờ đăng ký doanh nghiệp để demo chuyển khoản cá nhân thủ công. Việc kết nối provider thật vẫn cần thông tin của chủ tài khoản; không đưa mật khẩu SMTP/khóa API vào GitHub. Những phần “chưa xác minh dịch vụ thật” khác với “chưa viết bộ kết nối”.
