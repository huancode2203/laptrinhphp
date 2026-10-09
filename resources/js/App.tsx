import CourtMatrix from "./CourtMatrix";
import { ContentSupport, Coaching } from "./FeaturePages";
import { lazy, Suspense, useEffect, useState } from "react";
import CustomerHome, { CourtIllustration } from "./CustomerHome";
import SiteHeader from "./SiteHeader";
import BookingFlow from "./BookingFlow";
import AuthScreen from "./AuthScreen";
import Account from "./Account";
import CommunityApp from "./CommunityApp";
import EventsPage from "./EventsPage";
const Management = lazy(() => import("./Management"));
import { api, useHub } from "./hub";
import { BRANCHES, day, money, type Row } from "./data";
import { Card, Empty, Modal } from "./ui";
function BranchDetail({ id, book }: { id: number; book: (d: Row) => void }) {
    const { data, toast } = useHub();
    const [date, setDate] = useState(day(1)),
        [schedule, setSchedule] = useState<Row | null>(null),
        [reviews, setReviews] = useState<Row[]>([]);
    const b = BRANCHES.find((b) => b.id === id),
        raw = data!.branches.find((b: Row) => b.id === id);
    useEffect(() => {
        api("branches/" + id + "/schedule?date=" + date)
            .then((r) => setSchedule(r.data))
            .catch((e) => toast(e.message));
        api("branches/" + id + "/reviews")
            .then((r) => setReviews(r.data.data))
            .catch((e) => toast(e.message));
    }, [id, date]);
    if (!b) return <Empty />;
    return (
        <div className="product-page mx-auto max-w-6xl p-5 md:p-10">
            <div className="branch-hero relative mb-6 overflow-hidden p-8 text-white">
                <div className="branch-detail-art">
                    <CourtIllustration />
                </div>
                <div className="relative">
                    <span className="badge badge-green">{b.courts} sân</span>
                    <h1 className="font-display my-4 text-4xl font-bold">
                        {b.rawName}
                    </h1>
                    <p>{b.address}</p>
                    <div className="mt-5 flex flex-wrap gap-3">
                        <button
                            className="btn"
                            onClick={() => book({ branch_id: id })}
                        >
                            Đặt sân ngay
                        </button>
                        {b.phone && (
                            <a
                                href={"tel:" + b.phone}
                                className="btn-outline border-white/20 text-white"
                            >
                                Gọi {b.phone}
                            </a>
                        )}
                        <a
                            className="btn-outline border-white/20 text-white"
                            target="_blank"
                            rel="noreferrer"
                            href={
                                "https://www.google.com/maps/search/?api=1&query=" +
                                encodeURIComponent(b.address)
                            }
                        >
                            Chỉ đường ↗
                        </a>
                    </div>
                </div>
            </div>
            <div className="grid gap-5 md:grid-cols-3">
                <Card>
                    <h2 className="font-bold">Giờ mở cửa</h2>
                    <p className="mt-3">
                        {String(Math.floor(raw.opens_minute / 60)).padStart(
                            2,
                            "0",
                        )}
                        :{String(raw.opens_minute % 60).padStart(2, "0")} –{" "}
                        {String(Math.floor(raw.closes_minute / 60)).padStart(
                            2,
                            "0",
                        )}
                        :{String(raw.closes_minute % 60).padStart(2, "0")} mỗi
                        ngày
                    </p>
                </Card>
                <Card>
                    <h2 className="font-bold">Giá sân</h2>
                    <p className="mt-3">Từ {money(b.price)}/giờ</p>
                    <small>Giá và ưu đãi được tính khi chọn lịch.</small>
                </Card>
                <Card>
                    <h2 className="font-bold">Tiện ích</h2>
                    <p className="mt-3">{b.amenities?.join(" · ")}</p>
                </Card>
            </div>
            <div className="my-6 flex flex-wrap items-center justify-between gap-3">
                <h2 className="font-display text-2xl font-bold">
                    Lịch sân trực tiếp
                </h2>
                <input
                    aria-label="Ngày xem lịch sân"
                    className="input"
                    type="date"
                    min={day()}
                    value={date}
                    onChange={(e) => setDate(e.target.value)}
                />
            </div>
            <CourtMatrix schedule={schedule} onPick={book} />
            <h2 className="my-6 text-2xl font-bold">Dịch vụ tại sân</h2>
            <div className="grid gap-3 sm:grid-cols-3">
                {raw.services.map((s: Row) => (
                    <Card key={s.id}>
                        <b>{s.name}</b>
                        <p className="mt-2 text-[#16a34a]">
                            {money(s.price)}/{s.unit}
                        </p>
                    </Card>
                ))}
            </div>
            <h2 className="my-6 text-2xl font-bold">
                Đánh giá từ người đã chơi
            </h2>
            {reviews.length ? (
                reviews.map((r) => (
                    <Card key={r.id} className="mb-3">
                        <b>
                            {r.user?.name} · {r.rating}★
                        </b>
                        <p className="mt-2 whitespace-pre-wrap">{r.content}</p>
                        {r.images?.map((id: number) => (
                            <img
                                key={id}
                                className="mt-3 h-32 rounded-xl"
                                src={"/api/v1/media/" + id}
                                alt="Ảnh đánh giá"
                            />
                        ))}
                        {r.reply && (
                            <p className="mt-3 rounded-xl bg-[#eaf7de] p-3 text-sm">
                                <b>Chi nhánh phản hồi: </b>
                                {r.reply}
                            </p>
                        )}
                    </Card>
                ))
            ) : (
                <Empty text="Chưa có đánh giá. Bạn có thể đánh giá sau khi hoàn tất buổi chơi." />
            )}
        </div>
    );
}
export default function App() {
    const { data, run, refresh } = useHub();
    const user = data!.user;
    const routePage = () => {
        const name = location.hash.slice(1).split("/")[0];
        return [
            "home",
            "auth",
            "booking",
            "branch",
            "community",
            "events",
            "manage",
            "account",
            "content",
            "coaching",
        ].includes(name)
            ? name
            : "home";
    };
    const [page, setPage] = useState(routePage),
        [initial, setInitial] = useState<Row>({}),
        [branch, setBranch] = useState(
            Number(location.hash.split("/")[1]) || BRANCHES[0]?.id,
        ),
        [afterAuth, setAfterAuth] = useState("account");
    useEffect(() => {
        const cb = () => {
            setPage(routePage());
            if (location.hash.startsWith("#branch/"))
                setBranch(Number(location.hash.split("/")[1]));
        };
        window.addEventListener("hashchange", cb);
        return () => window.removeEventListener("hashchange", cb);
    }, []);
    function go(p: string, branchId?: number) {
        setPage(p.split("/")[0]);
        if (branchId) setBranch(branchId);
        location.hash = p + (branchId ? "/" + branchId : "");
        window.scrollTo(0, 0);
    }
    function auth(next = "account") {
        setAfterAuth(next);
        go("auth");
    }
    function book(d: Row = {}) {
        setInitial(d);
        if (!user) {
            auth("booking");
            return;
        }
        go("booking");
    }
    useEffect(() => {
        if (user && page === "auth") go(afterAuth);
    }, [user?.id]);
    useEffect(() => {
        const expired = () => {
            setAfterAuth(page);
            go("auth");
            refresh();
        };
        window.addEventListener("hub:auth-expired", expired);
        return () => window.removeEventListener("hub:auth-expired", expired);
    }, [page]);
    const manager =
        user && ["manager", "admin", "staff", "cashier"].includes(user.role);
    return (
        <div className={"app-shell page-" + page}>
            <SiteHeader
                page={page}
                go={(p) => (p === "auth" ? auth() : go(p))}
                book={() => book()}
                userName={user?.name}
                manage={!!manager}
                logout={() =>
                    run(async () => {
                        await api("logout", "POST");
                        await refresh();
                        go("home");
                    })
                }
            />
            {page === "home" ? (
                <>
                    <CustomerHome
                        onBook={(i, date, time, duration) =>
                            book({
                                branch_id: BRANCHES[i ?? 0]?.id,
                                date,
                                time,
                                duration,
                            })
                        }
                        onBranch={(i) => {
                            go("branch", BRANCHES[i]?.id);
                        }}
                        onCommunity={() => go("community")}
                        onEvents={() => go("events")}
                        onAuth={() => (user ? go("account") : auth())}
                        onMembership={() =>
                            user ? go("account/extras") : auth("account/extras")
                        }
                        onSupport={() => go("content")}
                        onAiBook={book}
                        onManage={manager ? () => go("manage") : undefined}
                        userName={user?.name}
                    />
                </>
            ) : page === "auth" ? (
                <AuthScreen done={() => go(afterAuth)} />
            ) : page === "booking" ? (
                user ? (
                    <BookingFlow
                        initial={initial}
                        close={() => go("home")}
                        done={() => go("account")}
                    />
                ) : (
                    <AuthScreen done={() => go("booking")} />
                )
            ) : page === "branch" ? (
                <BranchDetail id={branch} book={book} />
            ) : page === "community" ? (
                <CommunityApp auth={() => auth("community")} book={book} />
            ) : page === "events" ? (
                <EventsPage auth={() => auth("events")} />
            ) : page === "content" ? (
                <ContentSupport />
            ) : page === "coaching" ? (
                <Coaching />
            ) : page === "manage" ? (
                manager ? (
                    <Suspense fallback={<Empty text="Đang mở quản lý…" />}>
                        <Management book={book} />
                    </Suspense>
                ) : (
                    <AuthScreen done={() => go("account")} />
                )
            ) : user ? (
                <Account
                    book={() => book()}
                    initialTab={location.hash.split("/")[1] ?? "bookings"}
                />
            ) : (
                <AuthScreen done={() => go("account")} />
            )}
        </div>
    );
}
