import { useEffect, useState } from "react";
import { api, key, useHub } from "./hub";
import { localDateTime, money, type Row } from "./data";
import { Modal } from "./ui";
export default function RescheduleBooking({
    booking,
    close,
    after,
}: {
    booking: Row;
    close: () => void;
    after: () => Promise<void>;
}) {
    const { run, toast, loading, data } = useHub();
    const [requestKey] = useState(key);
    const [wholeSeries, setWholeSeries] = useState(false);
    const [quote, setQuote] = useState<Row | null>(null),
        [quoteError, setQuoteError] = useState("");
    const [start, setStart] = useState(localDateTime(booking.starts_at)),
        [end, setEnd] = useState(localDateTime(booking.ends_at)),
        [court, setCourt] = useState(String(booking.court_id)),
        [options, setOptions] = useState<Row[]>([]),
        [error, setError] = useState(""),
        [checking, setChecking] = useState(false);
    useEffect(() => {
        let live = true;
        setOptions([]);
        setChecking(true);
        setError("");
        const timer = setTimeout(
            () =>
                api(
                    "branches/" +
                        booking.court.branch_id +
                        "/availability?" +
                        new URLSearchParams({
                            starts_at: start,
                            ends_at: end,
                            exclude_booking_id: String(booking.id),
                            ...(wholeSeries ? { exclude_series: "1" } : {}),
                        }),
                )
                    .then((r) => {
                        if (live) setOptions(r.data);
                    })
                    .catch((e) => {
                        if (live) setError(e.message);
                    })
                    .finally(() => {
                        if (live) setChecking(false);
                    }),
            250,
        );
        return () => {
            live = false;
            clearTimeout(timer);
        };
    }, [start, end, booking.id, wholeSeries]);
    const selected = options.find((c) => String(c.id) === court && c.available);
    useEffect(() => {
        let live = true;
        setQuote(null);
        setQuoteError("");
        if (!selected) return;
        const timer = setTimeout(
            () =>
                api("bookings/" + booking.id + "/reschedule-quote", "POST", {
                    court_id: Number(court),
                    starts_at: start,
                    ends_at: end,
                    scope: wholeSeries ? "remaining" : "single",
                })
                    .then((response) => {
                        if (live) setQuote(response.data);
                    })
                    .catch((e) => {
                        if (live) setQuoteError(e.message);
                    }),
            250,
        );
        return () => {
            live = false;
            clearTimeout(timer);
        };
    }, [court, start, end, selected?.id, booking.id, wholeSeries]);
    return (
        <Modal title="Đổi sân hoặc khung giờ" close={close}>
            <p className="mb-5 text-sm text-slate-500">
                Chọn sân trống trong cùng chi nhánh. Giá sân được tính lại theo
                khung giờ mới. Xem lại giá mới và khoản cần thanh toán thêm
                trước khi xác nhận.
            </p>
            <form
                className="hub-form form-grid"
                onSubmit={(e) => {
                    e.preventDefault();
                    run(async () => {
                        await api(
                            "bookings/" + booking.id + "/reschedule",
                            "PATCH",
                            {
                                court_id: Number(court),
                                starts_at: start,
                                ends_at: end,
                                request_key: requestKey,
                                expected_total: quote!.booking.total_amount,
                                expected_refund: quote!.refund_amount,
                                scope: wholeSeries ? "remaining" : "single",
                            },
                        );
                        await after();
                        toast("Đã đổi lịch và thông báo tới khách hàng.");
                        close();
                    });
                }}
            >
                {booking.series_key && (
                    <label className="form-field-wide flex items-start gap-3 rounded-2xl bg-emerald-50 p-4 text-sm">
                        <input
                            type="checkbox"
                            checked={wholeSeries}
                            onChange={(e) => setWholeSeries(e.target.checked)}
                        />
                        <span>
                            Đổi tất cả buổi chưa bắt đầu trong chuỗi. Các buổi
                            giữ khoảng cách ngày hiện có và dùng giờ, sân mới đã
                            chọn.
                        </span>
                    </label>
                )}
                <label>
                    Giờ bắt đầu
                    <input
                        className="input mt-2 w-full"
                        type="datetime-local"
                        required
                        step={1800}
                        value={start}
                        onChange={(e) => setStart(e.target.value)}
                    />
                </label>
                <label>
                    Giờ kết thúc
                    <input
                        className="input mt-2 w-full"
                        type="datetime-local"
                        required
                        step={1800}
                        value={end}
                        onChange={(e) => setEnd(e.target.value)}
                    />
                </label>
                <label className="form-field-wide">
                    Sân trống tại {booking.court.branch?.name}
                    <select
                        className="input mt-2 w-full"
                        required
                        value={court}
                        onChange={(e) => setCourt(e.target.value)}
                    >
                        <option value="">Chọn sân trống</option>
                        {options.map((c) => (
                            <option
                                key={c.id}
                                value={c.id}
                                disabled={!c.available}
                            >
                                {c.name} ·{" "}
                                {c.available
                                    ? money(c.court_amount) + " tiền sân"
                                    : "Không còn trống"}
                            </option>
                        ))}
                    </select>
                </label>
                {checking && <p role="status">Đang kiểm tra lịch sân…</p>}
                {error && (
                    <p role="alert" className="field-error form-field-wide">
                        {error}
                    </p>
                )}
                {!checking && !error && !selected && (
                    <p role="alert" className="form-field-wide text-amber-700">
                        Sân đã chọn không trống trong khung giờ mới. Hãy chọn
                        sân hoặc thời gian khác.
                    </p>
                )}
                {quoteError && (
                    <p role="alert" className="field-error form-field-wide">
                        {quoteError}
                    </p>
                )}
                {selected && !quote && !quoteError && (
                    <p className="form-field-wide" role="status">
                        Đang tính chênh lệch hóa đơn…
                    </p>
                )}
                {quote && (
                    <div className="form-field-wide rounded-2xl bg-slate-50 p-4 text-sm space-y-3">
                        <p className="flex justify-between">
                            <span>
                                {wholeSeries
                                    ? "Tổng chuỗi sau khi đổi"
                                    : "Tổng hóa đơn mới"}
                            </span>
                            <b>{money(quote.booking.total_amount)}</b>
                        </p>
                        <p className="flex justify-between">
                            <span>Đã thanh toán</span>
                            <b>
                                {money(
                                    wholeSeries
                                        ? quote.booking.paid_amount
                                        : booking.paid_amount,
                                )}
                            </b>
                        </p>
                        <p className="flex justify-between">
                            <span>Cần thu thêm</span>
                            <b>{money(quote.booking.remaining_amount)}</b>
                        </p>
                        {quote.sessions && (
                            <details>
                                <summary className="cursor-pointer font-bold">
                                    Xem {quote.sessions.length} buổi sau khi đổi
                                </summary>
                                {quote.sessions.map((session: Row) => (
                                    <p
                                        className="mt-2 flex flex-wrap justify-between gap-2"
                                        key={session.id}
                                    >
                                        <span>
                                            {new Date(
                                                session.starts_at,
                                            ).toLocaleString("vi-VN")}{" "}
                                            · {session.reference}
                                        </span>
                                        <b>{money(session.total_amount)}</b>
                                    </p>
                                ))}
                            </details>
                        )}
                        {quote.refund_amount > 0 && (
                            <p
                                role="alert"
                                className="rounded-xl bg-amber-50 p-4 text-sm"
                            >
                                Lịch mới có giá thấp hơn số tiền đã trả. Chọn
                                lịch có giá phù hợp hoặc liên hệ chi nhánh để
                                được hỗ trợ; hệ thống không xử lý hoàn tiền.
                            </p>
                        )}
                    </div>
                )}
                <button
                    className="btn form-field-wide"
                    disabled={
                        !selected ||
                        !quote ||
                        checking ||
                        loading ||
                        quote.refund_amount > 0
                    }
                >
                    Xác nhận đổi lịch
                </button>
            </form>
        </Modal>
    );
}
