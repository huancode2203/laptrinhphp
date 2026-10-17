import { useEffect, useState } from "react";
import { api } from "./hub";
import { money, stamp, statusText, type Row } from "./data";
import { Card, Table, cell, Empty } from "./ui";

const definitions: Record<
    string,
    { title: string; headers: string[]; states?: string[][] }
> = {
    wallet: {
        title: "Toàn bộ lịch sử ví",
        headers: ["Thời gian", "Nội dung", "Số tiền", "Số dư sau giao dịch"],
    },
    payments: {
        title: "Toàn bộ giao dịch thanh toán",
        headers: ["Thời gian", "Mã giao dịch", "Số tiền", "Trạng thái"],
        states: [
            ["pending", "Chờ xác nhận"],
            ["paid", "Đã thanh toán"],
            ["credited", "Đã cộng ví"],
            ["failed", "Không thành công"],
        ],
    },
    points: {
        title: "Lịch sử điểm thưởng",
        headers: ["Thời gian", "Nội dung", "Điểm", "Điểm còn lại"],
    },
    audits: {
        title: "Nhật ký hoạt động",
        headers: ["Thời gian", "Hoạt động", "Đối tượng", "Chi tiết"],
    },
    reports: {
        title: "Báo cáo vi phạm cộng đồng",
        headers: ["Thời gian", "Bài viết", "Lý do", "Trạng thái"],
        states: [
            ["pending", "Chờ xử lý"],
            ["resolved", "Đã xử lý"],
        ],
    },
};

