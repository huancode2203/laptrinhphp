import { useState } from "react";
import { useHub } from "./hub";
import { Card } from "./ui";
import Icon from "./Icon";
const guides: Record<string, string[][]> = {
    customer: [
        [
            "Đặt sân",
            "Tìm sân theo nhu cầu",
            "Tìm sân còn trống theo ngày, từ giờ, đến giờ, phường/xã và ngân sách.",
            "Mở Tìm sân; nhập nhu cầu hoặc tự chỉnh bộ lọc; xem kết quả; chọn sân phù hợp.",
            "booking",
        ],
        [
            "Đặt sân",
            "Chọn giờ và cơ sở",
            "Chọn đúng chi nhánh, sân và thời lượng trước khi xác nhận.",
            "Chọn ngày, giờ bắt đầu, thời lượng; xác nhận khung giờ; chọn sân trống; kiểm tra giá.",
            "booking",
        ],
        [
            "Đặt sân",
            "Đặt lịch cố định",
            "Giữ cùng lịch chơi qua nhiều tuần, xem tổng tiền và ưu đãi phí sân.",
            "Chọn Đánh cố định; nhập số tuần; xem giá từng buổi, tổng sau giảm và tiền cọc. Thanh toán PayOS hiện thực hiện theo từng lịch.",
            "booking",
        ],
        [
            "Thanh toán",
            "Chuyển khoản PayOS",
            "Thanh toán cọc hoặc trả đủ bằng ngân hàng; kết quả được đối soát trên máy chủ.",
            "Mở chi tiết lịch; chọn cọc hoặc trả đủ; mở PayOS; chuyển đúng số tiền và nội dung; quay lại kiểm tra trạng thái.",
            "account/bookings",
        ],
        [
            "Thanh toán",
            "Thanh toán tại quầy",
            "Nhân viên ghi nhận khoản tiền đã thực nhận bằng tiền mặt hoặc chuyển khoản trực tiếp.",
            "Đọc mã lịch tại quầy; thanh toán; yêu cầu nhân viên kiểm tra và ghi nhận; kiểm tra hóa đơn trong tài khoản.",
            "account/bookings",
        ],
        [
            "Thanh toán",
            "Ví HUB và nạp số dư",
            "Nạp số dư qua PayOS để sử dụng trong các thao tác có hỗ trợ dùng số dư.",
            "Mở Ví & ưu đãi; chọn nạp bằng chuyển khoản; nhập số tiền; hoàn tất trên PayOS. Số dư chỉ tăng sau khi ngân hàng xác nhận.",
            "account/wallet",
        ],
        [
            "Lịch của tôi",
            "Xem lịch và hóa đơn",
            "Theo dõi lịch chờ thanh toán, đã cọc, đang chơi, đã hoàn tất hoặc đã hủy.",
            "Mở Lịch của tôi; lọc trạng thái; mở chi tiết; xem phí sân, dịch vụ, tiền đã trả, còn thiếu hoặc tải phiếu.",
            "account/bookings",
        ],
        [
            "Lịch của tôi",
            "Mã nhận sân",
            "Giúp nhân viên tìm đúng lịch và xác nhận khách đã đến; không dùng để chuyển tiền.",
            "Khi đến chi nhánh, mở chi tiết lịch; mở Mã nhận sân; đưa nhân viên quét. Có thể đọc mã lịch thay cho quét QR.",
            "account/bookings",
        ],
        [
            "Lịch của tôi",
            "Bỏ giữ chỗ, hủy và đổi lịch",
            "Phân biệt lượt giữ chỗ 5 phút với lịch đã tạo. Lịch đã trả tiền không hủy trực tiếp.",
            "Bỏ giữ chỗ để chọn lại; hủy lịch chưa trả nếu không còn nhu cầu. Với lịch đã trả, dùng Đổi lịch hoặc liên hệ chi nhánh theo thông báo.",
            "account/bookings",
        ],
        [
            "Lịch của tôi",
            "Danh sách chờ",
            "Nhận thông báo khi đúng sân và giờ mong muốn có chỗ, không tự đặt hay thu tiền.",
            "Bấm sân đang bận; đọc thông tin; chỉ xác nhận nhận thông báo nếu thực sự muốn chờ. Có thể rời danh sách trong tài khoản.",
            "account/waitlist",
        ],
        [
            "Quyền lợi",
            "Sân yêu thích",
            "Lưu sân thường chơi để tìm lại nhanh.",
            "Bấm tim ở kết quả tìm sân; tim đỏ là đã lưu; bấm lại để bỏ lưu; xem danh sách trong Quyền lợi & đã lưu.",
            "account/extras",
        ],
        [
            "Quyền lợi",
            "Hội viên, điểm và ưu đãi",
            "Xem quyền lợi, mức giảm phí sân, lịch sử điểm và các voucher còn dùng được.",
            "Mở Hội viên hoặc Quyền lợi; xem điều kiện; chỉ chọn gói phù hợp và kiểm tra số dư nếu dùng chức năng mua bằng số dư.",
            "account/membership",
        ],
        [
            "Cộng đồng",
            "Đăng bài và duyệt nội dung",
            "Tìm bạn chơi, giao lưu theo thời gian, địa điểm và trình độ.",
            "Tạo bài đầy đủ thông tin; gửi duyệt; xem trạng thái/lý do nếu bị từ chối. Bài đã duyệt không sửa nội dung trực tiếp.",
            "community",
        ],
        [
            "Cộng đồng",
            "Tham gia, bình luận và trò chuyện",
            "Xin tham gia nhóm chơi, trả lời bình luận, nhắn tin và quản lý cuộc trò chuyện của mình.",
            "Mở bài; xin tham gia; chờ chủ bài duyệt nếu cần; dùng Trả lời ở bình luận hoặc mở trò chuyện. Xóa chat chỉ ẩn lịch sử của bạn.",
            "community",
        ],
        [
            "Dịch vụ",
            "Đánh giá sau buổi chơi",
            "Chia sẻ chất lượng sân sau khi lịch đã hoàn tất.",
            "Mở lịch hoàn tất của chính mình; chấm sao, nhập đánh giá và ảnh nếu có; xem phản hồi chi nhánh.",
            "account/bookings",
        ],
        [
            "Dịch vụ",
            "Học cầu lông và giải đấu",
            "Đặt một buổi học với huấn luyện viên hoặc đăng ký sự kiện còn chỗ.",
            "Chọn người dạy và giờ rảnh ở Học cầu lông; xem lịch học trong tài khoản. Vé sự kiện thanh toán trực tiếp tại quầy.",
            "coaching",
        ],
        [
            "Tài khoản",
            "Hồ sơ, Google và hỗ trợ",
            "Cập nhật liên hệ, liên kết Google, đổi mật khẩu và gửi yêu cầu hỗ trợ.",
            "Mở Hồ sơ; lưu thông tin hợp lệ; liên kết Google khi cần. Nếu gặp lỗi lịch hoặc thanh toán, gửi yêu cầu hỗ trợ kèm mã lịch.",
            "account/profile",
        ],
    ],
    manager: [
        [
            "Vận hành",
            "Tổng quan và phạm vi chi nhánh",
            "Theo dõi hoạt động tại các chi nhánh được phân công.",
            "Chọn chi nhánh và ngày; xem lịch hôm nay, khách đang chơi, hóa đơn cần chốt; bấm thống kê để mở danh sách.",
            "manage/dashboard",
        ],
        [
            "Vận hành",
            "Lịch sân và đặt tại quầy",
            "Đặt sân cho khách, không dùng tài khoản nhân sự làm khách hàng.",
            "Mở ma trận hoặc lịch; chọn sân/giờ trống; chọn khách có sẵn hoặc thêm khách mới; xem giá và xác nhận.",
            "manage/bookings",
        ],
        [
            "Vận hành",
            "Tạo khách hàng",
            "Lưu thông tin khách mới và tránh tạo trùng điện thoại/email.",
            "Mở form thêm khách; nhập họ tên, số điện thoại và email nếu có; hệ thống đối chiếu số 0/+84 và email trước khi tạo.",
            "manage/customers",
        ],
        [
            "Thanh toán",
            "Thu tiền qua PayOS tại quầy",
            "Tạo QR chuyển khoản cho đúng lịch và đúng khách trong chi nhánh phụ trách.",
            "Mở chi tiết lịch; chọn chuyển khoản cọc hoặc toàn bộ; đưa khách trang PayOS; kiểm tra trạng thái sau khi khách chuyển.",
            "manage/bookings",
        ],
        [
            "Thanh toán",
            "Ghi nhận thu tiền trực tiếp",
            "Ghi khoản tiền mặt hoặc chuyển khoản mà quầy đã kiểm tra thực nhận.",
            "Mở hóa đơn; nhập khoản còn phải thu; chọn tiền mặt/ngân hàng; chỉ xác nhận sau khi kiểm tra tiền; không thu vượt dư nợ.",
            "manage/bookings",
        ],
        [
            "Vận hành",
            "Nhận sân bằng QR hoặc mã lịch",
            "Tìm nhanh đúng khách, đúng sân và thời gian.",
            "Quét mã nhận sân hoặc tìm mã lịch; kiểm tra đã trả đủ và thời điểm nhận sân; xác nhận nhận sân.",
            "manage/bookings",
        ],
        [
            "Vận hành",
            "Đổi sân hoặc giờ chơi",
            "Chuyển sang sân trống trong cùng chi nhánh, xem trước giá thay đổi.",
            "Mở chi tiết lịch; chọn Đổi lịch; chọn giờ/sân mới; xem giá và phần cần thu thêm; xác nhận. Không tự hoàn chênh lệch.",
            "manage/bookings",
        ],
        [
            "Vận hành",
            "Thêm dịch vụ và hàng bán khi khách đang chơi",
            "Cộng nước, hàng hóa hoặc dịch vụ vào đúng hóa đơn sân.",
            "Thêm dịch vụ trong chi tiết lịch hoặc chọn Gộp vào sân ở POS; chọn đúng lịch đang sử dụng; kiểm tra hàng và tổng tiền.",
            "manage/pos",
        ],
        [
            "Vận hành",
            "Chốt hóa đơn cuối buổi",
            "Đảm bảo tiền sân và mọi khoản phát sinh đã được thu đủ.",
            "Khi hết giờ, mở lịch Chờ chốt hóa đơn; đối chiếu phát sinh; thu phần còn thiếu; hoàn tất sau khi đủ tiền.",
            "manage/bookings",
        ],
        [
            "Danh mục",
            "Sân, bảng giá và bảo trì",
            "Quản lý trạng thái sân, giờ phục vụ, mức giá và thời gian tạm ngừng.",
            "Tìm/lọc sân; sửa đúng chi nhánh; tạo bảo trì không trùng lịch khách. Không khóa sân đang có lịch còn hiệu lực.",
            "manage/courts",
        ],
        [
            "Danh mục",
            "Dịch vụ, sản phẩm và kho",
            "Quản lý giá bán, tồn kho và phiếu điều chỉnh.",
            "Tạo/sửa sản phẩm; nhập số lượng hợp lệ; điều chỉnh tồn có ghi chú. POS trừ kho theo đơn đã ghi, không nhân đôi khi thử lại.",
            "manage/products",
        ],
        [
            "Dịch vụ",
            "Sự kiện, người dạy và đánh giá",
            "Quản lý dịch vụ bổ sung theo quyền, xác nhận vé đã thu và phản hồi khách.",
            "Mở Sự kiện hoặc Vận hành & nội dung; chọn đúng chi nhánh; quản lý lịch người dạy, phản hồi đánh giá và hỗ trợ.",
            "manage/advanced",
        ],
        [
            "Báo cáo",
            "Doanh thu và hiệu suất",
            "Xem khoản thu thực ghi, công nợ và công suất theo phạm vi được giao.",
            "Chọn chi nhánh/khoảng ngày; xem biểu đồ và bảng; tải XLSX/PDF. Ngày thu tiền có thể khác ngày khách chơi.",
            "manage/reports",
        ],
        [
            "Tài khoản",
            "Phân quyền khi làm việc",
            "Các nút và quyền ghi phụ thuộc phân công của Admin.",
            "Nếu bị từ chối truy cập, kiểm tra chi nhánh và liên hệ Admin. Không đổi sang tài khoản khách để thực hiện thao tác quản lý.",
            "manage/advanced",
        ],
    ],
    admin: [
        [
            "Điều hành",
            "Tổng quan toàn hệ thống",
            "Theo dõi hoạt động, doanh thu và các nội dung cần xử lý của mọi chi nhánh.",
            "Chọn toàn hệ thống hoặc một chi nhánh; lọc ngày; bấm thẻ Bài chờ duyệt/Tài khoản/Chi nhánh để mở danh sách.",
            "admin/dashboard",
        ],
        [
            "Điều hành",
            "Chi nhánh và sân",
            "Tạo chi nhánh, cập nhật địa chỉ phường/xã, giờ mở và danh mục sân.",
            "Tìm chi nhánh; mở chi tiết; cập nhật liên hệ/địa chỉ; kiểm tra số sân và phân công quản lý.",
            "admin/branches",
        ],
        [
            "Điều hành",
            "Danh mục sân",
            "Tìm sân nhanh trong nhiều chi nhánh.",
            "Dùng tìm kiếm, bộ lọc tất cả/chi nhánh/trạng thái và phân trang; chỉnh đúng sân cần quản lý.",
            "admin/courts",
        ],
        [
            "Tài khoản",
            "Tài khoản, vai trò và phân công",
            "Tạo/khóa tài khoản và quyết định nhân sự được làm việc ở chi nhánh nào.",
            "Tìm theo tên, email, điện thoại hoặc vai trò; thay đổi đúng phân công; không cấp vai trò Admin cho đăng ký khách.",
            "admin/users",
        ],
        [
            "Tài khoản",
            "Cấu hình quyền",
            "Quyết định nhóm nhân sự được đọc/ghi từng nghiệp vụ.",
            "Mở Cấu hình & phân quyền; chọn vai trò và quyền. Việc có quyền vẫn cần đúng chi nhánh được giao.",
            "admin/operations",
        ],
        [
            "Thương mại",
            "Gói hội viên",
            "Quản lý tên gói, giá, thời hạn, ưu đãi và điều kiện điểm.",
            "Thêm/sửa gói; kiểm tra mức giảm và điều kiện; bật/tắt gói theo nhu cầu. Không lọc gói hội viên bằng phường/xã.",
            "admin/plans",
        ],
        [
            "Thương mại",
            "Mã ưu đãi",
            "Quản lý điều kiện áp dụng giảm giá phí sân.",
            "Tạo mã; chọn phạm vi chi nhánh/tất cả; đặt hạn dùng và lượt dùng; kiểm tra mã trước khi công bố.",
            "admin/coupons",
        ],
        [
            "Nội dung",
            "Kiểm duyệt cộng đồng",
            "Duyệt, từ chối, ẩn bài và xử lý báo cáo vi phạm.",
            "Lọc trạng thái; đọc nội dung; ghi lý do khi từ chối; người đăng nhận thông báo. AI chỉ hỗ trợ đề xuất.",
            "admin/moderation",
        ],
        [
            "Kết nối",
            "PayOS và thanh toán tại quầy",
            "Duy trì kết nối ngân hàng cho khách và quầy; theo dõi khoản cần đối chiếu.",
            "Mở Thanh toán & kết nối; kiểm tra cấu hình PayOS và trạng thái từng khoản chuyển; chỉ ghi nhận thu tại quầy sau khi thực nhận tiền.",
            "admin/payments",
        ],
        [
            "Kết nối",
            "Gmail và Google",
            "Thiết lập thư tài khoản và đăng nhập Google.",
            "Nhập cấu hình Gmail trực tiếp trong form bảo mật; gửi thư thử. Google dùng project đã cấu hình; tài khoản hiện có liên kết từ hồ sơ.",
            "admin/payments",
        ],
        [
            "Báo cáo",
            "Báo cáo toàn chi nhánh",
            "So sánh doanh thu, công nợ, sân/dịch vụ/hàng hóa và công suất.",
            "Chọn phạm vi, ngày; xem số liệu; xuất báo cáo. Các phân bổ dữ liệu cũ được ghi rõ là ước tính.",
            "admin/dashboard",
        ],
        [
            "Kiểm soát",
            "Nhật ký hoạt động",
            "Biết thao tác gì, đối tượng nào và thay đổi quan trọng đã ghi nhận.",
            "Tìm hoạt động; lọc ngày; đọc mô tả dễ hiểu ở Chi tiết; xuất CSV nếu cần đối chiếu.",
            "admin/audits",
        ],
        [
            "Tài khoản",
            "Hồ sơ quản trị",
            "Bảo vệ tài khoản làm việc và liên kết Google mà không mất vai trò.",
            "Mở Tài khoản quản trị; cập nhật tên/số điện thoại hoặc liên kết Google; không dùng tài khoản Admin để đặt sân cá nhân.",
            "admin/profile",
        ],
    ],
};
export default function UsageGuide() {
    const { data } = useHub();
    const own =
        data?.user?.role === "admin"
            ? "admin"
            : data?.user?.role === "customer"
              ? "customer"
              : "manager";
    const [role, setRole] = useState(own),
        [search, setSearch] = useState("");
    const normalize = (text: string) =>
        text
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/g, "d")
            .toLowerCase();
    const items = guides[role].filter((item) =>
        normalize(item.join(" ")).includes(normalize(search)),
    );
    return (
        <section className="space-y-5">
            <div className="rounded-3xl bg-[#173e31] p-6 text-white sm:p-8">
                <p className="text-xs font-bold tracking-widest text-[#c5f566]">
                    SỬ DỤNG BADMINTON HUB
                </p>
                <h2 className="mt-3 text-3xl font-bold">
                    Mỗi tính năng, một việc rõ ràng.
                </h2>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-white/75">
                    Chọn vai trò để xem công dụng, nơi thao tác và cách dùng.
                    Quyền làm việc thực tế phụ thuộc tài khoản và chi nhánh được
                    phân công.
                </p>
            </div>
            <div
                className="flex flex-wrap gap-2"
                aria-label="Vai trò xem hướng dẫn"
            >
                {[
                    ["customer", "Người chơi"],
                    ["manager", "Quản lý chi nhánh"],
                    ["admin", "Admin"],
                ].map(([id, label]) => (
                    <button
                        key={id}
                        className={role === id ? "btn" : "btn-outline"}
                        aria-pressed={role === id}
                        onClick={() => {
                            setRole(id);
                            setSearch("");
                        }}
                    >
                        {label}
                    </button>
                ))}
            </div>
            <label className="block text-sm font-semibold">
                Tìm chức năng
                <input
                    className="input mt-2 w-full"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Ví dụ: QR nhận sân, đặt cọc, đổi lịch, nhật ký…"
                />
            </label>
            <p className="text-sm text-slate-500">
                {items.length} chức năng phù hợp
            </p>
            <div className="grid gap-4 xl:grid-cols-2">
                {items.map(([group, title, purpose, steps, route]) => (
                    <Card key={title}>
                        <p className="eyebrow">{group}</p>
                        <h3 className="mt-2 text-xl font-bold">{title}</h3>
                        <p className="mt-3 text-sm leading-6 text-slate-600">
                            {purpose}
                        </p>
                        <div className="mt-4 rounded-xl bg-slate-50 p-4">
                            <p className="text-xs font-bold text-emerald-800">
                                CÁCH SỬ DỤNG
                            </p>
                            <p className="mt-2 text-sm leading-6">{steps}</p>
                        </div>
                        {role === own && (
                            <button
                                className="btn-outline mt-4"
                                onClick={() => {
                                    location.hash = route;
                                }}
                            >
                                Mở chức năng <Icon name="arrow" size={16} />
                            </button>
                        )}
                    </Card>
                ))}
            </div>
            {!items.length && (
                <p className="rounded-xl bg-white p-5">
                    Chưa tìm thấy hướng dẫn phù hợp. Hãy thử tên chức năng ngắn
                    hơn.
                </p>
            )}
        </section>
    );
}
