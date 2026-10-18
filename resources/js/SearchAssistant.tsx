import { useRef, useState } from "react";
import Icon from "./Icon";
import { api, useHub } from "./hub";
import { BRANCHES, day, money, type Row } from "./data";
export default function SearchAssistant({
    book,
    compact = false,
}: {
    book: (d: Row) => void;
    compact?: boolean;
}) {
    const { data, toast } = useHub();
    const [message, setMessage] = useState(""),
        [result, setResult] = useState<Row | null>(null),
        [busy, setBusy] = useState(false),
        [error, setError] = useState(""),
        [manual, setManual] = useState(false);
    const [manualEdited, setManualEdited] = useState(false);
    const filterVersion = useRef(0);
    const [favoriteBusy, setFavoriteBusy] = useState<number[]>([]);
    const [filters, setFilters] = useState<Row>({
        date: day(),
        start_time: "13:00",
        end_time: "15:00",
        district: "",
        max_budget: "",
        amenities: [],
    });
    async function search(useFilters = false, page = 0) {
        const keepSelection = useFilters || manualEdited || page > 0;
        const selectedFilters =
            page > 0 ? (result?.filters ?? filters) : filters;
        const version = filterVersion.current;
        setBusy(true);
        setError("");
        try {
            const r = await api("ai/courts", "POST", {
                message: message || "Tìm sân theo lựa chọn",
                page: page || 1,
                per_page: 6,
                ...(keepSelection
                    ? {
                          filters: {
                              ...selectedFilters,
                              max_budget: selectedFilters.max_budget
                                  ? Number(selectedFilters.max_budget)
                                  : null,
                          },
                      }
                    : {}),
            });
            setResult(r.data);
            if (
                r.data.filters &&
                !keepSelection &&
                version === filterVersion.current
            )
                setFilters({
                    ...filters,
                    ...r.data.filters,
                    max_budget: r.data.filters.max_budget ?? "",
                });
            if (r.data.needs_clarification) setManual(true);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setBusy(false);
        }
    }
    function changeFilter(name: string, value: any) {
        filterVersion.current++;
        setManualEdited(true);
        setFilters((previous) => ({ ...previous, [name]: value }));
    }
    return (
        <section
            className={"assistant " + (compact ? "" : "my-5")}
            aria-label="Tìm sân theo nhu cầu"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="assistant-heading">
                    <span className="assistant-icon">
                        <Icon name="spark" size={25} />
                    </span>
                    <div>
                        <span className="eyebrow">TRỢ LÝ TÌM SÂN CỦA BẠN</span>
                        <h2 className="mt-1 font-bold">
                            Hôm nay, bạn muốn chơi ở đâu?
                        </h2>
                    </div>
                </div>
                <span className="assistant-label">
                    <Icon name="spark" size={13} /> Tìm theo nhu cầu
                </span>
            </div>
            <p className="mt-3 text-xs leading-6 text-slate-500">
                Cho biết ngày, giờ và khu vực. Chúng tôi tìm sân còn trống phù
                hợp với bạn.
            </p>
            <form
                className="assistant-form mt-5 flex flex-col gap-3 sm:flex-row"
                onSubmit={(e) => {
                    e.preventDefault();
                    search();
                }}
            >
                <Icon name="search" size={21} />
                <input
                    className="input min-w-0 flex-1"
                    aria-label="Nhu cầu tìm sân"
                    maxLength={1000}
                    required
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    placeholder="Hôm nay từ 13h đến 15h ở TP.HCM, dưới 300k…"
                />
                <button className="btn" disabled={busy}>
                    {busy ? (
                        "Đang tìm sân…"
                    ) : (
                        <>
                            Tìm sân
                            <Icon name="arrow" size={17} />
                        </>
                    )}
                </button>
            </form>
            <div className="mt-3 flex flex-wrap gap-2">
                {[
                    "Ngày mai 18h đến 20h tại Bình Thạnh",
                    "Hôm nay 13h đến 15h ở TP.HCM dưới 300k",
                ].map((s) => (
                    <button
                        key={s}
                        className="search-chip"
                        onClick={() => setMessage(s)}
                    >
                        {s}
                    </button>
                ))}
                <button
                    className="search-chip"
                    onClick={() => setManual(!manual)}
                >
                    {manual ? "Ẩn bộ lọc" : "Chọn ngày & giờ"}
                </button>
            </div>
            {manual && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        search(true);
                    }}
                    className="hub-form search-filter-form mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-5"
                >
                    {[
                        ["date", "Ngày chơi", "date"],
                        ["start_time", "Từ giờ", "time"],
                        ["end_time", "Đến giờ", "time"],
                    ].map(([name, label, type]) => (
                        <label key={name} className="text-sm">
                            {label}
                            <input
                                className="input mt-1 w-full"
                                required
                                type={type}
                                step={type === "time" ? 1800 : undefined}
                                min={type === "date" ? day() : undefined}
                                value={filters[name] ?? ""}
                                onChange={(e) =>
                                    changeFilter(name, e.target.value)
                                }
                            />
                        </label>
                    ))}
                    <label className="text-sm">
                        Khu vực
                        <select
                            className="input mt-1 w-full"
                            value={filters.district ?? ""}
                            onChange={(e) =>
                                changeFilter("district", e.target.value)
                            }
                        >
                            <option value="">Toàn TP.HCM</option>
                            {[...new Set(BRANCHES.map((b) => b.district))].map(
                                (district) => (
                                    <option key={district} value={district}>
                                        {district}
                                    </option>
                                ),
                            )}
                        </select>
                    </label>
                    <label className="text-sm">
                        Ngân sách buổi chơi
                        <input
                            className="input mt-1 w-full"
                            type="number"
                            min={1}
                            max={100000000}
                            placeholder="Không giới hạn"
                            value={filters.max_budget}
                            onChange={(e) =>
                                changeFilter("max_budget", e.target.value)
                            }
                        />
                    </label>
                    <button className="btn lg:col-span-5" disabled={busy}>
                        Tìm theo lựa chọn
                    </button>
                </form>
            )}
            {error && (
                <p role="alert" className="mt-4 text-red-700">
                    {error}
                </p>
            )}
            {result && (
                <div className="mt-5" aria-live="polite">
                    <p className="font-bold">{result.message}</p>
                    {result.checked_at && (
                        <p className="mt-1 text-xs text-slate-500">
                            Kiểm tra lúc{" "}
                            {new Date(result.checked_at).toLocaleTimeString(
                                "vi-VN",
                            )}
                            . Sân được kiểm tra lại khi bạn xác nhận đặt.
                        </p>
                    )}
                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {result.courts?.map((c: Row) => (
                            <article key={c.court_id} className="result-court">
                                <span className="badge badge-green">
                                    Còn trống · {c.district}
                                </span>
                                <h3 className="mt-3 text-lg font-bold">
                                    {c.court_name} · {c.branch_name}
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    {c.address}
                                </p>
                                <p className="mt-3 text-sm">
                                    {new Date(c.starts_at).toLocaleDateString(
                                        "vi-VN",
                                    )}{" "}
                                    · {c.starts_at.slice(11, 16)}–
                                    {c.ends_at.slice(11, 16)}
                                </p>
                                <div className="my-3 flex items-end justify-between gap-2">
                                    <strong className="text-xl">
                                        {money(c.total_amount)}
                                    </strong>
                                    <span className="text-xs text-slate-500">
                                        Cọc {money(c.deposit_amount)}
                                    </span>
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        className="btn flex-1"
                                        onClick={() =>
                                            book({
                                                branch_id: c.branch_id,
                                                court_id: c.court_id,
                                                date: c.starts_at.slice(0, 10),
                                                time: c.starts_at.slice(11, 16),
                                                duration:
                                                    (new Date(
                                                        c.ends_at,
                                                    ).getTime() -
                                                        new Date(
                                                            c.starts_at,
                                                        ).getTime()) /
                                                    3600000,
                                            })
                                        }
                                    >
                                        Chọn sân này
                                    </button>
                                    <a
                                        className="btn-outline"
                                        href={
                                            c.directions_url ||
                                            "https://www.google.com/maps/dir/?api=1&destination=" +
                                                encodeURIComponent(c.address)
                                        }
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label={
                                            "Chỉ đường đến " + c.branch_name
                                        }
                                    >
                                        Chỉ đường ↗
                                    </a>
                                    {data?.user && (
                                        <button
                                            className={
                                                "btn-outline text-xl " +
                                                (c.is_favorite
                                                    ? "text-rose-600 border-rose-200 bg-rose-50"
                                                    : "")
                                            }
                                            aria-pressed={!!c.is_favorite}
                                            style={
                                                c.is_favorite
                                                    ? { color: "#e11d48" }
                                                    : undefined
                                            }
                                            disabled={favoriteBusy.includes(
                                                c.court_id,
                                            )}
                                            aria-label={
                                                "Yêu thích " + c.court_name
                                            }
                                            onClick={async () => {
                                                setFavoriteBusy((previous) => [
                                                    ...previous,
                                                    c.court_id,
                                                ]);
                                                try {
                                                    await api(
                                                        "courts/" +
                                                            c.court_id +
                                                            "/favorite",
                                                        "PUT",
                                                        {
                                                            active: !c.is_favorite,
                                                        },
                                                    );
                                                    setResult((previous) =>
                                                        previous
                                                            ? {
                                                                  ...previous,
                                                                  courts: previous.courts.map(
                                                                      (
                                                                          court: Row,
                                                                      ) =>
                                                                          court.court_id ===
                                                                          c.court_id
                                                                              ? {
                                                                                    ...court,
                                                                                    is_favorite:
                                                                                        !c.is_favorite,
                                                                                }
                                                                              : court,
                                                                  ),
                                                              }
                                                            : previous,
                                                    );
                                                    toast(
                                                        c.is_favorite
                                                            ? "Đã bỏ sân yêu thích."
                                                            : "Đã lưu vào sân yêu thích.",
                                                    );
                                                } catch (failure) {
                                                    toast(
                                                        (failure as Error)
                                                            .message,
                                                    );
                                                } finally {
                                                    setFavoriteBusy(
                                                        (previous) =>
                                                            previous.filter(
                                                                (id) =>
                                                                    id !==
                                                                    c.court_id,
                                                            ),
                                                    );
                                                }
                                            }}
                                        >
                                            {c.is_favorite ? "♥" : "♡"}
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                    {result.last_page > 1 && (
                        <nav
                            className="mt-5 flex items-center justify-center gap-4"
                            aria-label="Trang kết quả tìm sân"
                        >
                            <button
                                className="btn-outline"
                                disabled={busy || result.current_page <= 1}
                                onClick={() =>
                                    search(true, result.current_page - 1)
                                }
                            >
                                Sân trước
                            </button>
                            <span className="text-sm">
                                Trang {result.current_page} / {result.last_page}
                            </span>
                            <button
                                className="btn-outline"
                                disabled={
                                    busy ||
                                    result.current_page >= result.last_page
                                }
                                onClick={() =>
                                    search(true, result.current_page + 1)
                                }
                            >
                                Sân tiếp
                            </button>
                        </nav>
                    )}
                </div>
            )}
        </section>
    );
}
