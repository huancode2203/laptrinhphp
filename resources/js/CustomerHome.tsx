import { useState } from "react";
import {
    BRANCHES,
    PRICING,
    SERVICES,
    COMMUNITY_POSTS,
    MEMBERSHIPS,
    STATS,
    day,
} from "./data";

type Props = {
    onBook: (
        branchIdx?: number,
        date?: string,
        time?: string,
        duration?: number,
    ) => void;
    onBranch: (idx: number) => void;
    onCommunity: () => void;
    onAuth: () => void;
    onEvents: () => void;
    onMembership: () => void;
    onSupport: () => void;
    userName?: string;
};

const FACILITIES = [
    { icon: "🏸", label: "Sàn tiêu chuẩn BWF" },
    { icon: "💡", label: "LED chống chói" },
    { icon: "👕", label: "Phòng thay đồ" },
    { icon: "🚿", label: "Shower & khăn" },
    { icon: "🚗", label: "Bãi giữ xe" },
    { icon: "🔒", label: "Locker cá nhân" },
    { icon: "📶", label: "WiFi tốc độ cao" },
    { icon: "☕", label: "Khu nghỉ + cafe" },
    { icon: "🛒", label: "Shop thể thao" },
    { icon: "❄️", label: "Điều hòa / quạt" },
];

