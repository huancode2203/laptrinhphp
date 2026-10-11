import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { money, stamp, statusText, type Row } from "./data";
import { Card, Empty } from "./ui";
import Icon from "./Icon";

export default function DemoCheckout() {
    const { data, run, refresh, toast, loading } = useHub();
    const [attempt, setAttempt] = useState<Row | null>(null);
    const [error, setError] = useState("");
    const id = Number(location.hash.split("/")[1]);
    useEffect(() => {
        setAttempt(null);
        setError("");
        api("payments/momo/" + id)
            .then((r) => setAttempt(r.data))
            .catch((e) => setError(e.message));
    }, [id]);
    if (error) return <Empty text={error} />;
    if (!attempt) return <Empty text="Đang mở giao dịch…" />;
    if (
        !data?.demo?.enabled ||
        !["DEMO_MOMO", "DEMO_BANK"].includes(attempt.partner_code)
    )
        return <Empty text="Giao dịch này không thuộc chế độ demo." />;
    const pending = attempt.status === "pending";
    const expired = new Date(attempt.expires_at).getTime() <= Date.now();
    const finish = (success: boolean) =>
        run(async () => {
            const response = await api("demo/payments/" + id, "POST", {
                success,
            });
            setAttempt(response.data);
            await refresh();
            toast(response.message);
        });
    return (
        <main className="product-page mx-auto max-w-xl p-5 py-12">
            <Card>
                <span className="badge bg-amber-100 text-amber-900">
                    GIAO DỊCH DEMO
                </span>
                <div className="my-6 flex items-center gap-3">
                    <Icon name="shield" size={32} />
                    <h1 className="font-display text-2xl font-bold">
                        {attempt.partner_code === "DEMO_BANK"
                            ? "Chuyển khoản thử"
                            : "Thanh toán MoMo thử"}
                    </h1>
                </div>
                <p className="rounded-xl bg-amber-50 p-4 text-sm leading-6">
                    Không chuyển tiền thật. Bạn đang mô phỏng kết quả thanh toán
                    để thử đặt sân và nạp ví.
                </p>
                <p className="mt-6 text-sm text-slate-500">
                    {attempt.purpose === "booking"
                        ? "Thanh toán lịch sân"
                        : "Nạp ví HUB"}
                </p>
                <p className="font-display my-2 text-4xl font-black">
                    {money(attempt.amount)}
                </p>
                <p className="break-all text-sm text-slate-500">
                    Mã giao dịch: {attempt.order_id}
                </p>
                <p className="my-3 text-sm">
                    Hết hạn: {stamp(attempt.expires_at)}
                </p>
                <p role="status" className="my-4 font-semibold">
                    {expired && pending
                        ? "Giao dịch đã hết hạn"
                        : statusText(attempt.status)}
                </p>
                {pending && !expired && (
                    <div className="flex flex-wrap gap-3">
                        <button
                            className="btn"
                            disabled={loading}
                            onClick={() => finish(true)}
                        >
                            Mô phỏng thanh toán thành công
                        </button>
                        <button
                            className="btn-outline"
                            disabled={loading}
                            onClick={() => finish(false)}
                        >
                            Hủy giao dịch thử
                        </button>
                    </div>
                )}
                <a
                    className="btn-outline mt-6 inline-block"
                    href={
                        attempt.purpose === "wallet"
                            ? "#account/wallet"
                            : "#account/bookings"
                    }
                >
                    Về tài khoản
                </a>
            </Card>
        </main>
    );
}
