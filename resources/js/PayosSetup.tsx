import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { Card, Editor } from "./ui";
import type { Row } from "./data";

export default function PayosSetup() {
    const { toast, refresh } = useHub();
    const [status, setStatus] = useState<Row | null>(null),
        [editing, setEditing] = useState(false),
        [error, setError] = useState("");
    const load = () =>
        api("admin/integrations/payos")
            .then((r) => setStatus(r.data))
            .catch((e) => setError(e.message));
    useEffect(() => {
        load();
    }, []);
    return (
        <Card>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">THANH TOÁN NGÂN HÀNG</p>
                    <h3 className="mt-2 text-2xl font-bold">Kết nối PayOS</h3>
                    <p className="mt-2 text-sm text-slate-500">
                        Khách quét QR, thanh toán cọc hoặc trả đủ tiền; hệ thống
                        đối soát và cập nhật hóa đơn.
                    </p>
                </div>
                <span className="report-pill">
                    {status?.configured ? "Đã cấu hình" : "Chưa kết nối"}
                </span>
            </div>
            {error && (
                <p role="alert" className="mt-4 text-red-700">
                    {error}
                </p>
            )}
            <div className="mt-5 flex flex-wrap gap-3">
                <button className="btn" onClick={() => setEditing(true)}>
                    {status?.configured
                        ? "Cập nhật kết nối"
                        : "Nhập cấu hình PayOS"}
                </button>
                <a
                    className="btn-outline"
                    href="https://my.payos.vn/"
                    target="_blank"
                    rel="noreferrer"
                >
                    Mở tài khoản PayOS ↗
                </a>
            </div>
            {status && (
                <div className="mt-5 grid gap-4 rounded-2xl bg-slate-50 p-4 text-sm">
                    <p>
                        <b>Địa chỉ nhận kết quả:</b>
                        <br />
                        <span className="break-all">{status.webhook_url}</span>
                    </p>
                    <p className="text-slate-500">
                        Điền địa chỉ này trong kênh thanh toán PayOS khi website
                        có HTTPS công khai để nhận kết quả ngay. Khi máy chủ
                        đang chạy, hệ thống cũng kiểm tra giao dịch đang chờ mỗi
                        phút; bạn có thể chủ động kiểm tra trong lịch thanh
                        toán.
                    </p>
                </div>
            )}
            {editing && (
                <Editor
                    title="Kết nối kênh thanh toán PayOS"
                    initial={{ client_id: "", api_key: "", checksum_key: "" }}
                    fields={[
                        {
                            name: "client_id",
                            label: "Client ID",
                            required: true,
                            maxLength: 200,
                        },
                        {
                            name: "api_key",
                            label: "API Key",
                            type: "password",
                            required: true,
                            maxLength: 250,
                        },
                        {
                            name: "checksum_key",
                            label: "Checksum Key",
                            type: "password",
                            required: true,
                            maxLength: 250,
                        },
                    ]}
                    close={() => setEditing(false)}
                    save={async (values) => {
                        const r = await api(
                            "admin/integrations/payos",
                            "PUT",
                            values,
                        );
                        setStatus(r.data);
                        setEditing(false);
                        await refresh();
                        toast(
                            "Đã lưu cấu hình PayOS. Khóa được mã hóa trên máy chủ.",
                        );
                    }}
                />
            )}
        </Card>
    );
}
