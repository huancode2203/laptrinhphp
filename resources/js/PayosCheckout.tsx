import { useEffect, useState } from "react";
import QRCode from "qrcode";
import { api } from "./hub";
import { money, stamp, type Row } from "./data";
import { Modal } from "./ui";

export default function PayosCheckout({
    initial,
    close,
    changed,
}: {
    initial: Row;
    close: () => void;
    changed: () => Promise<void>;
}) {
    const [attempt, setAttempt] = useState(initial);
    const [image, setImage] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    useEffect(() => {
        let live = true;
        if (attempt.qr_code)
            QRCode.toDataURL(attempt.qr_code, { width: 300, margin: 2 })
                .then((url) => {
                    if (live) setImage(url);
                })
                .catch(() => {});
        return () => {
            live = false;
        };
    }, [attempt.qr_code]);
    const paid = attempt.status === "paid";
    const failed = attempt.status === "failed";
    const wallet = attempt.purpose === "wallet";
    return (
        <Modal
            title={
                paid
                    ? wallet
                        ? "Đã nạp ví HUB"
                        : "Đã thanh toán hội viên"
                    : wallet
                      ? "Nạp ví HUB qua PayOS"
                      : "Thanh toán hội viên qua PayOS"
            }
            close={close}
        >
            <div className="space-y-4">
                <div className="rounded-2xl bg-slate-50 p-4">
                    <p className="font-bold">{attempt.order_info}</p>
                    <p className="my-2 text-2xl font-black">
                        {money(attempt.amount)}
                    </p>
                    <p className="text-sm text-slate-600">
                        Mã giao dịch: {attempt.order_id}
                    </p>
                </div>
                {paid ? (
                    <p role="status" className="font-bold text-green-700">
                        {wallet
                            ? "Ngân hàng đã xác nhận. Số tiền đã được cộng vào ví HUB của khách."
                            : "Ngân hàng đã xác nhận thanh toán. Hội viên của khách đã được kích hoạt."}
                    </p>
                ) : failed ? (
                    <p role="status">
                        {wallet
                            ? "Thanh toán đã hủy hoặc hết hạn. Ví HUB chưa được cộng tiền."
                            : "Thanh toán đã hủy hoặc hết hạn. Hội viên chưa được kích hoạt."}
                    </p>
                ) : attempt.status === "credited" ||
                  attempt.status === "partial" ? (
                    <p role="status">
                        {wallet
                            ? "Khoản tiền đã được đối chiếu và lưu vào ví HUB. Kiểm tra số dư và lịch sử nạp tiền của khách."
                            : "Khoản tiền đã được chi nhánh đối chiếu và lưu vào ví HUB. Kiểm tra số dư của khách trước khi mua lại gói hội viên."}
                    </p>
                ) : (
                    <>
                        <p>
                            {wallet
                                ? "Khách quét mã bằng ứng dụng ngân hàng để nạp tiền. Ví HUB chỉ được cộng sau khi ngân hàng xác nhận thanh toán."
                                : "Khách quét mã bằng ứng dụng ngân hàng và thanh toán đúng số tiền. Hội viên chỉ được kích hoạt sau khi ngân hàng xác nhận đủ tiền."}
                        </p>
                        {image && (
                            <img
                                src={image}
                                alt={
                                    wallet
                                        ? "Mã QR chuyển khoản nạp ví HUB"
                                        : "Mã QR chuyển khoản ngân hàng cho gói hội viên"
                                }
                                className="mx-auto w-64 rounded-xl border border-slate-200"
                            />
                        )}
                        <p className="text-sm text-slate-600">
                            Hạn thanh toán: {stamp(attempt.expires_at)}
                        </p>
                        {attempt.review_reason && (
                            <p role="status" className="text-amber-800">
                                {attempt.review_reason}
                            </p>
                        )}
                        {attempt.pay_url && (
                            <a
                                className="btn-outline inline-flex"
                                href={attempt.pay_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Mở trang thanh toán PayOS ↗
                            </a>
                        )}
                    </>
                )}
                {error && (
                    <p role="alert" className="text-sm text-red-700">
                        {error}
                    </p>
                )}
                <div className="flex flex-wrap gap-3">
                    {!paid && !failed && (
                        <button
                            type="button"
                            className="btn"
                            disabled={busy}
                            onClick={async () => {
                                setBusy(true);
                                setError("");
                                try {
                                    const response = await api(
                                        "payments/payos/" +
                                            attempt.id +
                                            (attempt.pay_url
                                                ? "/sync"
                                                : "/resume"),
                                        "POST",
                                    );
                                    setAttempt(response.data);
                                    if (
                                        [
                                            "paid",
                                            "failed",
                                            "credited",
                                            "partial",
                                        ].includes(response.data.status)
                                    )
                                        await changed();
                                } catch (failure) {
                                    setError((failure as Error).message);
                                } finally {
                                    setBusy(false);
                                }
                            }}
                        >
                            {busy
                                ? "Đang kiểm tra ngân hàng…"
                                : attempt.pay_url
                                  ? "Kiểm tra thanh toán"
                                  : "Tiếp tục tạo mã thanh toán"}
                        </button>
                    )}
                    <button
                        type="button"
                        className="btn-outline"
                        onClick={close}
                    >
                        Đóng
                    </button>
                </div>
                {!paid && !failed && (
                    <p className="text-xs text-slate-500">
                        Đóng cửa sổ không hủy giao dịch. Bấm “
                        {wallet ? "Nạp ví" : "Mua hội viên"}” ở khách hàng này
                        để mở lại thanh toán đang chờ.
                    </p>
                )}
            </div>
        </Modal>
    );
}
