import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { stamp, type Row } from "./data";
export default function BookingResume({
    resume,
    pending,
}: {
    resume: () => void;
    pending: () => void;
}) {
    const { data } = useHub();
    const [hold, setHold] = useState<Row | null>(null),
        [count, setCount] = useState(0),
        [now, setNow] = useState(Date.now());
    useEffect(() => {
        if (!data?.user) {
            setHold(null);
            setCount(0);
            return;
        }
        let live = true;
        const load = async () => {
            const results = await Promise.allSettled([
                api("holds/active"),
                api("bookings?pending=1"),
            ]);
            if (!live) return;
            if (results[0].status === "fulfilled")
                setHold(results[0].value.data);
            if (results[1].status === "fulfilled")
                setCount(results[1].value.meta?.total ?? 0);
        };
        void load();
        const timer = setInterval(load, 60000);
        return () => {
            live = false;
            clearInterval(timer);
        };
    }, [data]);
    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);
        return () => clearInterval(timer);
    }, []);
    const remaining = hold
        ? Math.max(0, Math.ceil((Date.parse(hold.expires_at) - now) / 1000))
        : 0;
    if (!data?.user || (!remaining && !count)) return null;
    return (
        <aside className="booking-resume" aria-label="Lịch đang chờ">
            {remaining > 0 && (
                <div>
                    <span>
                        <b>
                            Đang giữ {hold!.court?.name} ·{" "}
                            {hold!.court?.branch?.name}
                        </b>
                        <small>
                            {stamp(hold!.starts_at)} · Còn{" "}
                            {Math.floor(remaining / 60)}:
                            {String(remaining % 60).padStart(2, "0")}
                        </small>
                    </span>
                    <button className="btn" onClick={resume}>
                        Tiếp tục giữ chỗ
                    </button>
                </div>
            )}
            {count > 0 && (
                <div>
                    <span>
                        <b>{count} lịch chờ thanh toán</b>
                        <small>Xem lịch và thanh toán trước hạn đặt cọc.</small>
                    </span>
                    <button className="btn-outline" onClick={pending}>
                        Xem lịch đang chờ
                    </button>
                </div>
            )}
        </aside>
    );
}
