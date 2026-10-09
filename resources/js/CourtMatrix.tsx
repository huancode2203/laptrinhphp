import { useEffect, useRef, useState } from "react";
import { money, type Row } from "./data";

const labels: Record<string, string> = {
    available: "Trống",
    reserved: "Đã đặt",
    playing: "Đang chơi",
    fixed: "Lịch cố định",
    held: "Giữ chỗ",
    event: "Sự kiện",
    maintenance: "Bảo trì",
    locked: "Tạm khóa",
    past: "Đã qua",
};
export default function CourtMatrix({
    schedule,
    onPick,
    fixedWindow,
    selectedCourt,
    onBusy,
    onReserved,
}: {
    schedule: Row | null;
    onPick: (range: Row) => void;
    fixedWindow?: { time: string; duration: number };
    selectedCourt?: number;
    onBusy?: (court: Row) => void;
    onReserved?: (slot: Row) => void;
}) {
    const [range, setRange] = useState<Row | null>(null);
    const viewport = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (!fixedWindow || !schedule || !viewport.current) return;
        const area = viewport.current;
        const heading = area.querySelector<HTMLElement>(
            '[data-time="' + fixedWindow.time + '"]',
        );
        if (heading)
            area.scrollTo({
                left: Math.max(
                    0,
                    area.scrollLeft +
                        heading.getBoundingClientRect().left -
                        area.getBoundingClientRect().left -
                        96,
                ),
            });
    }, [schedule, fixedWindow?.time]);

    const drag = useRef<Row | null>(null);
    const anchor = useRef<Row | null>(null);
    const pick = useRef(onPick);
    pick.current = onPick;
    useEffect(() => {
        setRange(null);
        anchor.current = null;
    }, [
        schedule?.date,
        schedule?.branch_id,
        fixedWindow?.time,
        fixedWindow?.duration,
    ]);
    function valid(c: Row, a: number, b: number) {
        return (
            Math.abs(b - a) < 12 &&
            c.slots
                .slice(Math.min(a, b), Math.max(a, b) + 1)
                .every((s: Row) => s.available)
        );
    }
    function selection(c: Row, a: number, b: number): Row {
        const slots = c.slots.slice(Math.min(a, b), Math.max(a, b) + 1);
        return {
            court_id: c.id,
            court_name: c.name,
            branch_id: schedule!.branch_id,
            date: schedule!.date,
            time: slots[0].starts_at.slice(11, 16),
            duration: slots.length / 2,
            court_amount: slots.reduce(
                (n: number, s: Row) => n + s.court_amount,
                0,
            ),
        };
    }
    useEffect(() => {
        function finish() {
            if (drag.current) {
                const { court, start, end } = drag.current;
                drag.current = null;
                if (start !== end && valid(court, start, end))
                    pick.current(selection(court, start, end));
            }
        }
        function cancel() {
            drag.current = null;
            setRange(null);
        }
        window.addEventListener("pointerup", finish);
        window.addEventListener("pointercancel", cancel);
        return () => {
            window.removeEventListener("pointerup", finish);
            window.removeEventListener("pointercancel", cancel);
        };
    }, [schedule]);
    if (!schedule)
        return <p className="p-4 text-sm text-slate-500">Đang tải lịch sân…</p>;
    const first = schedule.courts[0]?.slots ?? [];
    const windowStart = fixedWindow
        ? first.findIndex(
              (s: Row) => s.starts_at.slice(11, 16) === fixedWindow.time,
          )
        : -1;
    const windowEnd = windowStart + (fixedWindow?.duration ?? 0) * 2 - 1;
    return (
        <div>
            <div className="mb-4 flex flex-wrap gap-2 text-xs">
                {Object.entries(labels)
                    .filter(([s]) => s !== "past")
                    .map(([s, l]) => (
                        <span
                            key={s}
                            className={
                                "slot " + (s === "fixed" ? "fixed-booking" : s)
                            }
                        >
                            {l}
                        </span>
                    ))}
            </div>
            {!fixedWindow && (
                <p className="mb-3 text-sm text-slate-500">
                    Kéo qua các ô trống trên cùng một sân để chọn giờ. Có thể
                    chọn ô đầu rồi giữ Shift và chọn ô cuối. Mỗi ô 30 phút.
                </p>
            )}
            <div
                ref={viewport}
                className="max-w-full overflow-auto rounded-xl border border-[#dce4dc] bg-white"
            >
                <table className="w-full select-none text-xs">
                    <thead>
                        <tr>
                            <th className="sticky left-0 z-10 min-w-24 bg-white p-3 text-left">
                                Sân / Giờ
                            </th>
                            {first.map((s: Row) => (
                                <th
                                    data-time={s.starts_at.slice(11, 16)}
                                    className="min-w-16 p-3"
                                    key={s.starts_at}
                                >
                                    {s.starts_at.slice(11, 16)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {schedule.courts.map((c: Row) => (
                            <tr
                                className="border-t border-[#edf1ed]"
                                key={c.id}
                            >
                                <th className="sticky left-0 z-10 bg-white p-3 text-left">
                                    {c.name}
                                </th>
                                {c.slots.map((s: Row, i: number) => {
                                    const inWindow =
                                        !!fixedWindow &&
                                        i >= windowStart &&
                                        i <= windowEnd;
                                    const selectable = fixedWindow
                                        ? i === windowStart &&
                                          windowStart >= 0 &&
                                          valid(c, windowStart, windowEnd)
                                        : s.available;
                                    const marked =
                                        (range &&
                                            range.court_id === c.id &&
                                            i >= range.first &&
                                            i <= range.last) ||
                                        (inWindow && selectedCourt === c.id);
                                    return (
                                        <td key={s.starts_at} className="p-0.5">
                                            <button
                                                type="button"
                                                aria-label={
                                                    c.name +
                                                    " " +
                                                    s.starts_at.slice(11, 16) +
                                                    " · " +
                                                    labels[s.state]
                                                }
                                                aria-pressed={marked}
                                                className={
                                                    "slot w-full min-h-10 " +
                                                    (s.state === "fixed"
                                                        ? "fixed-booking"
                                                        : s.state) +
                                                    (marked
                                                        ? " ring-2 ring-inset ring-[#16a34a] bg-[#b7ff36] text-[#0d1f2d]"
                                                        : "") +
                                                    (fixedWindow && !inWindow
                                                        ? " opacity-40"
                                                        : "")
                                                }
                                                title={
                                                    labels[s.state] +
                                                    (selectable
                                                        ? " · " +
                                                          money(s.court_amount)
                                                        : "")
                                                }
                                                disabled={
                                                    !selectable &&
                                                    !(onBusy && inWindow) &&
                                                    !(
                                                        onReserved &&
                                                        s.booking_id
                                                    )
                                                }
                                                onPointerDown={(e) => {
                                                    if (
                                                        fixedWindow ||
                                                        !selectable ||
                                                        e.pointerType !==
                                                            "mouse" ||
                                                        e.shiftKey
                                                    )
                                                        return;
                                                    e.preventDefault();
                                                    anchor.current = {
                                                        court: c,
                                                        start: i,
                                                    };
                                                    drag.current = {
                                                        court: c,
                                                        start: i,
                                                        end: i,
                                                    };
                                                    setRange({
                                                        court_id: c.id,
                                                        first: i,
                                                        last: i,
                                                    });
                                                }}
                                                onPointerEnter={() => {
                                                    const d = drag.current;
                                                    if (
                                                        d &&
                                                        d.court.id === c.id &&
                                                        valid(c, d.start, i)
                                                    ) {
                                                        d.end = i;
                                                        setRange({
                                                            court_id: c.id,
                                                            first: Math.min(
                                                                d.start,
                                                                i,
                                                            ),
                                                            last: Math.max(
                                                                d.start,
                                                                i,
                                                            ),
                                                        });
                                                    }
                                                }}
                                                onClick={(e) => {
                                                    if (
                                                        onReserved &&
                                                        s.booking_id
                                                    ) {
                                                        onReserved(s);
                                                        return;
                                                    }
                                                    if (fixedWindow) {
                                                        if (selectable) {
                                                            onPick(
                                                                selection(
                                                                    c,
                                                                    windowStart,
                                                                    windowEnd,
                                                                ),
                                                            );
                                                        } else onBusy?.(c);
                                                        return;
                                                    }
                                                    if (
                                                        e.detail > 0 &&
                                                        e.nativeEvent instanceof
                                                            PointerEvent &&
                                                        e.nativeEvent
                                                            .pointerType ===
                                                            "mouse" &&
                                                        !e.shiftKey
                                                    )
                                                        return;
                                                    const a =
                                                        e.shiftKey &&
                                                        anchor.current &&
                                                        anchor.current.court
                                                            .id === c.id
                                                            ? anchor.current
                                                                  .start
                                                            : i;
                                                    if (valid(c, a, i)) {
                                                        setRange({
                                                            court_id: c.id,
                                                            first: Math.min(
                                                                a,
                                                                i,
                                                            ),
                                                            last: Math.max(
                                                                a,
                                                                i,
                                                            ),
                                                        });
                                                        onPick(
                                                            selection(c, a, i),
                                                        );
                                                        anchor.current = {
                                                            court: c,
                                                            start: i,
                                                        };
                                                    }
                                                }}
                                            >
                                                {s.available
                                                    ? marked
                                                        ? "✓"
                                                        : "＋"
                                                    : "●"}
                                            </button>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {!fixedWindow && range && (
                <button
                    type="button"
                    className="btn mt-3"
                    onClick={() => {
                        const c = schedule.courts.find(
                            (v: Row) => v.id === range.court_id,
                        );
                        if (c && valid(c, range.first, range.last))
                            onPick(selection(c, range.first, range.last));
                    }}
                >
                    Đặt khung giờ đã chọn · {(range.last - range.first + 1) / 2}{" "}
                    giờ
                </button>
            )}
            {!schedule.courts.length && (
                <p className="p-4 text-sm">
                    Chi nhánh chưa có sân nhận đặt lịch.
                </p>
            )}
        </div>
    );
}