export default function HistoryBrowser({
    kind,
    admin = false,
    version = 0,
    onSelect,
}: {
    kind: string;
    admin?: boolean;
    version?: number;
    onSelect?: (row: Row) => void;
}) {
    const definition = definitions[kind];
    const [from, setFrom] = useState(""),
        [to, setTo] = useState("");
    const [page, setPage] = useState(1),
        [search, setSearch] = useState(""),
        [state, setState] = useState(kind === "reports" ? "pending" : ""),
        [result, setResult] = useState<Row | null>(null),
        [error, setError] = useState(""),
        [retry, setRetry] = useState(0);
    useEffect(() => {
        let live = true;
        setResult(null);
        setError("");
        const timer = setTimeout(
            () =>
                api(
                    (admin ? "admin" : "account") +
                        "/history/" +
                        kind +
                        "?" +
                        new URLSearchParams({
                            page: String(page),
                            per_page: "20",
                            search,
                            state,
                            from,
                            to,
                        }),
                )
                    .then((response) => {
                        if (live) setResult(response.data);
                    })
                    .catch((e) => {
                        if (live) setError(e.message);
                    }),
            200,
        );
        return () => {
            live = false;
            clearTimeout(timer);
        };
    }, [kind, page, search, state, retry, version, admin, from, to]);
    const cells = (row: Row): React.ReactNode[] => {
        if (kind === "wallet")
            return [
                stamp(row.created_at),
                row.description,
                money(row.amount),
                money(row.balance_after),
            ];
        if (kind === "payments")
            return [
                stamp(row.created_at),
                row.order_info + " · " + row.order_id,
                money(row.amount),
                <div className="space-y-2">
                    <span>{statusText(row.status)}</span>
                    {row.status === "pending" &&
                        row.pay_url &&
                        Date.parse(row.expires_at) > Date.now() && (
                            <a
                                className="block font-bold text-emerald-700"
                                href={row.pay_url}
                            >
                                Tiếp tục thanh toán →
                            </a>
                        )}
                </div>,
            ];
        if (kind === "points")
            return [
                stamp(row.created_at),
                row.description,
                row.points,
                row.balance,
            ];
        if (kind === "reports")
            return [
                stamp(row.created_at),
                row.title,
                row.reason,
                row.status === "pending" ? "Chờ xử lý" : "Đã xử lý",
            ];
        return [
            stamp(row.created_at),
            row.action,
            row.subject_type + " #" + row.subject_id,
            typeof row.details === "string"
                ? row.details
                : JSON.stringify(row.details),
        ];
    };
    return (
        <Card>
            <h2 className="mb-4 text-xl font-bold">{definition.title}</h2>
            <div className="mb-4 flex flex-wrap items-end gap-3 text-sm">
                <label>
                    Từ ngày
                    <input
                        className="input mt-1 block"
                        type="date"
                        value={from}
                        onChange={(e) => {
                            setFrom(e.target.value);
                            setPage(1);
                        }}
                    />
                </label>
                <label>
                    Đến ngày
                    <input
                        className="input mt-1 block"
                        type="date"
                        min={from || undefined}
                        value={to}
                        onChange={(e) => {
                            setTo(e.target.value);
                            setPage(1);
                        }}
                    />
                </label>
                {(from || to) && (
                    <button
                        className="btn-outline"
                        onClick={() => {
                            setFrom("");
                            setTo("");
                            setPage(1);
                        }}
                    >
                        Tất cả thời gian
                    </button>
                )}
                {admin && kind === "audits" && (
                    <a
                        className="btn-outline"
                        href={
                            "/api/v1/admin/history/audits?" +
                            new URLSearchParams({
                                search,
                                from,
                                to,
                                download: "1",
                            })
                        }
                    >
                        Tải CSV
                    </a>
                )}
            </div>
            <div className="mb-4 flex flex-wrap gap-3">
                <input
                    aria-label={
                        "Tìm " + definition.title.toLocaleLowerCase("vi")
                    }
                    className="input min-w-48 flex-1"
                    placeholder="Tìm nội dung hoặc mã…"
                    value={search}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        setPage(1);
                    }}
                />
                {definition.states && (
                    <select
                        className="input"
                        aria-label={
                            "Lọc " + definition.title.toLocaleLowerCase("vi")
                        }
                        value={state}
                        onChange={(e) => {
                            setState(e.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">Tất cả trạng thái</option>
                        {definition.states.map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                )}
            </div>
            {error && (
                <div role="alert" className="mb-4 text-red-700">
                    {error}{" "}
                    <button
                        className="btn-outline"
                        onClick={() => setRetry((value) => value + 1)}
                    >
                        Thử lại
                    </button>
                </div>
            )}
            {!result && !error && <p role="status">Đang tải lịch sử…</p>}
            {result && (
                <>
                    <Table
                        pageSize={null}
                        headers={[
                            ...definition.headers,
                            ...(onSelect ? ["Thao tác"] : []),
                        ]}
                    >
                        {result.data.map((row: Row) => (
                            <tr key={row.id}>
                                {cells(row).map((value, index) => (
                                    <td
                                        key={index}
                                        className={
                                            cell + " max-w-sm break-words"
                                        }
                                    >
                                        {value}
                                    </td>
                                ))}
                                {onSelect && (
                                    <td className={cell}>
                                        <button
                                            className="btn-outline"
                                            onClick={() => onSelect(row)}
                                        >
                                            {kind === "payments"
                                                ? row.partner_code?.startsWith(
                                                      "DEMO_",
                                                  )
                                                    ? "Xem giao dịch demo"
                                                    : "Kiểm tra trạng thái"
                                                : "Xem bài"}
                                        </button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </Table>
                    {!result.data.length && (
                        <Empty text="Không có nội dung phù hợp bộ lọc." />
                    )}
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span>
                            {result.total} dòng · Trang {result.current_page}/
                            {result.last_page}
                        </span>
                        <div className="flex gap-2">
                            <button
                                className="btn-outline"
                                disabled={page <= 1}
                                onClick={() => setPage((value) => value - 1)}
                            >
                                Trang trước
                            </button>
                            <button
                                className="btn-outline"
                                disabled={page >= result.last_page}
                                onClick={() => setPage((value) => value + 1)}
                            >
                                Trang sau
                            </button>
                        </div>
                    </div>
                </>
            )}
        </Card>
    );
}
