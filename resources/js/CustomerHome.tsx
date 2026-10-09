import { useState } from "react";
import { BRANCHES, day, money } from "./data";
import { useHub } from "./hub";
import SearchAssistant from "./SearchAssistant";
type Props = {
    onBook: (
        idx?: number,
        date?: string,
        time?: string,
        duration?: number,
    ) => void;
    onBranch: (idx: number) => void;
    onCommunity: () => void;
    onAuth: () => void;
    onManage?: () => void;
    onEvents: () => void;
    onMembership: () => void;
    onSupport: () => void;
    onAiBook: (d: any) => void;
    userName?: string;
};
export function Logo({ dark = false }: { dark?: boolean }) {
    return (
        <div
            className={
                "font-display flex items-center gap-2 text-xl font-black " +
                (dark ? "text-[#0d1f2d]" : "text-white")
            }
        >
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#b7ff36] text-[#0d1f2d]">
                ↗
            </span>
            <span>
                BADMINTON<span className="ml-1 text-[#36bb64]">HUB</span>
            </span>
        </div>
    );
}
export default function CustomerHome(p: Props) {
    const { data } = useHub();
    const [area, setArea] = useState(""),
        [date, setDate] = useState(day(1)),
        [time, setTime] = useState("18:00");
    const filtered = BRANCHES.filter((b) => !area || b.name === area);
    return (
        <div className="sports-home">
            <header className="sports-hero">
                <nav className="relative z-10 mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-6 md:px-10">
                    <button
                        onClick={() => {
                            location.hash = "home";
                        }}
                        aria-label="Trang chủ"
                    >
                        <Logo />
                    </button>
                    <div className="flex flex-wrap items-center gap-4 text-sm font-semibold text-white/80">
                        <a href="#branches">Sân cầu lông</a>
                        <button onClick={p.onCommunity}>Cộng đồng</button>
                        <button onClick={p.onEvents}>Giải đấu</button>
                        <a href="#coaching">Học cầu lông</a>
                        {p.onManage && (
                            <button onClick={p.onManage}>Quản lý</button>
                        )}
                        <button
                            className="rounded-full border border-white/25 px-4 py-2 text-white"
                            onClick={p.onAuth}
                        >
                            {p.userName ?? "Đăng nhập"}
                        </button>
                    </div>
                </nav>
                <div className="relative mx-auto grid max-w-7xl gap-8 px-5 pb-28 pt-10 md:grid-cols-[1.15fr_1fr] md:px-10 md:pt-16">
                    <div className="relative z-10">
                        <span className="hero-tag">
                            TP. HỒ CHÍ MINH · LÊN SÂN CÙNG NHAU
                        </span>
                        <h1 className="font-display mt-6 text-5xl font-black leading-[1.07] tracking-tight md:text-7xl">
                            BẬT NĂNG LƯỢNG.
                            <br />
                            <span className="text-[#b7ff36]">CHẠM ĐAM MÊ.</span>
                        </h1>
                        <p className="mt-6 max-w-lg text-base leading-7 text-white/70">
                            Một trận cầu hay bắt đầu từ sân chơi phù hợp. Tìm
                            giờ trống, rủ bạn đồng hành và sẵn sàng cho cú đánh
                            tiếp theo.
                        </p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <button
                                className="btn-lime"
                                onClick={() => p.onBook()}
                            >
                                Đặt sân ngay ↗
                            </button>
                            <button
                                className="rounded-full border border-white/30 px-6 py-3 font-bold"
                                onClick={p.onCommunity}
                            >
                                Tìm bạn chơi
                            </button>
                        </div>
                        <div className="mt-8 flex flex-wrap gap-6 text-sm text-white/70">
                            <span>
                                🏸 {data?.branches?.length ?? 0} chi nhánh
                            </span>
                            <span>◷ Chọn lịch trong vài bước</span>
                            <span>♡ Chơi theo cách của bạn</span>
                        </div>
                    </div>
                    <div className="court-art" aria-hidden="true">
                        <div className="court-art-glow" />
                        <div className="court-art-board">
                            <svg
                                viewBox="0 0 320 520"
                                width="100%"
                                height="100%"
                            >
                                <rect
                                    x="15"
                                    y="15"
                                    width="290"
                                    height="490"
                                    fill="#148144"
                                    stroke="#f2ffe6"
                                    strokeWidth="3"
                                />
                                <path
                                    d="M15 260H305M15 160H305M15 360H305M40 15V505M280 15V505M40 55H280M40 465H280M160 15V160M160 360V505"
                                    fill="none"
                                    stroke="#efffe6"
                                    strokeWidth="2"
                                />
                                <path
                                    d="M0 260H320"
                                    stroke="#b7ff36"
                                    strokeWidth="9"
                                />
                            </svg>
                        </div>
                        <div className="court-art-shuttle">🏸</div>
                        <div className="court-art-label">
                            YOUR NEXT
                            <br />
                            <strong>GREAT GAME.</strong>
                            <span>PLAY / CONNECT / GROW</span>
                        </div>
                    </div>
                </div>
            </header>
            <main className="relative mx-auto max-w-7xl px-5 md:px-10">
                <div className="-mt-16">
                    <SearchAssistant book={p.onAiBook} compact />
                </div>
                <section id="branches" className="scroll-mt-24 py-14">
                    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="eyebrow text-[#168449]">
                                CHỌN ĐIỂM HẸN TIẾP THEO
                            </p>
                            <h2 className="font-display mt-2 text-3xl font-black md:text-4xl">
                                Sân gần bạn. Trận cầu của bạn.
                            </h2>
                        </div>
                        <select
                            className="input"
                            aria-label="Lọc khu vực"
                            value={area}
                            onChange={(e) => setArea(e.target.value)}
                        >
                            <option value="">Toàn TP.HCM</option>
                            {BRANCHES.map((b) => (
                                <option key={b.id}>{b.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="mb-6 flex flex-wrap items-end gap-3">
                        <label className="text-sm">
                            Ngày chơi
                            <input
                                className="input mt-1 block"
                                type="date"
                                min={day()}
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                            />
                        </label>
                        <label className="text-sm">
                            Giờ bắt đầu
                            <input
                                className="input mt-1 block"
                                type="time"
                                step={1800}
                                value={time}
                                onChange={(e) => setTime(e.target.value)}
                            />
                        </label>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {filtered.map((b) => {
                            const raw = data?.branches.find(
                                (v: any) => v.id === b.id,
                            );
                            const rates =
                                raw?.courts
                                    ?.filter((v: any) => v.status === "active")
                                    .map((v: any) => v.hourly_rate) ?? [];
                            return (
                                <article className="branch-card" key={b.id}>
                                    <button
                                        className="branch-visual"
                                        onClick={() =>
                                            p.onBranch(BRANCHES.indexOf(b))
                                        }
                                        aria-label={"Xem " + b.rawName}
                                    >
                                        <div className="mini-court" />
                                        <span className="branch-area">
                                            {b.name}
                                        </span>
                                        <span className="branch-number">
                                            {String(
                                                BRANCHES.indexOf(b) + 1,
                                            ).padStart(2, "0")}
                                        </span>
                                    </button>
                                    <div className="p-5">
                                        <p className="text-xs font-bold text-[#168449]">
                                            {b.courts} SÂN ·{" "}
                                            {raw
                                                ? String(
                                                      Math.floor(
                                                          raw.opens_minute / 60,
                                                      ),
                                                  ).padStart(2, "0") +
                                                  ":" +
                                                  String(
                                                      raw.opens_minute % 60,
                                                  ).padStart(2, "0")
                                                : ""}
                                            –
                                            {raw
                                                ? String(
                                                      Math.floor(
                                                          raw.closes_minute /
                                                              60,
                                                      ),
                                                  ).padStart(2, "0") +
                                                  ":" +
                                                  String(
                                                      raw.closes_minute % 60,
                                                  ).padStart(2, "0")
                                                : ""}
                                        </p>
                                        <h3 className="font-display my-2 text-xl font-bold">
                                            {b.rawName}
                                        </h3>
                                        <p className="min-h-10 text-sm text-slate-500">
                                            {b.address}
                                        </p>
                                        <p className="my-3 text-sm">
                                            {rates.length ? (
                                                <>
                                                    Từ{" "}
                                                    <b>
                                                        {money(
                                                            Math.min(...rates),
                                                        )}
                                                    </b>{" "}
                                                    / giờ
                                                </>
                                            ) : (
                                                "Xem giá theo khung giờ"
                                            )}
                                        </p>
                                        <div className="flex gap-2">
                                            <button
                                                className="btn flex-1"
                                                onClick={() =>
                                                    p.onBook(
                                                        BRANCHES.indexOf(b),
                                                        date,
                                                        time,
                                                        2,
                                                    )
                                                }
                                            >
                                                Xem sân trống
                                            </button>
                                            <button
                                                className="btn-outline"
                                                onClick={() =>
                                                    p.onBranch(
                                                        BRANCHES.indexOf(b),
                                                    )
                                                }
                                            >
                                                Chi tiết ↗
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                </section>
                <section className="sports-connect mb-12 grid gap-7 p-7 md:grid-cols-2 md:p-10">
                    <div>
                        <span className="hero-tag">
                            MỖI TRẬN ĐẤU, THÊM MỘT KẾT NỐI
                        </span>
                        <h2 className="font-display my-5 text-3xl font-black">
                            Đối thủ hôm nay.
                            <br />
                            Đồng đội ngày mai.
                        </h2>
                        <p className="max-w-md text-white/70">
                            Tìm nhóm đúng trình độ, tham gia giải đấu hoặc học
                            cùng huấn luyện viên. Luôn có một cách để tiến thêm.
                        </p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {[
                            [
                                p.onCommunity,
                                "↗",
                                "Tìm bạn chơi",
                                "Tìm nhóm, chia sẻ và trò chuyện",
                            ],
                            [
                                p.onEvents,
                                "🏆",
                                "Tham gia giải đấu",
                                "Xem lịch thi đấu và bảng xếp hạng",
                            ],
                            [
                                () => {
                                    location.hash = "coaching";
                                },
                                "🏸",
                                "Học cầu lông",
                                "Chọn huấn luyện viên và đặt buổi học",
                            ],
                            [
                                p.onMembership,
                                "♡",
                                "Quyền lợi của bạn",
                                "Tích điểm, đổi quà và ưu đãi",
                            ],
                        ].map(([fn, icon, title, description]: any) => (
                            <button
                                key={title}
                                className="connect-card"
                                onClick={fn}
                            >
                                <span className="text-2xl text-[#b7ff36]">
                                    {icon}
                                </span>
                                <strong className="mt-3 block">{title}</strong>
                                <span className="mt-2 block text-xs text-white/60">
                                    {description}
                                </span>
                            </button>
                        ))}
                    </div>
                </section>
            </main>
            <footer className="bg-[#0d1f2d] px-5 py-10 text-white/70">
                <div className="mx-auto flex max-w-7xl flex-wrap justify-between gap-6">
                    <Logo />
                    <div className="flex flex-wrap gap-5 text-sm">
                        <a href="#content/about">Về chúng tôi</a>
                        <a href="#content/blog">Góc cầu lông</a>
                        <a href="#content/job">Tuyển dụng</a>
                        <a href="#content/franchise">Hợp tác</a>
                        <button onClick={p.onSupport}>
                            Hỗ trợ & câu hỏi thường gặp
                        </button>
                    </div>
                </div>
            </footer>
        </div>
    );
}
