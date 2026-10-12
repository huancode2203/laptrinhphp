import { useEffect, useState } from "react";
import CustomerPicker from "./CustomerPicker";
import { api, key, useHub } from "./hub";
import { money, type Row } from "./data";
import { Modal } from "./ui";

export default function QuickBooking({
    range,
    close,
    done,
}: {
    range: Row;
    close: () => void;
    done: (booking: Row) => void;
}) {
    const { data, run, loading, toast } = useHub();
    const [customer, setCustomer] = useState<number | string>(""),
        [quote, setQuote] = useState<Row | null>(null),
        [error, setError] = useState(""),
        [notes, setNotes] = useState(""),
        [requestKey] = useState(key);
    const start = range.date + "T" + range.time + ":00+07:00";
    const endDate = new Date(
        new Date(start).getTime() + range.duration * 3600000,
    );
    const end =
        range.date +
        "T" +
        endDate.toLocaleTimeString("en-GB", {
            timeZone: "Asia/Ho_Chi_Minh",
            hour12: false,
        }) +
        "+07:00";
    useEffect(() => {
        let live = true;
        setQuote(null);
        api("courts/" + range.court_id + "/quote", "POST", {
            starts_at: start,
            ends_at: end,
            ...(customer ? { user_id: Number(customer) } : {}),
        })
            .then((r) => {
                if (live) {
                    setQuote(r.data);
                    setError("");
                }
            })
            .catch((e) => {
                if (live) setError(e.message);
            });
        return () => {
            live = false;
        };
    }, [customer, start, end, range.court_id]);
    return (
        <Modal title="Đặt sân nhanh" close={close} className="form-dialog">
            <form
                className="hub-form space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    run(async () => {
                        const r = await api("bookings", "POST", {
                            request_key: requestKey,
                            court_id: range.court_id,
                            user_id: Number(customer),
                            starts_at: start,
                            ends_at: end,
                            notes,
                        });
                        toast(
                            "Đã đặt sân. Có thể thu tiền và nhận sân trong chi tiết lịch.",
                        );
                        done(r.data);
                    });
                }}
            >
                <div className="rounded-xl bg-[#0d1f2d] p-4 text-white">
                    <p className="font-bold">
                        {range.court_name} ·{" "}
                        {range.date.split("-").reverse().join("/")}
                    </p>
                    <p className="mt-2 text-2xl font-extrabold">
                        {range.time} – {end.slice(11, 16)}
                    </p>
                    <p className="mt-2 text-sm text-[#b7ff36]">
                        {range.duration} giờ ·{" "}
                        {
                            data!.branches.find(
                                (b: Row) => b.id === range.branch_id,
                            )?.name
                        }
                    </p>
                </div>
                <CustomerPicker
                    branch={range.branch_id}
                    value={customer}
                    onChange={setCustomer}
                />
                <label className="block text-sm">
                    Ghi chú
                    <textarea
                        className="input mt-2 w-full"
                        value={notes}
                        maxLength={1000}
                        onChange={(e) => setNotes(e.target.value)}
                    />
                </label>
                <div className="flex justify-between rounded-xl bg-green-50 p-4">
                    <span>Tổng tiền dự kiến</span>
                    <b>{money(quote?.total_amount ?? range.court_amount)}</b>
                </div>
                {!!quote?.discount_amount && (
                    <p className="text-sm text-green-700">
                        Ưu đãi khách hàng {money(quote.discount_amount)}
                    </p>
                )}
                {error && (
                    <p role="alert" className="text-sm text-red-600">
                        {error}
                    </p>
                )}
                <div className="form-actions">
                    <div>
                        <button
                            type="button"
                            className="btn-outline"
                            disabled={loading}
                            onClick={close}
                        >
                            Hủy
                        </button>
                        <button
                            className="btn"
                            disabled={!customer || !quote || !!error || loading}
                        >
                            Xác nhận đặt {range.duration} giờ
                        </button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
