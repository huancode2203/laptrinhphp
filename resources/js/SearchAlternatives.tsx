import { money, type Row } from "./data";
export default function SearchAlternatives({
    rows,
    book,
}: {
    rows?: Row[];
    book: (initial: Row) => void;
}) {
    if (!rows?.length) return null;
    return (
        <div className="mt-5 space-y-3">
            <h3 className="font-bold">Có thể phù hợp nếu bạn đổi giờ</h3>
            <p className="text-sm text-slate-500">
                Các gợi ý dưới đây khác giờ ban đầu và đã được đối chiếu lịch
                trống. Hệ thống kiểm tra lại khi bạn đặt sân.
            </p>
            {rows.map((c) => (
                <div
                    key={c.court_id + c.starts_at}
                    className="rounded-2xl border border-amber-200 bg-amber-50 p-4"
                >
                    <p className="text-sm font-bold text-amber-800">
                        {c.reason}
                    </p>
                    <b>
                        {c.branch_name} · {c.court_name}
                    </b>
                    <p className="text-sm">
                        {c.ward} · {money(c.total_amount)}
                    </p>
                    <button
                        className="btn-outline mt-3"
                        onClick={() =>
                            book({
                                branch_id: c.branch_id,
                                court_id: c.court_id,
                                starts_at: c.starts_at,
                                hours:
                                    (Date.parse(c.ends_at) -
                                        Date.parse(c.starts_at)) /
                                    3600000,
                            })
                        }
                    >
                        Chọn khung giờ này
                    </button>
                </div>
            ))}
        </div>
    );
}
