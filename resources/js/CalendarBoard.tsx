import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { day, localDateTime, stamp, type Row } from "./data";
import { Editor } from "./ui";
const dateString = (d: Date) =>
    d.getFullYear() +
    "-" +
    String(d.getMonth() + 1).padStart(2, "0") +
    "-" +
    String(d.getDate()).padStart(2, "0");
export default function CalendarBoard({
    branch,
    courts,
}: {
    branch: number;
    courts: Row[];
}) {
    const { toast } = useHub();
    const [date, setDate] = useState(day()),
        [mode, setMode] = useState("week"),
        [rows, setRows] = useState<Row[]>([]),
        [move, setMove] = useState<Row | null>(null);
    const anchor = new Date(date + "T12:00:00");
    let from = new Date(anchor),
        count = 7;
    if (mode === "month") {
        from = new Date(anchor.getFullYear(), anchor.getMonth(), 1, 12);
        count = 42;
    }
    from.setDate(from.getDate() - ((from.getDay() + 6) % 7));
    const dates = Array.from({ length: count }, (_, i) => {
        const d = new Date(from);
        d.setDate(d.getDate() + i);
        return dateString(d);
    });
    async function load() {
        const r = await api(
            "manage/calendar?" +
                new URLSearchParams({
                    branch_id: String(branch),
                    from: dates[0],
                    to: dates[count - 1],
                }),
        );
        setRows(r.data);
    }
    useEffect(() => {
        load().catch((e) => toast(e.message));
    }, [branch, date, mode]);
    function open(b: Row, d?: string, court?: number) {
        if (b.paid_amount > 0 || b.status !== "confirmed") {
            toast("Chỉ đổi lịch chưa thanh toán và chưa bắt đầu.");
            return;
        }
        const duration =
            new Date(b.ends_at).getTime() - new Date(b.starts_at).getTime();
        const start =
            (d ?? localDateTime(b.starts_at).slice(0, 10)) +
            localDateTime(b.starts_at).slice(10);
        const end = new Date(new Date(start + "+07:00").getTime() + duration);
        setMove({
            ...b,
            court_id: court ?? b.court_id,
            starts_at: start,
            ends_at: localDateTime(end.toISOString()),
        });
    }
    function drop(e: React.DragEvent, d: string, court?: number) {
        e.preventDefault();
        const b = rows.find(
            (b) => String(b.id) === e.dataTransfer.getData("booking"),
        );
        if (b) open(b, d, court);
    }
    function card(b: Row) {
        return (
            <button
                key={b.id}
                draggable={b.paid_amount === 0 && b.status === "confirmed"}
                onDragStart={(e) =>
                    e.dataTransfer.setData("booking", String(b.id))
                }
                onClick={() => open(b)}
                className={
                    "calendar-booking " +
                    (b.status === "cancelled" ? "opacity-40" : "")
                }
                title={b.reference + " · " + stamp(b.starts_at)}
            >
                <strong>
                    {localDateTime(b.starts_at).slice(11)}–
                    {localDateTime(b.ends_at).slice(11)}
                </strong>
                <span>
                    {b.court?.name} · {b.customer?.name}
                </span>
                <small>
                    {b.reference}
                    {b.paid_amount > 0 ? " · Đã thu tiền" : ""}
                </small>
            </button>
        );
    }
    return (
        <section>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-2">
                    {[
                        ["week", "Tuần"],
                        ["month", "Tháng"],
                    ].map(([v, label]) => (
                        <button
                            key={v}
                            className={mode === v ? "btn" : "btn-outline"}
                            onClick={() => setMode(v)}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <div className="flex gap-2">
                    <button
                        className="btn-outline"
                        aria-label="Lịch trước"
                        onClick={() => {
                            anchor.setDate(
                                anchor.getDate() - (mode === "week" ? 7 : 28),
                            );
                            setDate(dateString(anchor));
                        }}
                    >
                        ←
                    </button>
                    <input
                        className="input"
                        aria-label="Ngày xem lịch"
                        type="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                    />
                    <button
                        className="btn-outline"
                        aria-label="Lịch sau"
                        onClick={() => {
                            anchor.setDate(
                                anchor.getDate() + (mode === "week" ? 7 : 28),
                            );
                            setDate(dateString(anchor));
                        }}
                    >
                        →
                    </button>
                </div>
            </div>
            <p className="mb-4 text-sm text-slate-500">
                Kéo lịch chưa thanh toán sang ngày hoặc sân khác. Bấm vào lịch
                để chọn giờ và xem lại trước khi xác nhận.
            </p>
            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                {mode === "month" ? (
                    <div className="grid min-w-[720px] grid-cols-7">
                        {["T2", "T3", "T4", "T5", "T6", "T7", "CN"].map((v) => (
                            <div
                                className="border-b p-3 text-center font-bold"
                                key={v}
                            >
                                {v}
                            </div>
                        ))}
                        {dates.map((d) => (
                            <div
                                className="min-h-28 border-b border-r border-slate-100 p-2"
                                key={d}
                                onDragOver={(e) => e.preventDefault()}
                                onDrop={(e) => drop(e, d)}
                            >
                                <p
                                    className={
                                        "mb-2 text-sm font-bold " +
                                        (d === day()
                                            ? "text-[#168449]"
                                            : "text-slate-500")
                                    }
                                >
                                    {+d.slice(8)}
                                    {d.slice(8) === "01"
                                        ? "/" + d.slice(5, 7)
                                        : ""}
                                </p>
                                {rows
                                    .filter(
                                        (b) =>
                                            localDateTime(b.starts_at).slice(
                                                0,
                                                10,
                                            ) === d,
                                    )
                                    .map(card)}
                            </div>
                        ))}
                    </div>
                ) : (
                    <table className="w-full min-w-[1050px] table-fixed">
                        <thead>
                            <tr>
                                <th className="w-24 p-3">Sân</th>
                                {dates.map((d) => (
                                    <th className="p-3 text-sm" key={d}>
                                        {new Date(
                                            d + "T12:00:00",
                                        ).toLocaleDateString("vi-VN", {
                                            weekday: "short",
                                            day: "numeric",
                                            month: "numeric",
                                        })}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {courts.map((c) => (
                                <tr key={c.id}>
                                    <th className="border-t p-2 text-sm">
                                        {c.name}
                                    </th>
                                    {dates.map((d) => (
                                        <td
                                            key={d}
                                            className="h-28 border-l border-t border-slate-100 p-2 align-top"
                                            onDragOver={(e) =>
                                                e.preventDefault()
                                            }
                                            onDrop={(e) => drop(e, d, c.id)}
                                        >
                                            {rows
                                                .filter(
                                                    (b) =>
                                                        b.court_id === c.id &&
                                                        localDateTime(
                                                            b.starts_at,
                                                        ).slice(0, 10) === d,
                                                )
                                                .map(card)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
            {!rows.length && (
                <p className="mt-4 text-sm text-slate-500">
                    Chưa có lịch trong khoảng thời gian này.
                </p>
            )}
            {move && (
                <Editor
                    title={"Đổi lịch " + move.reference}
                    initial={move}
                    close={() => setMove(null)}
                    fields={[
                        {
                            name: "court_id",
                            label: "Sân",
                            required: true,
                            options: courts.map((c) => ({
                                value: c.id,
                                label: c.name,
                            })),
                        },
                        {
                            name: "starts_at",
                            label: "Bắt đầu",
                            type: "datetime-local",
                            required: true,
                        },
                        {
                            name: "ends_at",
                            label: "Kết thúc",
                            type: "datetime-local",
                            required: true,
                        },
                    ]}
                    save={async (d) => {
                        await api(
                            "bookings/" + move.id + "/reschedule",
                            "PATCH",
                            {
                                court_id: Number(d.court_id),
                                starts_at: d.starts_at + "+07:00",
                                ends_at: d.ends_at + "+07:00",
                            },
                        );
                        await load();
                        toast("Đã đổi lịch và cập nhật giá.");
                    }}
                />
            )}
        </section>
    );
}
