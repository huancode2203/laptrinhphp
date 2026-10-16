import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
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
    const { run, toast, loading } = useHub();
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
    }, [start, end, booking.id]);
    const selected = options.find((c) => String(c.id) === court && c.available);
    return (
        <Modal title="Đổi sân hoặc khung giờ" close={close}>
            <p className="mb-5 text-sm text-slate-500">
                Chọn sân trống trong cùng chi nhánh. Giá sân được tính lại theo
                khung giờ mới; số tiền đã thu được giữ nguyên.
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
                            },
                        );
                        await after();
                        toast("Đã đổi lịch và thông báo tới khách hàng.");
                        close();
                    });
                }}
            >
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
                <button
                    className="btn form-field-wide"
                    disabled={!selected || checking || loading}
                >
                    Xác nhận đổi lịch
                </button>
            </form>
        </Modal>
    );
}
