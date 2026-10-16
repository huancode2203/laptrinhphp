import Icon from "./Icon";
import { useEffect, useState } from "react";
import { api, key, useHub } from "./hub";
import { BRANCHES, day, money, stamp, type Row } from "./data";
import { Card, Confirm, Editor, Empty, Modal, Table, cell } from "./ui";
import MediaPicker from "./MediaPicker";
const td = (value: React.ReactNode) => <td className={cell}>{value}</td>;

export function AccountExtras() {
    const { data, run, refresh, toast } = useHub();
    const [extras, setExtras] = useState<Row | null>(null),
        [reward, setReward] = useState<Row | null>(null);
    const load = async () => setExtras((await api("features/account")).data);
    useEffect(() => {
        load().catch((e) => toast(e.message));
    }, []);
    async function changed(fn: () => Promise<any>) {
        await fn();
        await refresh();
        await load();
    }
    if (!extras) return <Empty text="Đang tải quyền lợi…" />;
    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <p className="text-sm text-slate-500">Điểm có thể đổi</p>
                    <strong className="font-display text-4xl">
                        {data!.user.loyalty_points ?? 0}
                    </strong>
                    <p className="mt-2 text-sm">
                        1 điểm cho mỗi 10.000đ đủ điều kiện
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-slate-500">Tổng điểm đã tích</p>
                    <strong className="font-display text-4xl">
                        {data!.user.earned_points ?? 0}
                    </strong>
                    <p className="mt-2 text-sm">Dùng xét hạng hội viên</p>
                </Card>
                <Card>
                    <h3 className="font-bold">Lên hạng, thêm lợi thế</h3>
                    <p className="mt-3 text-sm text-slate-500">
                        Hạng hội viên mở ưu đãi giá sân và quyền đặt sớm. Quyền
                        lợi được áp dụng khi bạn chọn lịch.
                    </p>
                    <a
                        className="mt-3 inline-block font-bold text-[#168449]"
                        href="#account/membership"
                    >
                        Xem hội viên ↗
                    </a>
                </Card>
            </div>
            <h2 className="text-xl font-bold">Đổi điểm</h2>
            <div className="grid gap-4 md:grid-cols-2">
                {extras.rewards.map((v: Row) => (
                    <Card key={v.id}>
                        <h3 className="font-bold">{v.name}</h3>
                        <p className="my-3 text-sm">
                            {v.points} điểm · {money(v.wallet_amount)} vào ví
                        </p>
                        <button
                            className="btn"
                            disabled={data!.user.loyalty_points < v.points}
                            onClick={() => setReward(v)}
                        >
                            Đổi quà
                        </button>
                    </Card>
                ))}
            </div>
            {reward && (
                <Confirm
                    title="Đổi điểm thưởng"
                    close={() => setReward(null)}
                    action={async (requestKey) => {
                        await changed(() =>
                            api("loyalty/redeem", "POST", {
                                request_key: requestKey,
                                reward_id: reward.id,
                            }),
                        );
                        setReward(null);
                        toast("Đã đổi điểm. Tiền đã được cộng vào ví.");
                    }}
                >
                    Dùng {reward.points} điểm để nhận{" "}
                    {money(reward.wallet_amount)} vào ví?
                </Confirm>
            )}
            <h2 className="text-xl font-bold">Sân yêu thích</h2>
            <div className="grid gap-4 md:grid-cols-3">
                {extras.favorites.length ? (
                    extras.favorites.map((v: Row) => (
                        <Card key={v.id}>
                            <b>
                                {v.name} · {v.branch.name}
                            </b>
                            <p className="my-3 text-sm text-slate-500">
                                {v.branch.address}
                            </p>
                            <div className="flex gap-2">
                                <a
                                    className="btn-outline"
                                    href={"#branch/" + v.branch_id}
                                >
                                    Xem lịch
                                </a>
                                <button
                                    className="btn-outline"
                                    onClick={() =>
                                        run(() =>
                                            changed(() =>
                                                api(
                                                    "courts/" +
                                                        v.id +
                                                        "/favorite",
                                                    "PUT",
                                                    { active: false },
                                                ),
                                            ),
                                        )
                                    }
                                >
                                    Bỏ lưu
                                </button>
                            </div>
                        </Card>
                    ))
                ) : (
                    <Empty text="Lưu sân yêu thích từ kết quả tìm sân để xem lại tại đây." />
                )}
            </div>
            <h2 className="text-xl font-bold">Bài viết đã lưu</h2>
            <div className="grid gap-3 md:grid-cols-2">
                {extras.saved_posts.map((v: Row) => (
                    <Card key={v.id}>
                        <h3 className="font-bold">{v.title}</h3>
                        <p className="my-2 text-sm">
                            {v.user?.name} · {v.branch?.name}
                        </p>
                        <a
                            className="text-sm font-bold text-[#168449]"
                            href={"#community/post/" + v.id}
                        >
                            Xem bài viết ↗
                        </a>
                    </Card>
                ))}
                {!extras.saved_posts.length && (
                    <Empty text="Chưa có bài viết đã lưu." />
                )}
            </div>
            <h2 className="text-xl font-bold">Lịch sử tích và đổi điểm</h2>
            <Table headers={["Thời gian", "Nội dung", "Điểm", "Số dư"]}>
                {extras.points.map((v: Row) => (
                    <tr key={v.id}>
                        {td(stamp(v.created_at))}
                        {td(v.description)}
                        {td(v.points)}
                        {td(v.balance)}
                    </tr>
                ))}
            </Table>
            <h2 className="text-xl font-bold">Tài khoản đã chặn</h2>
            <div className="flex flex-wrap gap-3">
                {extras.blocked.map((v: Row) => (
                    <button
                        className="btn-outline"
                        key={v.id}
                        onClick={() =>
                            run(() =>
                                changed(() =>
                                    api("users/" + v.id + "/block", "PUT", {
                                        active: false,
                                    }),
                                ),
                            )
                        }
                    >
                        {v.name} · Bỏ chặn
                    </button>
                ))}
                {!extras.blocked.length && (
                    <p className="text-sm text-slate-500">
                        Bạn chưa chặn tài khoản nào.
                    </p>
                )}
            </div>
        </div>
    );
}
export function ContentSupport() {
    const { data, run, toast } = useHub();
    const [pages, setPages] = useState<Row[]>([]),
        [tickets, setTickets] = useState<Row[]>([]),
        [tab, setTab] = useState(location.hash.split("/")[1] ?? "faq"),
        [selected, setSelected] = useState<Row | null>(null),
        [form, setForm] = useState(false);
    useEffect(() => {
        api("pages")
            .then((r) => setPages(r.data))
            .catch((e) => toast(e.message));
        if (data?.user)
            api("features/account")
                .then((r) => setTickets(r.data.tickets))
                .catch((e) => toast(e.message));
    }, [data?.user?.id]);
    const list = pages.filter((p) =>
        tab === "franchise"
            ? p.slug.includes("hop-tac") || p.slug.includes("franchise")
            : p.type === (tab === "about" ? "page" : tab),
    );
    return (
        <main className="product-page mx-auto max-w-5xl p-5 md:p-10">
            <div className="page-heading">
                <div>
                    <p className="eyebrow">ĐỒNG HÀNH CÙNG BẠN</p>
                    <h1 className="font-display my-3 text-4xl font-black">
                        Thông tin & hỗ trợ
                    </h1>
                    <p>Mọi điều bạn cần biết để buổi chơi thêm trọn vẹn.</p>
                </div>
                <Icon name="help" size={43} />
            </div>
            <div className="page-tabs">
                {[
                    ["faq", "Câu hỏi thường gặp"],
                    ["about", "Về chúng tôi"],
                    ["blog", "Góc cầu lông"],
                    ["job", "Tuyển dụng"],
                    ["franchise", "Hợp tác"],
                ].map(([id, label]) => (
                    <button
                        className={tab === id ? "btn" : "btn-outline"}
                        key={id}
                        onClick={() => {
                            setTab(id);
                            setSelected(null);
                        }}
                    >
                        {label}
                    </button>
                ))}
            </div>
            {selected ? (
                <Card>
                    <button
                        className="text-sm font-bold text-[#168449]"
                        onClick={() => setSelected(null)}
                    >
                        ← Quay lại
                    </button>
                    <h2 className="my-5 text-2xl font-bold">
                        {selected.title}
                    </h2>
                    <p className="whitespace-pre-wrap leading-8">
                        {selected.content}
                    </p>
                    {tab === "job" && (
                        <button
                            className="btn mt-5"
                            onClick={() => setForm(true)}
                        >
                            Gửi thông tin ứng tuyển
                        </button>
                    )}
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {list.length ? (
                        list.map((p) => (
                            <button
                                className="rounded-2xl border border-slate-200 bg-white p-5 text-left"
                                key={p.id}
                                onClick={() => setSelected(p)}
                            >
                                <h2 className="text-lg font-bold">
                                    {p.title} ↗
                                </h2>
                                <p className="mt-3 line-clamp-3 text-sm leading-6 text-slate-500">
                                    {p.content}
                                </p>
                            </button>
                        ))
                    ) : (
                        <Empty
                            text={
                                tab === "job"
                                    ? "Hiện chưa có tin tuyển dụng được công bố."
                                    : tab === "franchise"
                                      ? "Gửi yêu cầu hỗ trợ nếu bạn quan tâm hợp tác."
                                      : "Chưa có bài viết trong mục này."
                            }
                        />
                    )}
                </div>
            )}
            <section className="mt-10">
                <Card>
                    <div className="flex flex-wrap justify-between gap-4">
                        <div>
                            <h2 className="text-xl font-bold">
                                Bạn cần trợ giúp thêm?
                            </h2>
                            <p className="mt-2 text-sm text-slate-500">
                                Gửi câu hỏi về lịch đặt, thanh toán, tài khoản
                                hoặc hợp tác.
                            </p>
                        </div>
                        <button
                            className="btn"
                            onClick={() =>
                                data?.user
                                    ? setForm(true)
                                    : (location.hash = "auth")
                            }
                        >
                            Gửi yêu cầu hỗ trợ
                        </button>
                    </div>
                </Card>
            </section>
            {!!tickets.length && (
                <section className="mt-8">
                    <h2 className="mb-4 text-xl font-bold">Yêu cầu của bạn</h2>
                    <div className="space-y-3">
                        {tickets.map((t) => (
                            <Card key={t.id}>
                                <span className="badge badge-green">
                                    {t.status === "resolved"
                                        ? "Đã phản hồi"
                                        : "Đang xử lý"}
                                </span>
                                <h3 className="mt-3 font-bold">{t.subject}</h3>
                                <p className="my-3 whitespace-pre-wrap text-sm">
                                    {t.content}
                                </p>
                                {t.reply && (
                                    <p className="whitespace-pre-wrap rounded-xl bg-[#eaf7de] p-4 text-sm">
                                        <b>Phản hồi: </b>
                                        {t.reply}
                                    </p>
                                )}
                            </Card>
                        ))}
                    </div>
                </section>
            )}
            {form && (
                <Editor
                    title="Gửi yêu cầu hỗ trợ"
                    initial={{
                        subject:
                            selected && tab === "job"
                                ? "Ứng tuyển: " + selected.title
                                : "",
                    }}
                    fields={[
                        { name: "subject", label: "Tiêu đề", required: true },
                        {
                            name: "branch_id",
                            label: "Chi nhánh liên quan",
                            options: BRANCHES.map((b) => ({
                                value: b.id,
                                label: b.rawName,
                            })),
                        },
                        {
                            name: "content",
                            label:
                                tab === "job"
                                    ? "Thông tin và kinh nghiệm của bạn"
                                    : "Nội dung cần hỗ trợ",
                            type: "textarea",
                            required: true,
                        },
                    ]}
                    close={() => setForm(false)}
                    save={async (d) => {
                        await api("support", "POST", {
                            ...d,
                            branch_id: d.branch_id ? Number(d.branch_id) : null,
                        });
                        setTickets(
                            (await api("features/account")).data.tickets,
                        );
                        toast("Đã gửi yêu cầu hỗ trợ.");
                    }}
                />
            )}
        </main>
    );
}
export function Coaching() {
    const { data, toast, run, refresh } = useHub();
    const [coaches, setCoaches] = useState<Row[]>([]),
        [coach, setCoach] = useState<Row | null>(null),
        [court, setCourt] = useState(""),
        [date, setDate] = useState(day(1)),
        [time, setTime] = useState("08:00"),
        [duration, setDuration] = useState(1),
        [quote, setQuote] = useState<Row | null>(null),
        [error, setError] = useState(""),
        [requestKey, setKey] = useState(key),
        [coachBranch, setCoachBranch] = useState(""),
        [coachPage, setCoachPage] = useState(1),
        [availableCourts, setAvailableCourts] = useState<Row[]>([]),
        [coachesLoaded, setCoachesLoaded] = useState(false);
    const filteredCoaches = coaches.filter(
        (c) => !coachBranch || c.branch_id === Number(coachBranch),
    );
    const coachPages = Math.max(1, Math.ceil(filteredCoaches.length / 9));
    const start = date + "T" + time + ":00+07:00",
        end =
            date +
            "T" +
            String(+time.slice(0, 2) + duration).padStart(2, "0") +
            time.slice(2) +
            ":00+07:00";
    useEffect(() => {
        api("coaches")
            .then((r) => setCoaches(r.data))
            .catch((e) => toast(e.message))
            .finally(() => setCoachesLoaded(true));
    }, []);
    useEffect(() => {
        setQuote(null);
        let live = true;
        if (court)
            api("courts/" + court + "/quote", "POST", {
                starts_at: start,
                ends_at: end,
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
    }, [court, start, end]);
    useEffect(() => {
        let live = true;
        setAvailableCourts([]);
        if (coach)
            api(
                "branches/" +
                    coach.branch_id +
                    "/availability?" +
                    new URLSearchParams({ starts_at: start, ends_at: end }),
            )
                .then((r) => {
                    if (live) {
                        setAvailableCourts(r.data);
                        setError("");
                    }
                })
                .catch((e) => {
                    if (live) setError(e.message);
                });
        return () => {
            live = false;
        };
    }, [coach?.id, start, end]);
    const branch = data!.branches.find((b: Row) => b.id === coach?.branch_id);
    useEffect(() => {
        if (branch) {
            const max = Math.min(
                3,
                Math.floor(
                    (branch.closes_minute -
                        (+time.slice(0, 2) * 60 + +time.slice(3))) /
                        60,
                ),
            );
            if (max >= 1 && duration > max) setDuration(max);
        }
    }, [time, branch?.id, duration]);
    return (
        <main className="product-page mx-auto max-w-6xl p-5 md:p-10">
            <div className="page-heading">
                <div>
                    <p className="eyebrow">TIẾN BỘ QUA TỪNG BUỔI TẬP</p>
                    <h1 className="font-display my-3 text-4xl font-black">
                        Học cầu lông
                    </h1>
                    <p className="mb-8 text-slate-500">
                        Chọn huấn luyện viên, sân và giờ tập. Chi phí sân và
                        buổi học được hiển thị trước khi đặt.
                    </p>
                </div>
                <Icon name="racket" size={43} />
            </div>
            <label className="mb-6 block max-w-md text-sm">
                Chi nhánh huấn luyện
                <select
                    className="input mt-2 w-full"
                    value={coachBranch}
                    onChange={(e) => {
                        setCoachBranch(e.target.value);
                        setCoachPage(1);
                    }}
                >
                    <option value="">Tất cả chi nhánh</option>
                    {data!.branches.map((b: Row) => (
                        <option key={b.id} value={b.id}>
                            {b.name}
                        </option>
                    ))}
                </select>
            </label>
            <div className="grid gap-5 md:grid-cols-3">
                {filteredCoaches
                    .slice((coachPage - 1) * 9, coachPage * 9)
                    .map((c) => (
                        <Card key={c.id}>
                            <div className="coach-avatar mb-4">
                                {c.name.split(" ").at(-1)?.slice(0, 1)}
                            </div>
                            <h2 className="text-xl font-bold">{c.name}</h2>
                            <p className="my-2 text-sm text-[#168449]">
                                {
                                    data!.branches.find(
                                        (b: Row) => b.id === c.branch_id,
                                    )?.name
                                }
                            </p>
                            <p className="my-3 whitespace-pre-wrap text-sm leading-6 text-slate-500">
                                {c.bio}
                            </p>
                            <p className="mb-4 font-bold">
                                {money(c.hourly_rate)} / giờ học
                            </p>
                            <button
                                className="btn"
                                onClick={() => {
                                    if (!data?.user) {
                                        location.hash = "auth";
                                        return;
                                    }
                                    setCoach(c);
                                    setCourt("");
                                    setKey(key());
                                }}
                            >
                                Chọn buổi học
                            </button>
                        </Card>
                    ))}
                {!coaches.length && (
                    <Empty
                        text={
                            coachesLoaded
                                ? "Chưa có lịch huấn luyện viên được công bố. Gửi yêu cầu hỗ trợ để được tư vấn."
                                : "Đang tải huấn luyện viên…"
                        }
                    />
                )}
            </div>
            {coachPages > 1 && (
                <div className="mt-5 flex items-center justify-between gap-3">
                    <span className="text-sm">
                        {filteredCoaches.length} huấn luyện viên · Trang{" "}
                        {coachPage}/{coachPages}
                    </span>
                    <div className="flex gap-2">
                        <button
                            className="btn-outline"
                            disabled={coachPage <= 1}
                            onClick={() => setCoachPage((p) => p - 1)}
                        >
                            Trang trước
                        </button>
                        <button
                            className="btn-outline"
                            disabled={coachPage >= coachPages}
                            onClick={() => setCoachPage((p) => p + 1)}
                        >
                            Trang sau
                        </button>
                    </div>
                </div>
            )}
            {coach && (
                <Modal
                    title={"Đặt buổi học cùng " + coach.name}
                    close={() => setCoach(null)}
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            run(async () => {
                                const r = await api("coaching", "POST", {
                                    request_key: requestKey,
                                    coach_id: coach.id,
                                    court_id: Number(court),
                                    starts_at: start,
                                    ends_at: end,
                                    pay_with_wallet: false,
                                });
                                await refresh();
                                toast(
                                    "Đã đặt buổi học. Thanh toán đặt cọc trong thời hạn của lịch.",
                                );
                                location.hash = "account";
                            });
                        }}
                        className="hub-form form-grid"
                    >
                        <label className="text-sm">
                            Ngày
                            <input
                                type="date"
                                className="input mt-1 w-full"
                                min={day()}
                                required
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                            />
                        </label>
                        <label className="text-sm">
                            Giờ bắt đầu
                            <input
                                type="time"
                                step={1800}
                                className="input mt-1 w-full"
                                value={time}
                                onChange={(e) => setTime(e.target.value)}
                            />
                        </label>
                        <label className="text-sm">
                            Thời lượng
                            <select
                                className="input mt-1 w-full"
                                value={duration}
                                onChange={(e) => setDuration(+e.target.value)}
                            >
                                {[1, 2, 3]
                                    .filter(
                                        (n) =>
                                            n * 60 <=
                                            (branch?.closes_minute ?? 1380) -
                                                (+time.slice(0, 2) * 60 +
                                                    +time.slice(3)),
                                    )
                                    .map((n) => (
                                        <option key={n} value={n}>
                                            {n} giờ
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <label className="text-sm">
                            Sân
                            <select
                                className="input mt-1 w-full"
                                required
                                value={court}
                                onChange={(e) => setCourt(e.target.value)}
                            >
                                <option value="">Chọn sân</option>
                                {availableCourts.map((c: Row) => (
                                    <option
                                        key={c.id}
                                        value={c.id}
                                        disabled={!c.available}
                                    >
                                        {c.name} ·{" "}
                                        {c.available
                                            ? "Còn trống"
                                            : "Đã có lịch"}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="form-field-wide">
                            {error && (
                                <p role="alert" className="mb-3 text-red-700">
                                    {error}
                                </p>
                            )}
                            {quote && (
                                <p className="mb-4 text-lg font-bold">
                                    Sân {money(quote.total_amount)} + học{" "}
                                    {money(coach.hourly_rate * duration)} ={" "}
                                    {money(
                                        quote.total_amount +
                                            coach.hourly_rate * duration,
                                    )}
                                </p>
                            )}
                            <button
                                className="btn"
                                disabled={
                                    !quote ||
                                    !availableCourts.some(
                                        (c) =>
                                            c.available &&
                                            c.id === Number(court),
                                    )
                                }
                            >
                                Xác nhận đặt buổi học
                            </button>
                            <button
                                type="button"
                                className="btn-outline ml-3"
                                onClick={() => setCoach(null)}
                            >
                                Đóng
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </main>
    );
}
