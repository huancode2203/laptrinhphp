import { useEffect, useState } from "react";
import CustomerHome, { Logo } from "./CustomerHome";
import BookingFlow from "./BookingFlow";
import AuthScreen from "./AuthScreen";
import Account from "./Account";
import CommunityApp from "./CommunityApp";
import EventsPage from "./EventsPage";
import Management from "./Management";
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
        <div className="mx-auto max-w-6xl p-5 md:p-10">
            <div className="relative mb-6 overflow-hidden rounded-2xl bg-[#0d1f2d] p-8 text-white">
                <img
                    className="absolute inset-0 h-full w-full object-cover opacity-20"
                    src={
                        "https://images.unsplash.com/" +
                        b.img +
                        "?w=1400&fit=crop&auto=format"
                    }
                    alt=""
                />
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
                    <p className="mt-3">03:00 – 23:00 mỗi ngày</p>
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
            <p className="mb-4 text-sm text-[#6d8474]">
                Mỗi ô là 30 phút. Chọn ô xanh để đặt lịch.
            </p>
            <div className="overflow-x-auto rounded-xl border border-[#dce4dc] bg-white p-4">
                <table className="text-xs">
                    <thead>
                        <tr>
                            <th className="sticky left-0 min-w-24 bg-white">
                                Sân
                            </th>
                            {schedule?.courts[0]?.slots.map((s: Row) => (
                                <th className="min-w-16" key={s.starts_at}>
                                    {s.starts_at.slice(11, 16)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {schedule?.courts.map((c: Row) => (
                            <tr key={c.id}>
                                <th className="sticky left-0 bg-white py-3">
                                    {c.name}
                                </th>
                                {c.slots.map((s: Row) => (
                                    <td key={s.starts_at}>
                                        <button
                                            title={
                                                s.state +
                                                " · " +
                                                money(s.court_amount)
                                            }
                                            className={"slot w-full " + s.state}
                                            disabled={!s.available}
                                            onClick={() =>
                                                book({
                                                    branch_id: id,
                                                    date,
                                                    time: s.starts_at.slice(
                                                        11,
                                                        16,
                                                    ),
                                                    duration: 0.5,
                                                })
                                            }
                                        >
                                            {s.available ? "＋" : "●"}
                                        </button>
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
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
        ].includes(name)
            ? name
            : "home";
    };
    const [page, setPage] = useState(routePage),
        [initial, setInitial] = useState<Row>({}),
        [branch, setBranch] = useState(
            Number(location.hash.split("/")[1]) || BRANCHES[0]?.id,
        ),
        [support, setSupport] = useState(false),
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
    const manager =
        user && ["manager", "admin", "staff", "cashier"].includes(user.role);
    return (
        <>
            {page !== "home" && (
                <header className="sticky top-0 z-30 flex flex-wrap items-center justify-between gap-3 border-b border-[#dce4dc] bg-white px-5 py-4">
                    <button aria-label="Trang chủ" onClick={() => go("home")}>
                        <Logo dark />
                    </button>
                    <nav className="flex flex-wrap gap-4 text-sm font-bold">
                        <button onClick={() => book()}>Đặt sân</button>
                        <button onClick={() => go("community")}>
                            Cộng đồng
                        </button>
                        <button onClick={() => go("events")}>Sự kiện</button>
                        {user ? (
                            <>
                                <button onClick={() => go("account")}>
                                    Tài khoản
                                </button>
                                {manager && (
                                    <button onClick={() => go("manage")}>
                                        Quản lý
                                    </button>
                                )}
                                <button
                                    className="text-[#6d8474]"
                                    onClick={() =>
                                        run(async () => {
                                            await api("logout", "POST");
                                            await refresh();
                                            go("home");
                                        })
                                    }
                                >
                                    Đăng xuất
                                </button>
                            </>
                        ) : (
                            <button onClick={() => auth()}>Đăng nhập</button>
                        )}
                    </nav>
                </header>
            )}
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
                            user
                                ? go("account/membership")
                                : auth("account/membership")
                        }
                        onSupport={() => setSupport(true)}
                        userName={user?.name}
                    />
                    <div className="fixed bottom-4 right-4 z-20 flex gap-2">
                        {manager && (
                            <button
                                className="btn shadow-lg"
                                onClick={() => go("manage")}
                            >
                                Quản lý
                            </button>
                        )}
                        <button
                            className="btn-outline bg-white shadow-lg lg:hidden"
                            onClick={() => (user ? go("account") : auth())}
                        >
                            {user ? "Tài khoản" : "Đăng nhập"}
                        </button>
                    </div>
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
                <CommunityApp auth={() => auth("community")} />
            ) : page === "events" ? (
                <EventsPage auth={() => auth("events")} />
            ) : page === "manage" ? (
                manager ? (
                    <Management book={book} />
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
            {support && (
                <Modal
                    title="Hỗ trợ Badminton Hub"
                    close={() => setSupport(false)}
                >
                    <div className="space-y-4 text-sm">
                        <p>
                            Chọn chi nhánh, ngày và giờ chơi để xem sân trống.
                            Giá cuối cùng và ưu đãi hiển thị trước khi xác nhận.
                        </p>
                        <p>
                            Lịch chưa trả tiền: hủy trước giờ bắt đầu. Lịch trả
                            bằng ví: hủy trước tối thiểu 4 giờ để tự hoàn ví.
                            Các phương thức khác: liên hệ quầy để được xử lý
                            hoàn tiền.
                        </p>
                        <p>
                            Lịch cố định: từ 2 đến 12 tuần, cùng sân và khung
                            giờ mỗi tuần, giảm 15% phí sân. Nếu một tuần bị
                            trùng, toàn bộ yêu cầu chưa được tạo.
                        </p>
                        <p>
                            Để liên hệ sân, chọn Chi tiết chi nhánh và dùng số
                            điện thoại hoặc bản đồ đã công bố. Thông tin hợp tác
                            và tuyển dụng do quản trị cung cấp qua chi nhánh.
                        </p>
                        <button
                            className="btn"
                            onClick={() => {
                                setSupport(false);
                                go("community");
                            }}
                        >
                            Hỏi cộng đồng
                        </button>
                    </div>
                </Modal>
            )}
        </>
    );
}
