import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { Card, Editor } from "./ui";
import type { Row } from "./data";

export default function EmailSetup({ after }: { after: () => Promise<void> }) {
    const { run, toast, loading } = useHub();
    const [status, setStatus] = useState<Row | null>(null);
    const [editing, setEditing] = useState(false);
    const [error, setError] = useState("");
    const [notice, setNotice] = useState("");
    useEffect(() => {
        api("admin/integrations/email")
            .then((response) => setStatus(response.data))
            .catch((failure) => setError(failure.message));
    }, []);
    return (
        <Card>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">EMAIL TÀI KHOẢN</p>
                    <h3 className="mt-2 text-2xl font-bold">
                        Gửi thư bằng Gmail
                    </h3>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Gửi mã xác nhận và thư khôi phục mật khẩu đến người
                        dùng. Nhập mật khẩu ứng dụng riêng của Google để kết
                        nối.
                    </p>
                </div>
                <span className="report-pill">
                    {status?.last_test_at
                        ? "Đã thử gửi thư"
                        : status?.configured
                          ? "Đã lưu cấu hình"
                          : "Cần kết nối email"}
                </span>
            </div>
            {error && (
                <p role="alert" className="mt-4 text-sm text-red-700">
                    {error}
                </p>
            )}
            <div className="my-5 grid gap-4 rounded-2xl bg-slate-50 p-5 sm:grid-cols-2">
                <div>
                    <p className="text-xs font-semibold text-slate-500">
                        ĐỊA CHỈ GỬI
                    </p>
                    <p className="mt-2 break-all font-semibold">
                        {status?.sender || "Chưa thiết lập"}
                    </p>
                </div>
                <div>
                    <p className="text-xs font-semibold text-slate-500">
                        LẦN GỬI THỬ GẦN NHẤT
                    </p>
                    <p className="mt-2 text-sm">
                        {status?.last_test_at
                            ? new Date(status.last_test_at).toLocaleString(
                                  "vi-VN",
                              )
                            : "Chưa gửi thư thử"}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap gap-3">
                {status?.source !== "environment" && (
                    <button
                        className="btn"
                        disabled={!status || loading}
                        onClick={() => {
                            setNotice("");
                            setEditing(true);
                        }}
                    >
                        {status?.configured
                            ? "Cập nhật email gửi"
                            : "Kết nối Gmail"}
                    </button>
                )}
                <button
                    className="btn-outline"
                    disabled={!status?.configured || loading}
                    onClick={() =>
                        run(async () => {
                            setError("");
                            setNotice("");
                            try {
                                const response = await api(
                                    "admin/integrations/email/test",
                                    "POST",
                                );
                                setStatus(response.data);
                                setNotice(response.message);
                                await after();
                            } catch (failure) {
                                setError(
                                    failure instanceof Error
                                        ? failure.message
                                        : "Chưa gửi được thư thử.",
                                );
                            }
                        })
                    }
                >
                    Gửi thư thử về địa chỉ gửi
                </button>
                <a
                    className="btn-outline"
                    href="https://myaccount.google.com/apppasswords"
                    target="_blank"
                    rel="noreferrer"
                >
                    Tạo mật khẩu ứng dụng ↗
                </a>
            </div>
            {status?.source === "environment" && (
                <p className="mt-4 text-sm text-slate-500">
                    Kết nối được quản lý bằng cấu hình máy chủ.
                </p>
            )}
            {notice && (
                <p
                    role="status"
                    className="mt-4 rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-800"
                >
                    {notice}
                </p>
            )}
            <p className="mt-4 text-xs leading-6 text-slate-500">
                Mật khẩu được mã hóa trên máy chủ và không hiển thị lại. Lưu cấu
                hình chưa chứng minh thư đã đến hộp thư; hãy gửi thử rồi kiểm
                tra Hộp thư đến hoặc Thư rác.
            </p>
            {editing && (
                <Editor
                    title="Kết nối Gmail gửi thư"
                    label="Lưu kết nối email"
                    description="Dùng mật khẩu ứng dụng Google gồm 16 ký tự. Không nhập mật khẩu đăng nhập Gmail."
                    initial={{
                        email: status?.sender || "",
                        name: status?.sender_name || "Badminton Hub",
                        password: "",
                    }}
                    close={() => setEditing(false)}
                    fields={[
                        {
                            name: "email",
                            label: "Địa chỉ Gmail gửi thư",
                            type: "email",
                            required: true,
                            maxLength: 254,
                        },
                        {
                            name: "name",
                            label: "Tên hiển thị người gửi",
                            required: true,
                            maxLength: 100,
                        },
                        {
                            name: "password",
                            label: "Mật khẩu ứng dụng Google",
                            type: "password",
                            required: !status?.password_saved,
                            maxLength: 64,
                            help: status?.password_saved
                                ? "Để trống để giữ mật khẩu đã lưu khi không đổi địa chỉ gửi."
                                : "Google cung cấp 16 ký tự; bạn có thể dán cả khoảng trắng phân nhóm.",
                        },
                    ]}
                    save={async (values) => {
                        const response = await api(
                            "admin/integrations/email",
                            "PUT",
                            values,
                        );
                        setStatus(response.data);
                        setEditing(false);
                        setError("");
                        setNotice("");
                        await after();
                        toast(
                            "Đã lưu email gửi. Hãy gửi thư thử để kiểm tra kết nối.",
                        );
                    }}
                />
            )}
        </Card>
    );
}
