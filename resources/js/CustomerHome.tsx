import { useState } from "react";
import { BRANCHES, day, money } from "./data";
import { useHub } from "./hub";
import SearchAssistant from "./SearchAssistant";
import Icon, { type IconName } from "./Icon";
import { Logo } from "./Brand";
export { Logo } from "./Brand";
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
export function CourtIllustration() {
    return (
        <svg
            viewBox="0 0 360 210"
            fill="none"
            aria-hidden="true"
            className="venue-court"
        >
            <g transform="translate(72 15) rotate(16 110 90)">
                <rect
                    x="0"
                    y="0"
                    width="216"
                    height="180"
                    rx="2"
                    fill="currentColor"
                    fillOpacity=".08"
                    stroke="currentColor"
                    strokeWidth="2"
                />
                <path
                    d="M0 90h216M22 0v180M194 0v180M22 50h172M22 130h172M22 14h172M22 166h172M108 0v50M108 130v50"
                    stroke="currentColor"
                    strokeWidth="1.3"
                />
                <path d="M-14 90h244" stroke="currentColor" strokeWidth="4" />
            </g>
        </svg>
    );
}
export default function CustomerHome(p: Props) {
    const { data } = useHub();
    const [area, setArea] = useState(""),
        [venuePage, setVenuePage] = useState(1),
        [date, setDate] = useState(day(1)),
        [time, setTime] = useState("18:00");
    const filtered = BRANCHES.filter((b) => !area || b.district === area);
    const courts =
        data?.branches.reduce(
            (n: number, b: any) =>
                n + (b.courts_count ?? b.courts?.length ?? 0),
            0,
        ) ?? 0;
    return (
        <div className="sports-home">
            <section className="home-hero home-container">
                <div className="hero-copy">
                    <span className="section-kicker">
                        <span className="kicker-dot" />
                        SÂN CHƠI CHO ĐAM MÊ CỦA BẠN
                    </span>
                    <h1>
                        Lên sân.
                        <br />
                        <span>Chơi hết mình.</span>
                    </h1>
                    <p>
                        Tìm một sân chơi phù hợp, rủ thêm đồng đội.
                        <br className="hidden sm:block" /> Phần còn lại, dành
                        cho những trận cầu thật hay.
                    </p>
                    <div className="hero-actions">
                        <button className="btn" onClick={() => p.onBook()}>
                            Tìm sân ngay
                            <Icon name="northeast" />
                        </button>
                        <button
                            className="hero-secondary"
                            onClick={p.onCommunity}
                        >
                            <Icon name="people" />
                            Tìm bạn chơi
                        </button>
                    </div>
                    <div className="hero-proof">
                        <div>
                            <strong>{data?.branches.length ?? 0}</strong>
                            <span>Chi nhánh tại TP.HCM</span>
                        </div>
                        <div>
                            <strong>{courts}</strong>
                            <span>Sân cho trận cầu tiếp theo</span>
                        </div>
                        <span className="hero-proof-icon">
                            <Icon name="racket" size={36} />
                        </span>
                    </div>
                </div>
                <div className="hero-media">
                    <img
                        src="https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=1200&q=85"
                        alt="Người chơi bật nhảy đánh cầu trên sân cầu lông"
                        fetchPriority="high"
                    />
                    <span className="hero-photo-label">
                        <Icon name="racket" size={16} /> Mỗi ngày, một trận cầu
                        hay.
                    </span>
                    <div className="hero-media-caption">
                        <span>PLAY. CONNECT. REPEAT.</span>
                        <strong>
                            Hẹn nhau
                            <br />
                            trên sân.
                        </strong>
                        <Icon name="northeast" size={35} />
                    </div>
                    <div className="hero-floating">
                        <span className="floating-icon">
                            <Icon name="calendar" size={23} />
                        </span>
                        <div>
                            <strong>Lịch của bạn. Sân của bạn.</strong>
                            <span>Chọn ngày, chọn giờ, sẵn sàng chơi.</span>
                        </div>
                        <span className="floating-check">
                            <Icon name="check" size={14} />
                        </span>
                    </div>
                </div>
            </section>
            <div className="home-container home-search">
                <SearchAssistant book={p.onAiBook} compact />
            </div>
            <main className="home-container">
                <section id="branches" className="venues-section">
                    <div className="section-heading">
                        <div>
                            <span className="section-kicker">
                                ĐIỂM HẸN TIẾP THEO
                            </span>
                            <h2>
                                Chọn sân. <span>Chọn cuộc vui.</span>
                            </h2>
                            <p>
                                Khám phá các chi nhánh và tìm khung giờ phù hợp
                                với bạn.
                            </p>
                        </div>
                        <span className="section-count">
                            <Icon name="pin" size={17} />
                            TP. Hồ Chí Minh
                        </span>
                    </div>
                    <div className="venue-filters">
                        <div
                            className="district-filters"
                            aria-label="Lọc phường / xã"
                        >
                            <button
                                className={!area ? "selected" : ""}
                                onClick={() => {
                                    setArea("");
                                    setVenuePage(1);
                                }}
                                aria-pressed={!area}
                            >
                                Tất cả phường / xã
                            </button>
                            {[...new Set(BRANCHES.map((b) => b.district))].map(
                                (district) => (
                                    <button
                                        key={district}
                                        className={
                                            area === district ? "selected" : ""
                                        }
                                        aria-pressed={area === district}
                                        onClick={() => {
                                            setArea(district);
                                            setVenuePage(1);
                                        }}
                                    >
                                        {district}
                                    </button>
                                ),
                            )}
                        </div>
                        <div className="venue-date-filters">
                            <label>
                                <Icon name="calendar" size={17} />
                                <span>Ngày chơi</span>
                                <input
                                    type="date"
                                    aria-label="Ngày chơi tại chi nhánh"
                                    min={day()}
                                    value={date}
                                    onChange={(e) => setDate(e.target.value)}
                                />
                            </label>
                            <label>
                                <Icon name="clock" size={17} />
                                <span>Giờ bắt đầu</span>
                                <input
                                    type="time"
                                    aria-label="Giờ chơi tại chi nhánh"
                                    step={1800}
                                    value={time}
                                    onChange={(e) => setTime(e.target.value)}
                                />
                            </label>
                            <span className="venue-results">
                                {filtered.length} chi nhánh
                            </span>
                        </div>
                    </div>
                    <div className="venue-grid">
                        {filtered
                            .slice((venuePage - 1) * 6, venuePage * 6)
                            .map((b) => {
                                const raw = data?.branches.find(
                                    (v: any) => v.id === b.id,
                                );
                                const rates =
                                    raw?.courts
                                        ?.filter(
                                            (v: any) => v.status === "active",
                                        )
                                        .map((v: any) => v.hourly_rate) ?? [];
                                const formatHour = (minute: number) =>
                                    String(Math.floor(minute / 60)).padStart(
                                        2,
                                        "0",
                                    ) +
                                    ":" +
                                    String(minute % 60).padStart(2, "0");
                                return (
                                    <article className="venue-card" key={b.id}>
                                        <button
                                            className={
                                                "venue-visual venue-color-" +
                                                (BRANCHES.indexOf(b) % 4)
                                            }
                                            onClick={() =>
                                                p.onBranch(BRANCHES.indexOf(b))
                                            }
                                            aria-label={"Xem " + b.rawName}
                                        >
                                            <CourtIllustration />
                                            <span className="venue-tag">
                                                <Icon name="court" size={14} />
                                                {b.courts} sân
                                            </span>
                                            <span className="venue-district">
                                                {b.name}
                                            </span>
                                            <span className="venue-visual-arrow">
                                                <Icon
                                                    name="northeast"
                                                    size={20}
                                                />
                                            </span>
                                        </button>
                                        <div className="venue-content">
                                            <div className="venue-card-meta">
                                                <span>
                                                    <Icon
                                                        name="clock"
                                                        size={14}
                                                    />
                                                    {raw
                                                        ? formatHour(
                                                              raw.opens_minute,
                                                          ) +
                                                          " – " +
                                                          formatHour(
                                                              raw.closes_minute,
                                                          )
                                                        : "Xem giờ mở cửa"}
                                                </span>
                                                {b.rating !== "—" && (
                                                    <span className="venue-rating">
                                                        <Icon
                                                            name="star"
                                                            size={14}
                                                        />
                                                        {b.rating}
                                                    </span>
                                                )}
                                            </div>
                                            <h3>
                                                <button
                                                    onClick={() =>
                                                        p.onBranch(
                                                            BRANCHES.indexOf(b),
                                                        )
                                                    }
                                                >
                                                    {b.rawName}
                                                </button>
                                            </h3>
                                            <p className="venue-address">
                                                <Icon name="pin" size={15} />
                                                {b.address}
                                            </p>
                                            <a
                                                className="mb-3 inline-block text-sm font-bold text-[#19543e]"
                                                href={
                                                    b.directions_url ||
                                                    "https://www.google.com/maps/dir/?api=1&destination=" +
                                                        encodeURIComponent(
                                                            b.address,
                                                        )
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                Chỉ đường Google Maps ↗
                                            </a>
                                            <div className="venue-card-bottom">
                                                <div>
                                                    <span>Giá sân / giờ</span>
                                                    <strong>
                                                        {rates.length
                                                            ? money(
                                                                  Math.min(
                                                                      ...rates,
                                                                  ),
                                                              )
                                                            : "Xem giá"}
                                                    </strong>
                                                </div>
                                                <button
                                                    className="btn"
                                                    onClick={() =>
                                                        p.onBook(
                                                            BRANCHES.indexOf(b),
                                                            date,
                                                            time,
                                                            2,
                                                        )
                                                    }
                                                >
                                                    Đặt sân
                                                    <Icon
                                                        name="arrow"
                                                        size={16}
                                                    />
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                    </div>
                    {filtered.length > 6 && (
                        <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                            <span className="text-sm text-slate-500">
                                Trang {venuePage}/
                                {Math.ceil(filtered.length / 6)}
                            </span>
                            <div className="flex gap-2">
                                <button
                                    className="btn-outline"
                                    disabled={venuePage <= 1}
                                    onClick={() => setVenuePage((p) => p - 1)}
                                >
                                    Chi nhánh trước
                                </button>
                                <button
                                    className="btn-outline"
                                    disabled={
                                        venuePage >=
                                        Math.ceil(filtered.length / 6)
                                    }
                                    onClick={() => setVenuePage((p) => p + 1)}
                                >
                                    Chi nhánh tiếp
                                </button>
                            </div>
                        </div>
                    )}
                </section>
                <section className="home-community">
                    <div className="community-promo-copy">
                        <span className="section-kicker">
                            CÓ ĐỒNG ĐỘI, CÓ CUỘC VUI
                        </span>
                        <h2>
                            Đi cùng nhau.
                            <br />
                            <span>Chơi hay hơn.</span>
                        </h2>
                        <p>
                            Tìm người chơi cùng trình độ, chia sẻ khoảnh khắc
                            trên sân và hẹn nhau cho trận đấu tiếp theo.
                        </p>
                        <button className="btn-lime" onClick={p.onCommunity}>
                            Khám phá cộng đồng
                            <Icon name="northeast" />
                        </button>
                    </div>
                    <div className="community-promo-art" aria-hidden="true">
                        <div className="community-orbit" />
                        <Icon name="racket" size={180} />
                        <span className="orbit-tag orbit-tag-one">
                            CÙNG LÊN SÂN
                        </span>
                        <span className="orbit-tag orbit-tag-two">
                            CÙNG TIẾN BỘ ↗
                        </span>
                    </div>
                </section>
                <section className="home-explore">
                    <div className="section-heading">
                        <div>
                            <span className="section-kicker">
                                THÊM LÝ DO ĐỂ LÊN SÂN
                            </span>
                            <h2>
                                Không chỉ là <span>một trận đấu.</span>
                            </h2>
                        </div>
                    </div>
                    <div className="explore-grid">
                        {(
                            [
                                [
                                    p.onEvents,
                                    "trophy",
                                    "Giải đấu & giao lưu",
                                    "Thử sức, gặp gỡ và cùng nhau chinh phục những trận đấu mới.",
                                ],
                                [
                                    () => {
                                        location.hash = "coaching";
                                    },
                                    "racket",
                                    "Nâng tầm kỹ năng",
                                    "Tìm huấn luyện viên phù hợp và đặt lịch cho buổi học tiếp theo.",
                                ],
                                [
                                    p.onMembership,
                                    "gift",
                                    "Chơi đều, thêm ưu đãi",
                                    "Khám phá quyền lợi hội viên, tích điểm và đổi quà khi chơi.",
                                ],
                            ] as [() => void, IconName, string, string][]
                        ).map(([fn, icon, title, description]) => (
                            <button
                                className="explore-card"
                                key={title}
                                onClick={fn}
                            >
                                <span className="explore-icon">
                                    <Icon name={icon} size={26} />
                                </span>
                                <h3>{title}</h3>
                                <p>{description}</p>
                                <span className="explore-link">
                                    Khám phá
                                    <Icon name="arrow" size={18} />
                                </span>
                            </button>
                        ))}
                    </div>
                </section>
            </main>
            <footer className="site-footer">
                <div className="home-container footer-grid">
                    <div>
                        <Logo dark />
                        <p>
                            Sân chơi cho đam mê.
                            <br />
                            Điểm hẹn cho những kết nối mới.
                        </p>
                    </div>
                    <div>
                        <strong>Khám phá</strong>
                        <button onClick={() => p.onBook()}>
                            Tìm & đặt sân
                        </button>
                        <button onClick={p.onCommunity}>
                            Cộng đồng cầu lông
                        </button>
                        <button onClick={p.onEvents}>Giải đấu & sự kiện</button>
                    </div>
                    <div>
                        <strong>Badminton Hub</strong>
                        <a href="#content/about">Về chúng tôi</a>
                        <a href="#content/blog">Góc cầu lông</a>
                        <a href="#content/franchise">Hợp tác cùng HUB</a>
                    </div>
                    <div>
                        <strong>Luôn sẵn sàng hỗ trợ</strong>
                        <p>Cần hỗ trợ cho buổi chơi của bạn?</p>
                        <button
                            className="footer-support"
                            onClick={p.onSupport}
                        >
                            Liên hệ & câu hỏi thường gặp
                            <Icon name="northeast" size={17} />
                        </button>
                    </div>
                </div>
                <div className="home-container footer-bottom">
                    <span>© {new Date().getFullYear()} Badminton Hub</span>
                    <span>
                        Hẹn bạn trên sân.
                        <Icon name="racket" size={16} />
                    </span>
                </div>
            </footer>
        </div>
    );
}
