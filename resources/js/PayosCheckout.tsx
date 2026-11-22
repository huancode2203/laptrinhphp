import { useEffect, useRef, useState } from "react";
import QRCode from "qrcode";
import { api, useHub } from "./hub";
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
    const [qrError, setQrError] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const { data } = useHub();
    const customer = data?.user?.role === "customer";
    const onChanged = useRef(changed);
    onChanged.current = changed;
    useEffect(() => {
        if (attempt.status !== "pending" || !attempt.pay_url) return;
        let live = true,
            checking = false;
        const check = async () => {
            if (!live || checking || document.visibilityState === "hidden")
                return;
            checking = true;
            try {
                const response = await api(
                    "payments/payos/" + attempt.id + "/sync",
                    "POST",
                );
                if (!live) return;
                setAttempt(response.data);
                if (
                    ["paid", "failed", "credited", "partial"].includes(
                        response.data.status,
                    )
                )
                    await onChanged.current();
            } catch {
                // The visible manual check remains available if a background check fails.
            } finally {
                checking = false;
            }
        };
        const timer = window.setInterval(check, 10000);
        window.addEventListener("focus", check);
        document.addEventListener("visibilitychange", check);
        return () => {
            live = false;
            window.clearInterval(timer);
            window.removeEventListener("focus", check);
            document.removeEventListener("visibilitychange", check);
        };
    }, [attempt.id, attempt.status, attempt.pay_url]);
    useEffect(() => {
        let live = true;
        setImage("");
        setQrError("");
        if (attempt.qr_code)
            QRCode.toDataURL(attempt.qr_code, { width: 300, margin: 2 })
                .then((url) => {
                    if (live) setImage(url);
                })
                .catch(() => {
                    if (live)
                        setQrError(
                            "Chưa hiển thị được mã QR. Vui lòng mở trang thanh toán PayOS bên dưới để lấy mã ngân hàng.",
                        );
                });
        else if (attempt.pay_url)
            setQrError(
                "Giao dịch này chưa có mã QR ngân hàng. Vui lòng mở trang thanh toán PayOS bên dưới để xem mã.",
            );
        return () => {
            live = false;
        };
    }, [attempt.qr_code, attempt.pay_url]);
    const paid = attempt.status === "paid";
    const failed = attempt.status === "failed";
    const wallet = attempt.purpose === "wallet";
    const booking =
        attempt.purpose === "booking" || attempt.purpose === "series";
    return (
        <Modal
            title={
                paid
                    ? booking
                        ? "Đã nhận thanh toán sân"
                        : wallet
                          ? "Đã nạp ví HUB"
                          : "Đã thanh toán hội viên"
                    : booking
                      ? attempt.purpose === "series"
                          ? "Quét mã thanh toán lịch cố định"
                          : "Quét mã thanh toán sân"
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
                        {booking
                            ? "Ngân hàng đã xác nhận khoản chuyển tiền. Hóa đơn sân đã được cập nhật; bạn có thể đóng cửa sổ để xem số tiền còn lại và nhận sân."
                            : wallet
                              ? customer
                                  ? "Nạp tiền thành công. Số dư ví HUB của bạn đã được cập nhật."
                                  : "Ngân hàng đã xác nhận. Số tiền đã được cộng vào ví HUB của khách."
                              : "Ngân hàng đã xác nhận thanh toán. Hội viên của khách đã được kích hoạt."}
                    </p>
                ) : failed ? (
                    <p role="status">
                        {booking
                            ? "Giao dịch đã hủy hoặc hết hạn. Hóa đơn sân chưa được ghi nhận khoản thanh toán này. Mở lại chi tiết lịch để kiểm tra và tạo mã mới."
                            : wallet
                              ? "Thanh toán đã hủy hoặc hết hạn. Ví HUB chưa được cộng tiền."
                              : "Thanh toán đã hủy hoặc hết hạn. Hội viên chưa được kích hoạt."}
                    </p>
                ) : attempt.status === "credited" ||
                  attempt.status === "partial" ? (
                    <p role="status">
                        {booking
                            ? "Khoản tiền đã được đối chiếu. Kiểm tra số tiền đã thu và số dư HUB của khách trong chi tiết lịch trước khi tạo thanh toán khác."
                            : wallet
                              ? "Khoản tiền đã được đối chiếu và lưu vào ví HUB. Kiểm tra số dư và lịch sử nạp tiền của khách."
                              : "Khoản tiền đã được chi nhánh đối chiếu và lưu vào ví HUB. Kiểm tra số dư của khách trước khi mua lại gói hội viên."}
                    </p>
                ) : (
                    <>
                        <p>
                            {booking
                                ? "Khách quét mã này bằng ứng dụng ngân hàng và chuyển đúng số tiền. Không cần đăng nhập tài khoản khách trên website. Giữ màn hình này mở, hóa đơn tự cập nhật khi PayOS xác nhận."
                                : wallet
                                  ? customer
                                      ? "Quét mã bằng ứng dụng ngân hàng để nạp tiền. Giữ trang này mở, số dư sẽ tự cập nhật sau khi ngân hàng xác nhận."
                                      : "Khách quét mã bằng ứng dụng ngân hàng để nạp tiền. Ví HUB chỉ được cộng sau khi ngân hàng xác nhận thanh toán."
                                  : "Khách quét mã bằng ứng dụng ngân hàng và thanh toán đúng số tiền. Hội viên chỉ được kích hoạt sau khi ngân hàng xác nhận đủ tiền."}
                        </p>
                        {image && (
                            <img
                                src={image}
                                alt={
                                    booking
                                        ? "Mã QR chuyển khoản ngân hàng cho lịch sân"
                                        : wallet
                                          ? "Mã QR chuyển khoản nạp ví HUB"
                                          : "Mã QR chuyển khoản ngân hàng cho gói hội viên"
                                }
                                className="mx-auto w-72 max-w-full rounded-xl border border-slate-200"
                            />
                        )}
                        {qrError && (
                            <p role="alert" className="text-sm text-amber-800">
                                {qrError}
                            </p>
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
                        {booking ? (
                            "Đóng cửa sổ không hủy giao dịch. Mở chi tiết lịch và chọn Hiện mã QR chuyển khoản để tiếp tục dùng mã đang chờ."
                        ) : customer ? (
                            "Đóng cửa sổ không hủy giao dịch. Bạn có thể kiểm tra lại trong lịch sử thanh toán tại Ví HUB."
                        ) : (
                            <>
                                Đóng cửa sổ không hủy giao dịch. Bấm “
                                {wallet ? "Nạp ví" : "Mua hội viên"}” ở khách
                                hàng này để mở lại thanh toán đang chờ.
                            </>
                        )}
                    </p>
                )}
            </div>
        </Modal>
    );
}
