import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { stamp, type Row } from "./data";
import { Card, Editor, Empty } from "./ui";
import HistoryBrowser from "./HistoryBrowser";

const states = [
    ["pending", "Chờ duyệt"],
    ["approved", "Đã duyệt"],
    ["rejected", "Đã từ chối"],
    ["hidden", "Đã gỡ"],
    ["all", "Tất cả"],
];
export default function AdminModeration({
    after,
}: {
    after: () => Promise<void>;
}) {
    const { data, toast } = useHub();
    const [state, setState] = useState("pending"),
        [branch, setBranch] = useState(""),
        [search, setSearch] = useState(""),
        [age, setAge] = useState("0"),
        [page, setPage] = useState(1),
        [version, setVersion] = useState(0),
        [result, setResult] = useState<Row | null>(null),
        [error, setError] = useState(""),
        [editing, setEditing] = useState<Row | null>(null),
        [detail, setDetail] = useState<Row | null>(null);
    useEffect(() => {
        let live = true;
        setResult(null);
        setError("");
        const timer = setTimeout(() => {
            const query = new URLSearchParams({
                state,
                older_than: age,
                page: String(page),
                per_page: "10",
                ...(branch ? { branch_id: branch } : {}),
                ...(search ? { search } : {}),
            });
            api("admin/posts?" + query)
                .then((r) => {
                    if (live) setResult(r.data);
                })
                .catch((e) => {
                    if (live) setError(e.message);
                });
        }, 200);
        return () => {
            live = false;
            clearTimeout(timer);
        };
    }, [state, branch, search, page, version, age]);
    return (
        <div className="space-y-5">
            <Card>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="eyebrow">CỘNG ĐỒNG / KIỂM DUYỆT</p>
                        <h2 className="mt-2 text-2xl font-bold">
                            Nội dung an toàn, cộng đồng văn minh
                        </h2>
                        <p className="mt-2 max-w-2xl text-sm text-slate-500">
                            Đọc nội dung, kiểm tra đề xuất của AI và gửi quyết
                            định kèm lý do tới người đăng.
                        </p>
                    </div>
                </div>
                <div
                    className="mt-5 flex flex-wrap gap-2"
                    role="group"
                    aria-label="Trạng thái duyệt bài"
                >
                    {states.map(([id, label]) => (
                        <button
                            key={id}
                            className={state === id ? "btn" : "btn-outline"}
                            aria-pressed={state === id}
                            onClick={() => {
                                setState(id);
                                setPage(1);
                            }}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <div className="mt-5 grid gap-3 md:grid-cols-2">
                    <input
                        className="input w-full"
                        aria-label="Tìm bài viết quản trị"
                        placeholder="Tìm theo tiêu đề bài viết…"
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setPage(1);
                        }}
                    />
                    <select
                        className="input w-full"
                        aria-label="Lọc chi nhánh bài viết"
                        value={branch}
                        onChange={(e) => {
                            setBranch(e.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="">Tất cả chi nhánh</option>
                        {data!.branches.map((b: Row) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="input w-full"
                        aria-label="Lọc bài đã quá thời gian"
                        value={age}
                        onChange={(e) => {
                            setAge(e.target.value);
                            setPage(1);
                        }}
                    >
                        <option value="0">Tất cả thời gian</option>
                        <option value="30">Lịch giao lưu đã qua 30 ngày</option>
                        <option value="90">Lịch giao lưu đã qua 90 ngày</option>
                        <option value="180">
                            Lịch giao lưu đã qua 180 ngày
                        </option>
                    </select>
                </div>
            </Card>
            {error && (
                <div role="alert" className="management-error">
                    {error}
                    <button
                        className="btn-outline"
                        onClick={() => setVersion((v) => v + 1)}
                    >
                        Thử lại
                    </button>
                </div>
            )}
            {!result && !error && <Empty text="Đang tải danh sách bài viết…" />}
            {result?.data.map((post: Row) => (
                <Card key={post.id}>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0 flex-1">
                            <span className="report-pill">
                                {
                                    states.find(
                                        ([id]) => id === post.moderation_status,
                                    )?.[1]
                                }
                                {post.status === "hidden" ? " · Đã gỡ" : ""}
                            </span>
                            <h3 className="mt-3 text-xl font-bold">
                                {post.title}
                            </h3>
                            <p className="mt-2 text-sm text-slate-500">
                                {post.user?.name} · {post.branch?.name} ·{" "}
                                {stamp(post.created_at)}
                            </p>
                        </div>
                        <button
                            className="btn-outline"
                            onClick={() =>
                                setDetail(detail?.id === post.id ? null : post)
                            }
                        >
                            {detail?.id === post.id
                                ? "Thu gọn"
                                : "Đọc chi tiết"}
                        </button>
                    </div>
                    <p
                        className={
                            "mt-4 whitespace-pre-wrap leading-7 " +
                            (detail?.id === post.id ? "" : "line-clamp-3")
                        }
                    >
                        {post.content}
                    </p>
                    {detail?.id === post.id && (
                        <div className="mt-4 flex flex-wrap gap-3">
                            {post.images?.map((id: number) => (
                                <a
                                    key={id}
                                    href={"/api/v1/media/" + id}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <img
                                        className="h-32 w-40 rounded-2xl object-cover"
                                        src={"/api/v1/media/" + id}
                                        alt="Ảnh của bài viết"
                                    />
                                </a>
                            ))}
                        </div>
                    )}
                    {(post.moderation_result?.human_reason ||
                        post.moderation_result?.reason) && (
                        <p className="mt-4 rounded-xl bg-slate-50 p-3 text-sm">
                            <b>
                                {post.moderation_result?.human_reason
                                    ? "Lý do quyết định: "
                                    : "Đề xuất AI: "}
                            </b>
                            {post.moderation_result?.human_reason ||
                                post.moderation_result?.reason}
                        </p>
                    )}
                    {post.status !== "hidden" && (
                        <div className="mt-4 flex flex-wrap gap-3">
                            <button
                                className="btn"
                                onClick={() =>
                                    setEditing({
                                        id: post.id,
                                        decision: "approved",
                                        reason: "Nội dung phù hợp với quy định cộng đồng.",
                                    })
                                }
                            >
                                Duyệt bài
                            </button>
                            <button
                                className="btn-outline"
                                onClick={() =>
                                    setEditing({
                                        id: post.id,
                                        decision: "rejected",
                                        reason: "",
                                    })
                                }
                            >
                                Từ chối kèm lý do
                            </button>
                            <button
                                className="text-sm font-bold text-slate-600"
                                onClick={() =>
                                    setEditing({
                                        id: post.id,
                                        decision: "hidden",
                                        reason: "Bài đã hết thời hạn giao lưu hoặc không còn phù hợp.",
                                    })
                                }
                            >
                                Gỡ khỏi cộng đồng
                            </button>
                        </div>
                    )}
                </Card>
            ))}
            {result && !result.data.length && (
                <Empty text="Không có bài viết phù hợp bộ lọc." />
            )}
            {result && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">
                        {result.total} bài viết · Trang {result.current_page}/
                        {result.last_page}
                    </p>
                    <div className="flex gap-2">
                        <button
                            className="btn-outline"
                            disabled={page <= 1}
                            onClick={() => setPage((p) => p - 1)}
                        >
                            Trang trước
                        </button>
                        <button
                            className="btn-outline"
                            disabled={page >= result.last_page}
                            onClick={() => setPage((p) => p + 1)}
                        >
                            Trang sau
                        </button>
                    </div>
                </div>
            )}
            <HistoryBrowser
                kind="reports"
                admin
                version={version}
                onSelect={(report) => {
                    setState("all");
                    setSearch(report.title);
                    setPage(1);
                    window.scrollTo({ top: 0, behavior: "smooth" });
                }}
            />
            {editing && (
                <Editor
                    title={
                        editing.decision === "hidden"
                            ? "Gỡ bài khỏi cộng đồng"
                            : "Quyết định kiểm duyệt"
                    }
                    initial={editing}
                    fields={[
                        {
                            name: "reason",
                            label: "Lý do gửi người đăng",
                            required: true,
                            type: "textarea",
                            maxLength: 500,
                        },
                    ]}
                    close={() => setEditing(null)}
                    save={async (values) => {
                        if (editing.decision === "hidden")
                            await api("admin/posts/" + editing.id, "PATCH", {
                                status: "hidden",
                                reason: values.reason,
                            });
                        else
                            await api(
                                "admin/posts/" + editing.id + "/moderation",
                                "PATCH",
                                {
                                    decision: editing.decision,
                                    reason: values.reason,
                                },
                            );
                        setEditing(null);
                        setVersion((v) => v + 1);
                        await after();
                        toast(
                            "Đã cập nhật bài viết và gửi thông báo tới người đăng.",
                        );
                    }}
                />
            )}
        </div>
    );
}
