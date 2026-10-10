import { useEffect, useState } from "react";
import Icon from "./Icon";
import { api, useHub } from "./hub";
import { Card, Empty } from "./ui";
import type { Row } from "./data";

const guides: Record<string, string[]> = {
    ai_search: [
        "Ollama: tải model tìm sân và giữ Ollama chạy trên máy chủ.",
        "OpenAI: tạo API key trong tài khoản API của bạn, chọn provider openai và đặt tên model OpenAI cho tìm sân.",
    ],
    ai_moderation: [
        "AI duyệt bài có provider và model riêng, có thể dùng Ollama hoặc OpenAI.",
        "Bài có dấu hiệu vi phạm, độ tin cậy thấp hoặc lỗi model sẽ vào hàng chờ quản trị duyệt.",
    ],
    momo: [
        "Đăng ký MoMo for Business, mở Thông tin tích hợp để lấy Partner Code, Access Key và Secret Key của môi trường thử nghiệm.",
        "Điền bộ khóa vào máy chủ, chọn môi trường thử nghiệm trước. Môi trường chính thức cần MoMo xác thực merchant.",
        "Đăng ký URL nhận kết quả /api/v1/payments/momo/ipn và URL quay lại website. MoMo cần truy cập được hai URL HTTPS này.",
    ],
    sepay: [
        "Đăng ký SePay và thêm tài khoản ngân hàng của bạn; có thể bắt đầu trong Test Mode.",
        "Thêm webhook Có tiền vào, chọn tài khoản nhận tiền, chọn xác thực API Key và điền cùng khóa ở máy chủ.",
        "URL webhook là /api/v1/payments/bank/webhook. Điền số tài khoản, tên chủ tài khoản và mã BIN ngân hàng để hiển thị thanh toán.",
    ],
    google: [
        "Trong Google Cloud, tạo project rồi cấu hình Google Auth Platform: tên ứng dụng, Audience và tài khoản thử nghiệm.",
        "Tạo OAuth client loại Web application. Thêm URI chuyển hướng chính xác như ở bên dưới.",
        "Sao chép Client ID và Client Secret vào máy chủ. Người có tài khoản hiện tại đăng nhập bằng mật khẩu rồi liên kết Google trong Hồ sơ.",
    ],
    sms: [
        "Đăng ký Twilio, lấy Account SID và Auth Token trong Console.",
        "Vào Verify, tạo Service để lấy Service SID bắt đầu bằng VA; đặt mã xác thực dài 6 chữ số.",
        "Bật SMS và quyền gửi đến Việt Nam trong cấu hình Verify. Tài khoản thử nghiệm có thể bị giới hạn số điện thoại nhận mã.",
    ],
    email: [
        "Dùng thông tin SMTP do nhà cung cấp email cấp: máy chủ, cổng, tài khoản và mật khẩu ứng dụng.",
        "Nếu dùng Gmail: bật xác minh 2 bước rồi tạo App Password dành riêng cho Badminton Hub. Mật khẩu ứng dụng có thể không khả dụng với một số tài khoản tổ chức.",
        "Gmail SMTP: smtp.gmail.com, cổng 587, MAIL_SCHEME=smtp; dùng email gửi thư làm MAIL_USERNAME và MAIL_FROM_ADDRESS.",
    ],
};

export default function IntegrationPanel() {
    const { run, toast, loading } = useHub();
    const [items, setItems] = useState<Row[] | null>(null);
    const [checks, setChecks] = useState<Record<string, Row>>({});
    const load = async () => setItems((await api("admin/integrations")).data);
    useEffect(() => {
        load().catch((e) => toast(e.message));
    }, []);
    if (!items) return <Empty text="Đang tải kết nối dịch vụ…" />;
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="font-display text-2xl font-bold">
                        Kết nối dịch vụ
                    </h2>
                    <p className="mt-2 max-w-2xl text-sm text-slate-500">
                        Theo dõi cấu hình AI, thanh toán và xác thực. Điền khóa
                        vào file .env riêng trên máy chủ; trang này chỉ hiển thị
                        trạng thái.
                    </p>
                </div>
                <button
                    className="btn-outline"
                    disabled={loading}
                    onClick={() => run(load)}
                >
                    Làm mới trạng thái
                </button>
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                {items.map((item) => (
                    <Card key={item.id}>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h3 className="flex items-center gap-2 font-bold">
                                    <Icon
                                        name={
                                            item.id.startsWith("ai_")
                                                ? "spark"
                                                : "shield"
                                        }
                                        size={20}
                                    />
                                    {item.label}
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    {item.mode}
                                </p>
                            </div>
                            <span
                                className={
                                    "rounded-full px-3 py-1 text-xs font-semibold " +
                                    (item.enabled && item.configured
                                        ? "bg-emerald-50 text-emerald-700"
                                        : "bg-amber-50 text-amber-800")
                                }
                            >
                                {!item.enabled
                                    ? "Đang tắt"
                                    : item.configured
                                      ? "Đã điền cấu hình"
                                      : "Còn thiếu cấu hình"}
                            </span>
                        </div>
                        {item.missing.length > 0 && (
                            <p className="mt-4 break-words rounded-xl bg-slate-50 p-3 text-xs leading-6 text-slate-600">
                                Cần điền: {item.missing.join(", ")}
                            </p>
                        )}
                        <details className="mt-4 text-sm">
                            <summary className="cursor-pointer font-semibold">
                                Hướng dẫn lấy thông tin
                            </summary>
                            <ol className="ml-5 mt-3 list-decimal space-y-2 leading-6 text-slate-600">
                                {(guides[item.id] ?? []).map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ol>
                            {item.id === "google" && (
                                <p className="mt-3 break-all rounded-lg bg-slate-50 p-3 text-xs">
                                    {window.location.origin}
                                    /auth/google/callback
                                </p>
                            )}
                        </details>
                        <div className="mt-5 flex flex-wrap gap-2">
                            <a
                                className="btn-outline text-xs"
                                href={item.setup_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Mở trang dịch vụ ↗
                            </a>
                            {item.id.startsWith("ai_") && item.configured && (
                                <button
                                    className="btn text-xs"
                                    disabled={loading}
                                    onClick={() =>
                                        run(async () => {
                                            const response = await api(
                                                "admin/integrations/ai-check",
                                                "POST",
                                                {
                                                    task: item.id.replace(
                                                        "ai_",
                                                        "",
                                                    ),
                                                },
                                            );
                                            setChecks((current) => ({
                                                ...current,
                                                [item.id]: response.data,
                                            }));
                                        })
                                    }
                                >
                                    Kiểm tra model
                                </button>
                            )}
                        </div>
                        {checks[item.id] && (
                            <p
                                role="status"
                                className={
                                    "mt-4 rounded-xl p-3 text-sm " +
                                    (checks[item.id].success
                                        ? "bg-emerald-50 text-emerald-800"
                                        : "bg-amber-50 text-amber-900")
                                }
                            >
                                {checks[item.id].message} ·{" "}
                                {checks[item.id].seconds ?? 0}s
                            </p>
                        )}
                    </Card>
                ))}
            </div>
        </div>
    );
}
