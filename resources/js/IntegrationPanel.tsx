import { useEffect, useState } from "react";
import Icon from "./Icon";
import { api, useHub } from "./hub";
import { Card, Empty, Editor } from "./ui";
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
        "Bản demo đã có màn hình mô phỏng thanh toán và nạp ví, không cần đăng ký doanh nghiệp.",
        "Nếu muốn dùng MoMo thật, cần Partner Code, Access Key và Secret Key do MoMo cấp; điền trong .env trên máy chủ.",
        "Điền bộ khóa vào máy chủ, chọn môi trường thử nghiệm trước. Môi trường chính thức cần MoMo xác thực merchant.",
        "Đăng ký URL nhận kết quả /api/v1/payments/momo/ipn và URL quay lại website. MoMo cần truy cập được hai URL HTTPS này.",
    ],
    sepay: [
        "Có thể dùng tài khoản ngân hàng cá nhân: điền tài khoản ở phía trên để tạo VietQR và xác nhận tại quầy.",
        "Tự động đối soát: đăng ký SePay, chọn kết nối ngân hàng có hỗ trợ tài khoản cá nhân (ví dụ MB) theo yêu cầu của dịch vụ.",
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
    const { run, toast, loading, refresh } = useHub();
    const [bank, setBank] = useState<Row | null>(null);
    const [bankForm, setBankForm] = useState(false);
    const [demoEnabled, setDemoEnabled] = useState(false);
    const [items, setItems] = useState<Row[] | null>(null);
    const [checks, setChecks] = useState<Record<string, Row>>({});
    const load = async () => {
        const response = await api("admin/integrations");
        setItems(response.data);
        setBank(response.bank);
        setDemoEnabled(response.demo_enabled);
    };
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
                        Chọn tài khoản ngân hàng cá nhân để nhận chuyển khoản.
                        Theo dõi AI, email và các dịch vụ đang dùng.
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
            {demoEnabled && (
                <p
                    role="status"
                    className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6"
                >
                    Chế độ demo đang bật trên máy này. Dịch vụ đã đủ cấu hình
                    dùng kết nối thật; dịch vụ còn thiếu dùng luồng thử có nhãn
                    Demo. OTP demo không xác thực email hay điện thoại thật.
                </p>
            )}
            <Card>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 className="text-lg font-bold">Ngân hàng cá nhân</h3>
                        <p className="mt-2 text-sm text-slate-500">
                            Không cần merchant để nhận chuyển khoản và xác nhận
                            tại quầy.
                        </p>
                    </div>
                    <button className="btn" onClick={() => setBankForm(true)}>
                        Cài tài khoản nhận tiền
                    </button>
                </div>
                {bank?.account ? (
                    <p className="my-4 rounded-xl bg-emerald-50 p-4 text-sm">
                        {bank.name} · {bank.account} ·{" "}
                        {bank.mode === "automatic"
                            ? "SePay tự đối soát"
                            : "Nhân viên xác nhận sau khi kiểm tra tiền vào tài khoản"}
                    </p>
                ) : (
                    <p className="mt-4 text-sm text-slate-500">
                        Chưa điền tài khoản. Khách có thể thử chuyển khoản demo,
                        không có mã QR nhận tiền thật.
                    </p>
                )}
                <p className="mt-3 text-xs text-slate-500">
                    Tài khoản đã điền trong .env được ưu tiên. SePay có thể kết
                    nối tài khoản cá nhân với ngân hàng hỗ trợ; tự đối soát cần
                    khóa webhook và địa chỉ nhận kết quả truy cập được.
                </p>
                {bank?.mode === "manual" && (
                    <button
                        className="btn-outline mt-3"
                        disabled={loading}
                        onClick={() =>
                            run(async () => {
                                const response = await api(
                                    "admin/integrations/bank",
                                    "DELETE",
                                );
                                toast(response.message);
                                await load();
                                await refresh();
                            })
                        }
                    >
                        Xóa tài khoản lưu trên website
                    </button>
                )}
            </Card>
            {bankForm && (
                <Editor
                    title="Tài khoản ngân hàng cá nhân"
                    close={() => setBankForm(false)}
                    initial={bank?.account ? bank : { bank_bin: "970422" }}
                    fields={[
                        {
                            name: "bank_bin",
                            label: "Ngân hàng",
                            required: true,
                            options: [
                                { value: "970422", label: "MB Bank" },
                                { value: "970436", label: "Vietcombank" },
                                { value: "970415", label: "VietinBank" },
                                { value: "970418", label: "BIDV" },
                                { value: "970407", label: "Techcombank" },
                                { value: "970416", label: "ACB" },
                            ],
                        },
                        {
                            name: "account",
                            label: "Số tài khoản nhận tiền",
                            required: true,
                        },
                        {
                            name: "name",
                            label: "Tên chủ tài khoản",
                            required: true,
                        },
                    ]}
                    save={async (d) => {
                        const response = await api(
                            "admin/integrations/bank",
                            "PUT",
                            {
                                account: d.account,
                                name: d.name,
                                bank_bin: String(d.bank_bin),
                            },
                        );
                        toast(response.message);
                        await load();
                        await refresh();
                    }}
                />
            )}
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
                                {item.demo
                                    ? "Đang dùng demo"
                                    : !item.enabled
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
