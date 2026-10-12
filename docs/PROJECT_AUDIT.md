# Rà soát toàn bộ Badminton Hub · 12/10/2026

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
| P1 | Khách tại quầy/hồ sơ/SMS | Tạo khách và sửa hồ sơ chưa thống nhất chuẩn số `0...`/`+84...` với luồng SMS; cùng một số có thể có hai dạng | Chuẩn hóa số tại mọi đầu vào, rà soát dữ liệu trước khi thêm ràng buộc duy nhất |
| P1 | Báo cáo quản lý/admin | Phân bổ khoản thu một phần cho sản phẩm/dịch vụ dựa trên hóa đơn hiện tại; thêm hàng sau khoản thu có thể thay đổi phân bổ lịch sử | Lưu phân bổ tại thời điểm thu/hoàn. Tổng thu/hoàn hiện có sổ tiền riêng, nhưng số chi tiết là phân bổ theo tỷ lệ |
| P2 | Báo cáo công suất | Mẫu số tính giờ mở của sân đang active, chưa trừ bảo trì/khóa sự kiện, dựa trên trạng thái sân hiện tại | Tính thời gian thực có thể bán và trạng thái lịch sử; nói rõ đang đo tỷ lệ đặt trên giờ mở |
| P2 | Phân công nhiều chi nhánh | Có phân công nhiều chi nhánh và lọc từng chi nhánh; tổng quan chính mặc định chi nhánh chính, chưa có tổng hợp toàn bộ các chi nhánh được giao cho quản lý | Thêm lựa chọn “Các chi nhánh được giao” và dùng cùng một bộ xác định phạm vi ở các trang |
| P2 | Cộng đồng | Bài đã lưu/danh sách tìm người chưa đồng bộ lọc tài khoản bị chặn như danh sách bài. Mở bài trực tiếp đã bị chặn trong đợt này | Lọc thống nhất các danh sách và bỏ/ẩn nội dung đã lưu khi có chặn |
| P2 | Chat nhóm | Chặn một thành viên hiện có thể khiến người dùng không gửi được vào cả nhóm | Chốt quy tắc chặn trong nhóm: ẩn từng người, rời nhóm hoặc ngăn gửi; thông báo rõ |
| P2 | Duyệt/báo cáo vi phạm | Báo cáo có thể gửi lặp; một số thao tác duyệt AI chưa đồng bộ đóng báo cáo, danh sách báo cáo có cả đã xử lý | Chống lặp theo người/bài, lọc trạng thái, thống nhất đóng vụ việc và lưu quyết định |
| P2 | Danh sách/lịch sử | Nhiều màn hình chỉ lấy 30/100/500 bản ghi gần nhất; chưa có phân trang toàn bộ ví/audit/người dùng trên giao diện chính và lịch sử chat cũ | Phân trang phía máy chủ và giao diện; tránh gọi toàn bộ dữ liệu khi hệ thống lớn |
| P2 | Tài khoản khách tại quầy | Tài khoản tạo nhanh có mật khẩu ngẫu nhiên; chưa có luồng mời khách nhận quyền quản lý tài khoản của mình | Gửi liên kết nhận tài khoản qua kênh đã xác minh, kiểm tra trùng email/số điện thoại |
| P2 | AI | Chưa đánh giá rộng trên câu thiếu ngày/giờ, nhầm AM/PM, viết tắt địa phương, xúc phạm viết biến thể hoặc trích dẫn | Tạo bộ câu có đáp án, đo sai/đúng và giữ kiểm duyệt người với trường hợp không chắc |

## 5. Chức năng chưa xây dựng đầy đủ

