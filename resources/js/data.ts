export type Row = Record<string, any>;
export let BRANCHES: Row[] = [];
export let SERVICES: Row[] = [];
export let COMMUNITY_POSTS: Row[] = [];
export let MEMBERSHIPS: Row[] = [];
export let STATS: Row = {
    members: 0,
    checkins: 0,
    playing: 0,
    hours: Array(14).fill(0),
};
export let PRICING: Row[] = [];
const images = [
    "photo-1626224583764-f87db24ac4ea",
    "photo-1587174486073-ae5e5cff23aa",
    "photo-1554284126-aa88f22d8b74",
    "photo-1551698618-1dfe5d97d256",
    "photo-1580748141549-71748dbe0bdc",
    "photo-1608245449230-4ac19066d2d0",
    "photo-1626224583764-f87db24ac4ea",
    "photo-1551698618-1dfe5d97d256",
];
export const statusText = (s: string) =>
    (
        ({
            customer: "Khách hàng",
            demo_bank: "Chuyển khoản thử nghiệm trước đây",
            demo_momo: "Giao dịch thử nghiệm trước đây",
            cash: "Tiền mặt tại quầy",
            bank: "Chuyển khoản tại quầy",
            payos: "Chuyển khoản PayOS",
            wallet: "Số dư Ví HUB",
            manager: "Quản lý",
            staff: "Nhân viên",
            cashier: "Thu ngân",
            admin: "Quản trị viên",
            expired: "Hết hạn",
            confirmed: "Đã xác nhận",
            checked_in: "Đang chơi",
            completed: "Hoàn tất",
            cancelled: "Đã hủy",
            pending: "Chờ xác nhận",
            accepted: "Đã duyệt",
            rejected: "Từ chối",
            withdrawn: "Đã rời",
            open: "Đang mở",
            closed: "Đã đóng",
            hidden: "Đã ẩn",
            waiting: "Đang chờ",
            notified: "Đã thông báo",
            scheduled: "Đã lên lịch",
            published: "Đang mở",
            paid: "Đã thanh toán",
            credited: "Đã cộng vào ví",
            failed: "Chưa thành công",
            resolved: "Đã xử lý",
            active: "Hoạt động",
            locked: "Đã khóa",
            maintenance: "Bảo trì",
        }) as Record<string, string>
    )[s] ?? s;
export const localDateTime = (s: string) => {
    const date = new Date(s);
    return new Intl.DateTimeFormat("sv-SE", {
        timeZone: "Asia/Ho_Chi_Minh",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    })
        .format(date)
        .replace(" ", "T");
};
export const money = (v: number = 0) =>
    new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(v);
export const stamp = (s: string) =>
    new Date(s).toLocaleString("vi-VN", {
        timeZone: "Asia/Ho_Chi_Minh",
        hour12: false,
    });
export const day = (delta = 0) => {
    const d = new Date();
    d.setDate(d.getDate() + delta);
    return new Intl.DateTimeFormat("sv-SE", {
        timeZone: "Asia/Ho_Chi_Minh",
    }).format(d);
};
export const times = Array.from(
    { length: 40 },
    (_, i) =>
        String(3 + Math.floor(i / 2)).padStart(2, "0") +
        ":" +
        (i % 2 ? "30" : "00"),
);
export function hydrate(data: Row, posts: Row[] = []) {
    BRANCHES = data.branches.map((b: Row, i: number) => ({
        ...b,
        rawName: b.name,
        name: b.name.replace(/^Badminton Hub\s*[–-]\s*/, ""),
        courts: b.courts_count ?? b.courts.length,
        courtList: b.courts,
        price: Math.min(...b.courts.map((c: Row) => c.hourly_rate)),
        img: images[i % images.length],
        rating: data.ratings[b.id]?.rating
            ? Number(data.ratings[b.id].rating).toFixed(1)
            : "—",
        parking: b.amenities?.includes("Parking"),
        shower: b.amenities?.includes("Shower"),
        cafe: b.amenities?.includes("Cafe"),
        wifi: b.amenities?.includes("WiFi"),
    }));
    SERVICES = (data.branches[0]?.services ?? []).map((s: Row, i: number) => ({
        ...s,
        icon: ["🎾", "🏸", "💧", "🥤", "👟", "🔒", "🚿", "👨‍🏫"][i % 8],
        unit: "/" + s.unit,
    }));
    MEMBERSHIPS = [
        {
            tier: "Basic",
            color: "#6d8474",
            bg: "#f3f6f4",
            price: "Miễn phí",
            perks: ["Đặt sân online", "Lịch sử đặt sân", "Kết nối cộng đồng"],
        },
        ...data.plans.map((p: Row, i: number) => ({
            tier: p.name,
            color: ["#475569", "#b45309", "#6d28d9"][i % 3],
            bg: ["#f1f5f9", "#fef9c3", "#ede9fe"][i % 3],
            price: money(p.price) + "/" + p.duration_days + " ngày",
            perks: [
                "Giảm " + p.discount_percent + "% phí sân",
                "Áp dụng tại các chi nhánh",
                "Theo dõi thời hạn trên tài khoản",
            ],
        })),
    ];
    PRICING = [
        {
            label: "Giờ thường",
            time: "03:00 – 16:00",
            price:
                "Từ " +
                money(Math.min(...BRANCHES.map((b) => b.price))) +
                "/giờ",
            note: "Thứ 2 – Thứ 6",
            badge: "off-peak",
        },
        {
            label: "Giờ cao điểm",
            time: "16:00 – 23:00",
            price: "Theo chi nhánh",
            note: "Giá hiển thị khi chọn sân",
            badge: "peak",
        },
        {
            label: "Cuối tuần",
            time: "03:00 – 23:00",
            price: "Theo chi nhánh",
            note: "Thứ 7 & Chủ nhật",
            badge: "weekend",
        },
        {
            label: "Đặt cố định",
            time: "Hàng tuần",
            price: "Giảm 15%",
            note: "Từ 2 – 12 tuần",
            badge: "fixed",
        },
    ];
    COMMUNITY_POSTS = posts.map((p) => ({
        ...p,
        author: p.user?.name,
        branch: p.branch?.name,
        time: stamp(p.starts_at),
        tags: [p.skill_level],
    }));
    STATS = data.stats ?? STATS;
}