export default function CustomerHome({
    onBook,
    onBranch,
    onCommunity,
    onAuth,
    onEvents,
    onMembership,
    onSupport,
    userName,
}: Props) {
    const [branchSel, setBranchSel] = useState(0);
    const [date, setDate] = useState(day(1)),
        [time, setTime] = useState("18:00"),
        [duration, setDuration] = useState(2);
    const [area, setArea] = useState("Tất cả");

    const areas = ["Tất cả", ...BRANCHES.map((b) => b.name)];
    const filtered =
        area === "Tất cả" ? BRANCHES : BRANCHES.filter((b) => b.name === area);

    return (
        <div className="min-h-screen bg-[#f3f6f4]">
            {/* ── Hero ─────────────────────────────────────────── */}
            <header className="relative overflow-hidden bg-[#0d1f2d] pb-16 pt-0 text-white">
                <div className="court-lines" />
                {/* Unsplash badminton hero overlay */}
                <div className="absolute inset-0 opacity-[.12]">
                    <img
                        src="https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=1600&h=700&fit=crop&auto=format"
                        alt=""
                        className="h-full w-full object-cover"
                    />
                </div>

                <div className="relative mx-auto max-w-7xl px-5 md:px-10">
                    {/* Navbar */}
                    <nav className="flex items-center justify-between py-4 sm:py-5">
                        <Logo />
                        <div className="hidden items-center gap-7 text-sm text-white/65 lg:flex">
                            <a
                                href="#branches"
                                className="hover:text-white transition-colors"
                            >
                                Chi nhánh
                            </a>
                            <a
                                href="#pricing"
                                className="hover:text-white transition-colors"
                            >
                                Bảng giá
                            </a>
                            <a
                                href="#community"
                                className="hover:text-white transition-colors"
                            >
                                Cộng đồng
                            </a>
                            <button
                                onClick={onEvents}
                                className="hover:text-white transition-colors"
                            >
                                Giải đấu
                            </button>
                        </div>
                        <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                            <button
                                onClick={onAuth}
                                className="hidden rounded-full border border-white/20 px-4 py-2 text-sm text-white hover:bg-white/10 lg:block transition-colors"
                            >
                                {userName || "Đăng nhập"}
                            </button>
                            <button
                                onClick={() => onBook()}
                                className="whitespace-nowrap rounded-full bg-[#b7ff36] px-3 py-2 text-xs font-bold text-[#0d1f2d] hover:bg-[#ccff66] transition-colors sm:px-5 sm:text-sm"
                            >
                                <span className="sm:hidden">Đặt sân</span>
                                <span className="hidden sm:inline">
                                    Đặt sân ngay
                                </span>
                            </button>
                        </div>
                    </nav>

                    {/* Hero copy */}
                    <div className="mt-10 grid items-center gap-10 lg:grid-cols-[1.4fr_.6fr]">
                        <div>
                            <p className="eyebrow text-[#b7ff36]">
                                01 / book your rally
                            </p>
                            <h1 className="font-display mt-4 text-[clamp(3rem,8vw,5.5rem)] font-extrabold leading-[.92] tracking-[-0.045em]">
                                SÂN ĐỈNH.
                                <br />
                                <span className="text-[#b7ff36]">
                                    TRẬN CHẤT.
                                </span>
                            </h1>
                            <p className="mt-6 max-w-md text-base leading-7 text-white/60">
                                {BRANCHES.length} chi nhánh khắp TP.HCM · Đặt
                                sân trong 60 giây · Check-in bằng QR · Tìm đồng
                                đội ngay trên app.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-4">
                                <button
                                    onClick={() => onBook()}
                                    className="flex items-center gap-2 rounded-xl bg-[#16a34a] px-7 py-3.5 font-bold text-white hover:bg-[#15803d] transition-colors"
                                >
                                    Tìm sân trống <span>→</span>
                                </button>
                                <button
                                    onClick={onCommunity}
                                    className="flex items-center gap-2 rounded-xl border border-white/20 px-7 py-3.5 font-semibold text-white hover:bg-white/10 transition-colors"
                                >
                                    Khám phá cộng đồng
                                </button>
                            </div>
                            {/* Stats bar */}
                            <div className="mt-10 flex flex-wrap gap-7">
                                {[
                                    [String(BRANCHES.length), "Chi nhánh HCM"],
                                    [
                                        String(
                                            BRANCHES.reduce(
                                                (s, b) => s + b.courts,
                                                0,
                                            ),
                                        ),
                                        "Sân",
                                    ],
                                    [String(STATS.members), "Thành viên"],
                                    ["03–23h", "Giờ mở cửa"],
                                ].map(([v, l]) => (
                                    <div key={l}>
                                        <div className="font-display text-2xl font-bold text-[#b7ff36]">
                                            {v}
                                        </div>
                                        <div className="text-xs text-white/50">
                                            {l}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                        {/* Live mini-chart */}
                        <div className="hidden rounded-2xl border border-white/15 bg-white/5 p-5 backdrop-blur lg:block">
                            <div className="flex justify-between text-sm">
                                <span className="text-white/55">
                                    Hoạt động hôm nay
                                </span>
                                <span className="text-[#b7ff36] flex items-center gap-1">
                                    <span className="animate-pulse">●</span>{" "}
                                    Trực tiếp
                                </span>
                            </div>
                            <div className="mt-5 flex items-end gap-1.5">
                                {(STATS.hours as number[]).map((h, i) => (
                                    <div
                                        key={i}
                                        style={{
                                            height: `${Math.max(3, h * 8)}px`,
                                        }}
                                        className="flex-1 rounded-t bg-gradient-to-t from-[#16a34a] to-[#b7ff36] opacity-80"
                                    />
                                ))}
                            </div>
                            <p className="mt-4 text-sm text-white/55">
                                <b className="text-white">{STATS.checkins}</b>{" "}
                                lượt check-in hôm nay
                            </p>
                            <div className="mt-4 grid grid-cols-2 gap-2 text-xs">
                                {[
                                    [
                                        String(STATS.bookings ?? 0),
                                        "Lịch hôm nay",
                                    ],
                                    [String(STATS.playing), "Sân đang chơi"],
                                ].map(([v, l]) => (
                                    <div
                                        key={l}
                                        className="rounded-lg bg-white/8 p-3"
                                    >
                                        <div className="font-display text-lg font-bold text-white">
                                            {v}
                                        </div>
                                        <div className="text-white/50">{l}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {/* ── Quick search widget ───────────────────────────── */}
            <section
                id="search"
                className="relative mx-auto -mt-7 max-w-7xl px-5 md:px-10"
            >
                <div className="rounded-2xl bg-white p-4 shadow-[0_16px_48px_rgba(13,31,45,.13)] md:p-5">
                    <div className="grid gap-3 md:grid-cols-[1.2fr_1fr_1fr_1fr_auto]">
                        <label className="field">
                            <span className="fi">📍</span>
                            <div>
                                <small>Chi nhánh</small>
                                <select
                                    value={branchSel}
                                    onChange={(e) =>
                                        setBranchSel(+e.target.value)
                                    }
                                    className="w-full"
                                >
                                    {BRANCHES.map((b, i) => (
                                        <option value={i} key={b.id}>
                                            Badminton Hub – {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </label>
                        <label className="field">
                            <span className="fi">📅</span>
                            <div>
                                <small>Ngày chơi</small>
                                <input
                                    aria-label="Ngày chơi"
                                    type="date"
                                    min={day()}
                                    value={date}
                                    onChange={(e) => setDate(e.target.value)}
                                />
                            </div>
                        </label>
                        <label className="field">
                            <span className="fi">🕐</span>
                            <div>
                                <small>Giờ bắt đầu</small>
                                <input
                                    aria-label="Giờ bắt đầu"
                                    type="time"
                                    min="03:00"
                                    max="22:30"
                                    step={1800}
                                    value={time}
                                    onChange={(e) => setTime(e.target.value)}
                                />
                            </div>
                        </label>
                        <label className="field">
                            <span className="fi">⏱</span>
                            <div>
                                <small>Thời lượng</small>
                                <select
                                    aria-label="Thời lượng"
                                    value={duration}
                                    onChange={(e) =>
                                        setDuration(+e.target.value)
                                    }
                                >
                                    {[0.5, 1, 1.5, 2, 2.5, 3, 4].map((v) => (
                                        <option value={v} key={v}>
                                            {v} giờ
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </label>
                        <button
                            onClick={() =>
                                onBook(branchSel, date, time, duration)
                            }
                            className="rounded-xl bg-[#16a34a] px-7 py-4 font-bold text-white transition hover:bg-[#15803d] whitespace-nowrap"
                        >
                            Tìm sân →
                        </button>
                    </div>
                </div>
            </section>

            {/* ── Branch list ───────────────────────────────────── */}
            <section
                id="branches"
                className="mx-auto max-w-7xl px-5 py-16 md:px-10"
            >
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="eyebrow text-[#16a34a]">
                            {BRANCHES.length} chi nhánh / tp. hồ chí minh
                        </p>
                        <h2 className="font-display mt-2 text-4xl font-bold tracking-tight">
                            Tìm sân gần bạn
                        </h2>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {areas.map((a) => (
                            <button
                                key={a}
                                onClick={() => setArea(a)}
                                className={`rounded-full border px-4 py-2 text-xs font-bold transition ${area === a ? "border-[#16a34a] bg-[#16a34a] text-white" : "border-[#dce4dc] text-[#6d8474] hover:border-[#16a34a]"}`}
                            >
                                {a}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {filtered.map((b) => (
                        <BranchCard
                            key={b.id}
                            branch={b}
                            onView={() =>
                                onBranch(
                                    BRANCHES.findIndex((x) => x.id === b.id),
                                )
                            }
                            onBook={() =>
                                onBook(BRANCHES.findIndex((x) => x.id === b.id))
                            }
                        />
                    ))}
                </div>
            </section>

            {/* ── Facilities ────────────────────────────────────── */}
            <section className="bg-[#0d1f2d] px-5 py-16 text-white md:px-10">
                <div className="mx-auto max-w-7xl">
                    <div className="grid gap-12 lg:grid-cols-2">
                        <div>
                            <p className="eyebrow text-[#b7ff36]">
                                tiện ích / facilities
                            </p>
                            <h2 className="font-display mt-3 text-4xl font-bold leading-tight">
                                Cơ sở vật chất
                                <br />
                                <span className="text-[#b7ff36]">
                                    đạt chuẩn quốc tế
                                </span>
                            </h2>
                            <p className="mt-5 leading-7 text-white/60">
                                Mỗi chi nhánh Badminton Hub được đầu tư thiết bị
                                cao cấp và không gian tối ưu cho trải nghiệm
                                chơi cầu lông chuyên nghiệp nhất.
                            </p>
                            <button
                                onClick={() => onBook()}
                                className="mt-7 rounded-xl bg-[#16a34a] px-6 py-3 font-bold text-white hover:bg-[#15803d] transition-colors"
                            >
                                Đặt sân ngay
                            </button>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {FACILITIES.map((f) => (
                                <div
                                    key={f.label}
                                    className="rounded-xl border border-white/10 bg-white/5 p-4"
                                >
                                    <div className="text-2xl">{f.icon}</div>
                                    <div className="mt-2 text-sm font-semibold text-white/85">
                                        {f.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ── Pricing ───────────────────────────────────────── */}
            <section
                id="pricing"
                className="mx-auto max-w-7xl px-5 py-16 md:px-10"
            >
                <p className="eyebrow text-[#16a34a]">bảng giá / pricing</p>
                <h2 className="font-display mt-2 text-4xl font-bold tracking-tight">
                    Giá sân minh bạch
                </h2>
                <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {PRICING.map((p) => (
                        <div
                            key={p.label}
                            className="rounded-2xl border-2 border-[#dce4dc] bg-white p-6 transition hover:border-[#16a34a] hover:shadow-lg"
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold text-[#6d8474]">
                                    {p.note}
                                </span>
                                <span
                                    className={`badge ${p.badge === "peak" ? "badge-orange" : p.badge === "weekend" ? "badge-purple" : p.badge === "fixed" ? "badge-blue" : "badge-green"}`}
                                >
                                    {p.badge === "peak"
                                        ? "Cao điểm"
                                        : p.badge === "weekend"
                                          ? "Cuối tuần"
                                          : p.badge === "fixed"
                                            ? "Cố định"
                                            : "Thường"}
                                </span>
                            </div>
                            <div className="font-display mt-4 text-3xl font-extrabold text-[#0d1f2d]">
                                {p.price}
                            </div>
                            <div className="mt-1 text-sm text-[#6d8474]">
                                {p.time}
                            </div>
                            <div className="mt-4 text-sm font-bold text-[#0d1f2d]">
                                {p.label}
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── Services ──────────────────────────────────────── */}
            <section className="bg-[#f3f6f4] px-5 py-16 md:px-10">
                <div className="mx-auto max-w-7xl">
                    <p className="eyebrow text-[#16a34a]">dịch vụ / services</p>
                    <h2 className="font-display mt-2 text-4xl font-bold tracking-tight">
                        Dịch vụ đi kèm
                    </h2>
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        {SERVICES.map((s) => (
                            <div
                                key={s.id}
                                className="flex items-center gap-4 rounded-xl border-2 border-[#dce4dc] bg-white p-4"
                            >
                                <div className="grid size-12 shrink-0 place-items-center rounded-xl bg-[#f0faea] text-2xl">
                                    {s.icon}
                                </div>
                                <div>
                                    <div className="font-semibold text-sm">
                                        {s.name}
                                    </div>
                                    <div className="text-[#16a34a] font-bold font-mono text-sm">
                                        {s.price.toLocaleString("vi-VN")}đ{" "}
                                        <span className="text-[#6d8474] font-normal">
                                            {s.unit}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── Membership ────────────────────────────────────── */}
            <section className="mx-auto max-w-7xl px-5 py-16 md:px-10">
                <p className="eyebrow text-[#16a34a]">membership / loyalty</p>
                <h2 className="font-display mt-2 text-4xl font-bold tracking-tight">
                    Gói thành viên
                </h2>
                <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {MEMBERSHIPS.map((m) => (
                        <div
                            key={m.tier}
                            style={{
                                background: m.bg,
                                borderColor: m.color + "33",
                            }}
                            className="rounded-2xl border-2 p-6"
                        >
                            <div
                                style={{ color: m.color }}
                                className="font-display text-2xl font-black"
                            >
                                {m.tier}
                            </div>
                            <div className="mt-2 font-bold text-lg">
                                {m.price}
                            </div>
                            <ul className="mt-4 space-y-2">
                                {m.perks.map((p: string) => (
                                    <li
                                        key={p}
                                        className="flex items-start gap-2 text-sm text-[#3d5a47]"
                                    >
                                        <span
                                            style={{ color: m.color }}
                                            className="mt-0.5 text-xs"
                                        >
                                            ✓
                                        </span>{" "}
                                        {p}
                                    </li>
                                ))}
                            </ul>
                            <button
                                onClick={onMembership}
                                style={{ background: m.color, color: "white" }}
                                className="mt-6 w-full rounded-xl py-2.5 text-sm font-bold hover:opacity-90 transition-opacity"
                            >
                                Đăng ký ngay
                            </button>
                        </div>
                    ))}
                </div>
            </section>

            {/* ── Community section ─────────────────────────────── */}
            <section
                id="community"
                className="bg-[#0d1f2d] px-5 py-16 text-white md:px-10"
            >
                <div className="mx-auto max-w-7xl">
                    <div className="grid gap-10 lg:grid-cols-[.8fr_1.2fr]">
                        <div>
                            <p className="eyebrow text-[#b7ff36]">
                                community court
                            </p>
                            <h2 className="font-display mt-3 text-4xl font-bold leading-tight">
                                Không chỉ đặt sân.
                                <br />
                                <span className="text-[#b7ff36]">
                                    Còn là tìm đồng đội.
                                </span>
                            </h2>
                            <p className="mt-5 leading-7 text-white/60">
                                Tìm người chơi cùng trình độ, tạo bài đăng tìm
                                đối, tham gia giải đấu nội bộ và kết nối với
                                cộng đồng cầu lông sôi động nhất TPHCM.
                            </p>
                            <button
                                onClick={onCommunity}
                                className="mt-7 rounded-xl bg-[#b7ff36] px-6 py-3 font-bold text-[#0d1f2d] hover:bg-[#ccff66] transition-colors"
                            >
                                Khám phá cộng đồng ↗
                            </button>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {COMMUNITY_POSTS.slice(0, 4).map((p) => (
                                <button
                                    key={p.id}
                                    onClick={onCommunity}
                                    className="community-card text-left"
                                >
                                    <span>{p.type.toUpperCase()}</span>
                                    <h3 className="mt-4 font-display text-base">
                                        {p.content.slice(0, 64)}…
                                    </h3>
                                    <p className="mt-2">
                                        {p.author} · {p.branch} · {p.time}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        {p.tags.slice(0, 2).map((t: string) => (
                                            <span
                                                key={t}
                                                className="rounded-full bg-white/10 px-2 py-0.5 text-[10px] text-white/70"
                                            >
                                                {t}
                                            </span>
                                        ))}
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ── CTA banner ────────────────────────────────────── */}
            <section className="relative overflow-hidden bg-[#16a34a] px-5 py-16 text-white md:px-10">
                <div className="court-lines" style={{ opacity: 0.08 }} />
                <div className="relative mx-auto max-w-7xl text-center">
                    <h2 className="font-display text-5xl font-black tracking-tight">
                        Sẵn sàng ra sân?
                    </h2>
                    <p className="mx-auto mt-4 max-w-md text-white/80">
                        Chọn chi nhánh, kiểm tra lịch trống và xác nhận đặt sân
                        ngay hôm nay.
                    </p>
                    <button
                        onClick={() => onBook()}
                        className="mt-8 rounded-2xl bg-[#b7ff36] px-10 py-4 font-bold text-[#0d1f2d] text-lg hover:bg-[#ccff66] transition-colors shadow-xl"
                    >
                        Đặt sân ngay – Miễn phí đăng ký
                    </button>
                </div>
            </section>

            {/* ── Footer ────────────────────────────────────────── */}
            <footer className="bg-[#0d1f2d] px-5 py-10 text-white/40 md:px-10">
                <div className="mx-auto max-w-7xl">
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <Logo />
                            <p className="mt-4 text-sm leading-6">
                                Hệ thống quản lý và đặt sân cầu lông hàng đầu
                                TP.HCM.
                            </p>
                        </div>
                        {[
                            {
                                title: "Dịch vụ",
                                links: [
                                    "Đặt sân online",
                                    "Đặt sân cố định",
                                    "Giải đấu",
                                    "Coaching",
                                ],
                            },
                            {
                                title: "Hỗ trợ",
                                links: [
                                    "Trung tâm trợ giúp",
                                    "Chính sách hủy",
                                    "Liên hệ sân",
                                    "Báo cáo vấn đề",
                                ],
                            },
                            {
                                title: "Công ty",
                                links: [
                                    "Về chúng tôi",
                                    "Nhượng quyền",
                                    "Tuyển dụng",
                                    "Blog",
                                ],
                            },
                        ].map((col) => (
                            <div key={col.title}>
                                <div className="mb-4 text-xs font-bold uppercase tracking-widest text-white/60">
                                    {col.title}
                                </div>
                                <ul className="space-y-2">
                                    {col.links.map((l) => (
                                        <li key={l}>
                                            <button
                                                onClick={
                                                    col.title === "Dịch vụ"
                                                        ? l === "Giải đấu"
                                                            ? onEvents
                                                            : () => onBook()
                                                        : onSupport
                                                }
                                                className="text-sm hover:text-white transition-colors"
                                            >
                                                {l}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                    <div className="mt-10 border-t border-white/10 pt-6 text-center text-xs">
                        © 2026 Badminton Hub · Made for every rally in Sài Gòn.
                    </div>
                </div>
            </footer>
        </div>
    );
}

function BranchCard({
    branch: b,
    onView,
    onBook,
}: {
    branch: (typeof BRANCHES)[0];
    onView: () => void;
    onBook: () => void;
}) {
    return (
        <div className="overflow-hidden rounded-2xl border-2 border-[#dce4dc] bg-white transition hover:border-[#16a34a] hover:shadow-xl group">
            <div className="relative h-40 overflow-hidden bg-[#0d1f2d]">
                <img
                    src={`https://images.unsplash.com/${b.img}?w=480&h=200&fit=crop&auto=format`}
                    alt={b.name}
                    className="h-full w-full object-cover opacity-80 transition group-hover:scale-105 duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[#0d1f2d]/60 to-transparent" />
                <div className="absolute bottom-3 left-3 right-3 flex items-end justify-between">
                    <div className="badge badge-green">{b.courts} sân</div>
                    <div className="rounded-full bg-black/40 px-2 py-1 text-xs font-bold text-white backdrop-blur">
                        {b.rating}★
                    </div>
                </div>
            </div>
            <div className="p-4">
                <div className="flex items-start justify-between gap-2">
                    <h3 className="font-display text-base font-bold">
                        Badminton Hub – {b.name}
                    </h3>
                </div>
                <p className="mt-1 text-xs text-[#6d8474]">{b.address}</p>
                <div className="mt-3 flex flex-wrap gap-1">
                    {b.parking && (
                        <span className="badge badge-gray">🚗 Parking</span>
                    )}
                    {b.shower && (
                        <span className="badge badge-gray">🚿 Shower</span>
                    )}
                    {b.cafe && (
                        <span className="badge badge-gray">☕ Cafe</span>
                    )}
                    {b.wifi && (
                        <span className="badge badge-gray">📶 WiFi</span>
                    )}
                </div>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[#f0f4f0] pt-4">
                    <div>
                        <span className="font-mono text-base font-bold text-[#16a34a]">
                            {b.price.toLocaleString("vi-VN")}đ
                        </span>
                        <span className="text-xs text-[#6d8474]">/giờ</span>
                    </div>
                    <div className="flex shrink-0 gap-2 whitespace-nowrap">
                        <button
                            onClick={onView}
                            className="rounded-lg border border-[#dce4dc] px-3 py-1.5 text-xs font-bold hover:border-[#16a34a] transition-colors"
                        >
                            Chi tiết
                        </button>
                        <button
                            onClick={onBook}
                            className="rounded-lg bg-[#16a34a] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#15803d] transition-colors"
                        >
                            Đặt sân
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export function Logo({ dark = false }: { dark?: boolean } = {}) {
    return (
        <div className="flex items-center gap-2.5">
            <div className="grid size-9 place-items-center rounded-xl bg-[#b7ff36] text-[#0d1f2d] shadow-[0_0_24px_#b7ff3650]">
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                >
                    <circle cx="12" cy="12" r="3" />
                    <path d="M12 9C10.5 7 8 6 5.5 7.5S3 12 4.5 14.5" />
                    <path d="M12 15c1.5 2 4 3 6.5 1.5S21 12 19.5 9.5" />
                    <path d="M9 12c-2 1.5-3 4-1.5 6.5S12 21 14.5 19.5" />
                    <path d="M15 12c2-1.5 3-4 1.5-6.5S12 3 9.5 4.5" />
                </svg>
            </div>
            <span
                className={
                    "font-display text-lg font-extrabold tracking-tight " +
                    (dark ? "text-[#0d1f2d]" : "text-white")
                }
            >
                BADMINTON{" "}
                <i
                    className={
                        "not-italic " +
                        (dark ? "text-[#16a34a]" : "text-[#b7ff36]")
                    }
                >
                    HUB
                </i>
            </span>
        </div>
    );
}
