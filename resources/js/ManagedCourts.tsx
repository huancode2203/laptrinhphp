import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { money, statusText, type Row } from "./data";
import { Editor, Empty, Table, cell } from "./ui";

export default function ManagedCourts({
    branch,
    writable = true,
}: {
    branch: number;
    writable?: boolean;
}) {
    const { toast } = useHub();
    const [search, setSearch] = useState(""),
        [status, setStatus] = useState(""),
        [page, setPage] = useState(1),
        [size, setSize] = useState(10),
        [result, setResult] = useState<Row | null>(null),
        [error, setError] = useState(""),
        [version, setVersion] = useState(0),
        [editing, setEditing] = useState<Row | null>(null);
    useEffect(() => {
        setPage(1);
        setSearch("");
        setStatus("");
    }, [branch]);
    useEffect(() => {
        let live = true;
        setResult(null);
        setError("");
        const timer = setTimeout(
            () =>
                api(
                    "manage/courts?" +
                        new URLSearchParams({
                            branch_id: String(branch),
                            search,
                            status,
                            page: String(page),
                            per_page: String(size),
                        }),
                )
                    .then((r) => {
                        if (live) setResult(r.data);
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
    }, [branch, search, status, page, size, version]);
    return (
        <section aria-label="Danh sách sân chi nhánh">
            <div className="mb-5 flex flex-wrap items-end gap-3">
                <label className="min-w-48 flex-1 text-sm">
                    Tìm sân
                    <input
                        className="input mt-2 w-full"
                        aria-label="Tìm sân trong chi nhánh"
                        placeholder="Nhập tên hoặc số sân…"
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setPage(1);
                        }}
                    />
                </label>
                <label className="text-sm">
                    Trạng thái
                    <select
                        className="input mt-2 block"
                        aria-label="Lọc trạng thái sân"
                        value={status}
                        onChange={(e) => {
                            setStatus(e.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">Tất cả trạng thái</option>
                        {["active", "maintenance", "locked"].map((s) => (
                            <option key={s} value={s}>
                                {statusText(s)}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">
                    Mỗi trang
                    <select
                        className="input mt-2 block"
                        aria-label="Số sân mỗi trang"
                        value={size}
                        onChange={(e) => {
                            setSize(Number(e.target.value));
                            setPage(1);
                        }}
                    >
                        {[10, 20, 50].map((n) => (
                            <option key={n}>{n}</option>
                        ))}
                    </select>
                </label>
                {writable && (
                    <button
                        className="btn"
                        onClick={() =>
                            setEditing({
                                status: "active",
                                hourly_rate: 100000,
                                peak_rate: 180000,
                                weekend_rate: 200000,
                            })
                        }
                    >
                        + Thêm sân
                    </button>
                )}
            </div>
            {error ? (
                <p role="alert" className="form-alert">
                    {error}
                    <button
                        className="btn-outline"
                        onClick={() => setVersion((v) => v + 1)}
                    >
                        Thử lại
                    </button>
                </p>
            ) : !result ? (
                <Empty text="Đang tải sân…" />
            ) : (
                <>
                    <p className="mb-3 text-sm">
                        {result.total} sân phù hợp · Trang {result.current_page}
                        /{result.last_page}
                    </p>
                    <Table
                        pageSize={null}
                        headers={[
                            "Sân",
                            "Giá thường / cao điểm / cuối tuần",
                            "Trạng thái",
                            "Thao tác",
                        ]}
                    >
                        {result.data.map((court: Row) => (
                            <tr key={court.id}>
                                <td className={cell}>
                                    <b>{court.name}</b>
                                </td>
                                <td className={cell}>
                                    {money(court.hourly_rate)} /{" "}
                                    {money(court.peak_rate)} /{" "}
                                    {money(court.weekend_rate)}
                                </td>
                                <td className={cell}>
                                    {statusText(court.status)}
                                </td>
                                <td className={cell}>
                                    {writable && (
                                        <button
                                            className="btn-outline text-sm"
                                            onClick={() => setEditing(court)}
                                        >
                                            Sửa sân
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Table>
                    {!result.data.length && (
                        <Empty text="Không tìm thấy sân. Thử tên khác hoặc bỏ bộ lọc." />
                    )}
                    <div className="mt-4 flex items-center justify-between gap-3">
                        <button
                            className="btn-outline"
                            disabled={page <= 1}
                            onClick={() => setPage((p) => p - 1)}
                        >
                            Trước
                        </button>
                        <span>
                            {page} / {result.last_page}
                        </span>
                        <button
                            className="btn-outline"
                            disabled={page >= result.last_page}
                            onClick={() => setPage((p) => p + 1)}
                        >
                            Sau
                        </button>
                    </div>
                </>
            )}
            {editing && (
                <Editor
                    title={editing.id ? "Cập nhật sân" : "Thêm sân"}
                    initial={editing}
                    close={() => setEditing(null)}
                    fields={[
                        {
                            name: "name",
                            label: "Tên sân",
                            required: true,
                            maxLength: 100,
                        },
                        {
                            name: "status",
                            label: "Trạng thái",
                            required: true,
                            options: ["active", "maintenance", "locked"].map(
                                (value) => ({
                                    value,
                                    label: statusText(value),
                                }),
                            ),
                        },
                        ...[
                            ["hourly_rate", "Giá thường"],
                            ["peak_rate", "Giá cao điểm"],
                            ["weekend_rate", "Giá cuối tuần"],
                        ].map(([name, label]) => ({
                            name,
                            label,
                            type: "number",
                            required: true,
                            min: 1000,
                            max: 10000000,
                        })),
                    ]}
                    save={async (d) => {
                        await api(
                            "manage/courts" +
                                (editing.id ? "/" + editing.id : ""),
                            editing.id ? "PUT" : "POST",
                            { ...d, branch_id: branch },
                        );
                        setVersion((v) => v + 1);
                        toast("Đã cập nhật thông tin sân.");
                    }}
                />
            )}
        </section>
    );
}
