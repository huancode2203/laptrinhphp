import { useEffect, useState } from "react";
import CustomerPicker from "./CustomerPicker";
import { api, key, useHub } from "./hub";
import { money, stamp, statusText, type Row } from "./data";
import { Modal } from "./ui";
export default function PosCheckout({
    branch,
    total,
    close,
    save,
}: {
    branch: number;
    total: number;
    close: () => void;
    save: (d: Row) => Promise<void>;
}) {
    const { data, run, loading } = useHub();
    const [method, setMethod] = useState("cash"),
        [customer, setCustomer] = useState<number | string>(""),
        [booking, setBooking] = useState<Row | null>(null),
        [reference, setReference] = useState(""),
        [bookings, setBookings] = useState<Row[]>([]),
        [fetching, setFetching] = useState(false),
        [version, setVersion] = useState(0),
        [error, setError] = useState(""),
        [requestKey] = useState(key);
    const canAttach =
        data?.user.role === "admin" ||
        (data?.permissions?.["bookings.read"] &&
            data?.permissions?.["bookings.write"] &&
            data?.permissions?.["pos.write"]);
    const locked = loading || fetching;
    useEffect(() => {
        if (method !== "booking" || !canAttach) return;
        let live = true;
        setBooking(null);
        setBookings([]);
        setError("");
        setFetching(true);
        api("manage/sales/open-bookings?branch_id=" + branch)
            .then((response) => {
                if (live) setBookings(response.data);
            })
            .catch((failure) => {
                if (live) setError(failure.message);
            })
            .finally(() => {
                if (live) setFetching(false);
            });
        return () => {
            live = false;
        };
    }, [branch, method, canAttach, version]);
    return (
        <Modal
            title="Thanh toán hoặc gộp vào sân"
            close={() => {
                if (!locked) close();
            }}
            className="form-dialog form-dialog-compact"
        >
            <form
                className="hub-form space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (
                        locked ||
                        (method === "booking" &&
                            (!canAttach ||
                                !booking ||
                                !["confirmed", "checked_in"].includes(
                                    booking.status,
                                )))
                    )
                        return;
                    setError("");
                    run(async () => {
                        try {
                            await save({
                                request_key: requestKey,
                                method,
                                ...(method === "booking"
                                    ? { booking_id: booking!.id }
                                    : {
                                          ...(customer
                                              ? { user_id: Number(customer) }
                                              : {}),
                                      }),
                            });
                            close();
                        } catch (failure) {
                            setError((failure as Error).message);
                            throw failure;
                        }
                    });
                }}
            >
                <div className="rounded-xl bg-green-50 p-4">
                    <span>Tổng giỏ hàng</span>
                    <b className="float-right">{money(total)}</b>
                </div>
                <label className="block text-sm font-bold">
                    Cách thanh toán
                    <select
                        aria-label="Cách thanh toán POS"
                        className="input mt-2 w-full"
                        value={method}
                        disabled={locked}
                        onChange={(e) => {
                            setMethod(e.target.value);
                            setBooking(null);
                            setError("");
                        }}
                    >
                        <option value="cash">Tiền mặt</option>
                        <option value="bank">Chuyển khoản</option>
                        <option value="wallet">Ví khách hàng</option>
                        {canAttach && (
                            <option value="booking">
                                Gộp vào hóa đơn sân · thu sau
                            </option>
                        )}
                    </select>
                </label>
                {method === "booking" ? (
                    <>
                        <label className="block text-sm">
                            Chọn sân và khách
                            <select
                                aria-label="Sân và khách cần gộp hóa đơn"
                                className="input mt-2 w-full"
                                value={booking?.id ?? ""}
                                disabled={locked}
                                onChange={(e) =>
                                    setBooking(
                                        bookings.find(
                                            (b) =>
                                                b.id === Number(e.target.value),
                                        ) ?? null,
                                    )
                                }
                            >
                                <option value="">
                                    {fetching
                                        ? "Đang tải sân đang chơi…"
                                        : "Chọn sân / khách…"}
                                </option>
                                {["checked_in", "confirmed"].map((status) => (
                                    <optgroup
                                        key={status}
                                        label={
                                            status === "checked_in"
                                                ? "Khách đang chơi"
                                                : "Lịch hôm nay · chưa nhận sân"
                                        }
                                    >
                                        {bookings
                                            .filter((b) => b.status === status)
                                            .map((b) => (
                                                <option key={b.id} value={b.id}>
                                                    {b.court?.name} ·{" "}
                                                    {b.customer?.name} ·{" "}
                                                    {stamp(b.starts_at)} ·{" "}
                                                    {b.reference}
                                                </option>
                                            ))}
                                    </optgroup>
                                ))}
                            </select>
                        </label>
                        <div className="flex items-center justify-between gap-3 text-sm">
                            <span className="text-slate-500">
                                Sân đang chơi được cập nhật theo hiện tại.
                            </span>
                            <button
                                type="button"
                                className="auth-text-button shrink-0"
                                disabled={locked}
                                onClick={() =>
                                    setVersion((previous) => previous + 1)
                                }
                            >
                                Làm mới sân
                            </button>
                        </div>
                        {!fetching && !bookings.length && !error && (
                            <p
                                role="status"
                                className="rounded-xl bg-slate-50 p-3 text-sm"
                            >
                                Cơ sở chưa có khách đang chơi hoặc lịch đã xác
                                nhận hôm nay. Bạn có thể tìm một lịch khác bằng
                                mã bên dưới.
                            </p>
                        )}
                        <div className="flex gap-2">
                            <input
                                aria-label="Mã lịch cần gộp"
                                className="input min-w-0 flex-1"
                                placeholder="Hoặc nhập mã lịch khác"
                                value={reference}
                                maxLength={32}
                                disabled={locked}
                                onChange={(e) => setReference(e.target.value)}
                            />
                            <button
                                type="button"
                                className="btn-outline"
                                disabled={locked || !reference.trim()}
                                onClick={() =>
                                    run(async () => {
                                        setBooking(null);
                                        setError("");
                                        try {
                                            const r = await api(
                                                "manage/booking-search?" +
                                                    new URLSearchParams({
                                                        branch_id:
                                                            String(branch),
                                                        reference:
                                                            reference.trim(),
                                                    }),
                                            );
                                            setBooking(r.data);
                                        } catch (failure) {
                                            setError(
                                                (failure as Error).message,
                                            );
                                            throw failure;
                                        }
                                    })
                                }
                            >
                                Tìm lịch
                            </button>
                        </div>
                        {booking && (
                            <div className="rounded-xl bg-[#f3f6f4] p-4 text-sm">
                                <b>
                                    {booking.court?.name} ·{" "}
                                    {booking.customer?.name}
                                </b>
                                <p className="mt-2">
                                    {booking.status_label ||
                                        statusText(booking.status)}{" "}
                                    · {booking.reference}
                                </p>
                                <p className="mt-2">
                                    {stamp(booking.starts_at)}
                                </p>
                                {!["confirmed", "checked_in"].includes(
                                    booking.status,
                                ) && (
                                    <p
                                        role="alert"
                                        className="field-error mt-3"
                                    >
                                        Lịch này đã kết thúc hoặc bị hủy. Vui
                                        lòng chọn sân có lịch đã xác nhận hoặc
                                        khách đang chơi.
                                    </p>
                                )}
                                <dl className="mt-3 space-y-2">
                                    <div className="flex justify-between gap-3">
                                        <dt>Tổng hóa đơn hiện tại</dt>
                                        <dd>{money(booking.total_amount)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt>Đã thanh toán</dt>
                                        <dd>{money(booking.paid_amount)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt>Tiền hàng thêm vào</dt>
                                        <dd>+{money(total)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3 border-t pt-2">
                                        <dt>Còn phải thu sau khi gộp</dt>
                                        <dd className="font-bold">
                                            {money(
                                                booking.remaining_amount +
                                                    total,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        )}
                        <p className="text-sm text-slate-500">
                            Hàng được trừ kho và thêm vào hóa đơn. Thu chung với
                            tiền sân trong chi tiết lịch. Chưa thu tiền ở bước
                            này; sân đã thanh toán đủ vẫn có thể mua thêm.
                        </p>
                    </>
                ) : (
                    <CustomerPicker
                        branch={branch}
                        value={customer}
                        onChange={setCustomer}
                    />
                )}
                {error && (
                    <p role="alert" className="form-alert">
                        {error}
                    </p>
                )}
                <div className="form-actions">
                    <div>
                        <button
                            type="button"
                            className="btn-outline"
                            disabled={locked}
                            onClick={close}
                        >
                            Hủy
                        </button>
                        <button
                            className="btn"
                            disabled={
                                locked ||
                                (method === "wallet" && !customer) ||
                                (method === "booking" &&
                                    (!booking ||
                                        !["confirmed", "checked_in"].includes(
                                            booking.status,
                                        )))
                            }
                        >
                            {method === "booking"
                                ? "Thêm vào hóa đơn sân"
                                : "Xác nhận đã thanh toán"}
                        </button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