| Nhóm | Vai trò liên quan | Phần còn thiếu |
| --- | --- | --- |
| Chuyển khoản thủ công | Khách, thu ngân, admin | Khách gửi “đã chuyển”, chứng từ và hàng chờ đối soát riêng. Hiện khách liên hệ quầy, quầy ghi khoản thực nhận |
| POS đổi/trả | Thu ngân, quản lý | Hủy/đổi/trả hóa đơn POS có hoàn tiền, trả tồn kho và điều chỉnh điểm trong cùng luồng; chỉnh kho thủ công không thay thế nghiệp vụ này |
| Ca thu ngân | Thu ngân, quản lý | Mở/đóng ca, tiền đầu ca, kiểm đếm, chênh lệch, bàn giao và đối soát theo ca |
| Hoàn hỗn hợp | Khách, thu ngân, admin | Tự chia hoàn theo các nguồn ví/tiền mặt/ngân hàng/MoMo của cùng hóa đơn; chưa tự chuyển tiền hoàn ra ngân hàng |
| Chuỗi lịch cố định | Khách, nhân viên | Hủy/đổi đồng loạt một chuỗi với xem trước phí/hoàn. Hiện chuỗi được tạo nguyên tử, xử lý lịch theo từng buổi |
| Huấn luyện viên | Khách, quản lý | Tài khoản người dạy, ca làm/ngày nghỉ, lịch rảnh độc lập, khóa học nhiều buổi và quản lý học viên. Đã kiểm tra trùng sân/người dạy khi đặt một buổi |
| Giải cầu lông | Người chơi, quản lý | Đăng ký đôi/đội, chốt từng set 21 điểm/best-of-3/deuce, xếp giờ và sân cho từng trận, sửa kết quả có lịch sử. Hiện dùng từng tài khoản và tổng tỷ số |
| Nhắc lịch ngoài hệ thống | Khách, quản lý | Email/SMS nhắc trước buổi chơi. Scheduler hiện nhắc trong tài khoản; email/SMS adapter phục vụ OTP |
| Nhật ký vận hành | Admin | Tìm/lọc/xuất nhật ký đầy đủ, lịch sử thay đổi và giao diện sao lưu/khôi phục. Hiện có ghi audit và danh sách gần nhất |

Chi phí/lợi nhuận, bảng lương, nhà cung cấp/đơn nhập hàng, hóa đơn điện tử và máy in quầy chuyên dụng chưa được xây dựng. Đây là phần mở rộng, không bắt buộc cho demo môn học nếu thuyết minh rõ phạm vi.

## 6. Giới hạn nghiệp vụ hiện tại cần giải thích khi demo

- Đổi lịch tự phục vụ chỉ dành cho lịch chưa trả, còn hiệu lực, cùng chi nhánh; lịch có coupon hoặc huấn luyện viên cần hủy/đặt lại theo chính sách. Đổi lịch đã trả chưa có luồng tính chênh lệch/hoàn.
- Hủy tự hoàn chỉ với lịch thanh toán hoàn toàn bằng ví và đáp ứng mốc trước giờ chơi. Khoản khác hoặc trả hỗn hợp qua quầy; thao tác hủy không chứng minh đã hoàn ra ngân hàng.
- Danh sách chờ gửi lời mời khi có chỗ, không tự đặt hoặc tự trừ ví khách.
- Kết quả AI là sân đang thỏa tiêu chí tại thời điểm hỏi; chọn sân vẫn kiểm tra lại. Khi không có kết quả, chưa có bộ tối ưu gợi ý giờ/khu vực gần nhất.
- Báo cáo dòng tiền tính theo ngày thu/hoàn; lượng lịch/công nợ tính theo ngày chơi. Hai chỉ số không phải cùng một tập thời gian.
- Google demo vào một tài khoản mẫu cố định. OTP demo hiển thị trên màn hình, không chứng minh sở hữu email/điện thoại. Khôi phục demo khi chưa đăng nhập chỉ cho email `.test`; mã demo gắn với phiên và không dùng được khi tắt demo.

## 7. Thứ tự hoàn thiện đề xuất

1. Chuẩn hóa số điện thoại và hoàn thiện quy trình chuyển khoản thủ công/đối soát.
2. Hủy/đổi/trả POS và hoàn thanh toán hỗn hợp, bổ sung phân bổ doanh thu cố định theo thời điểm thu.
3. Ca thu ngân và phân quyền nhân viên/thu ngân theo nhu cầu thực tế.
4. Đồng bộ chặn cộng đồng, hàng kiểm duyệt và phân trang lịch sử.
5. Lịch huấn luyện viên, giải đôi/từng set, nhắc lịch email/SMS nếu nằm trong phạm vi chấm đồ án.

Không cần chờ đăng ký doanh nghiệp để demo chuyển khoản cá nhân thủ công. Việc kết nối provider thật vẫn cần thông tin của chủ tài khoản; không đưa mật khẩu SMTP/khóa API vào GitHub. Những phần “chưa xác minh dịch vụ thật” khác với “chưa viết bộ kết nối”.
