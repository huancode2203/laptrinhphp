import PosCheckout from "./PosCheckout";
import ManagedCourts from "./ManagedCourts";
import CourtMatrix from "./CourtMatrix";
import QuickBooking from "./QuickBooking";
import AdvancedManagement from "./AdvancedManagement";
import CalendarBoard from "./CalendarBoard";
const ReportsView = lazy(() => import("./ReportsView"));
const QRScanner = lazy(() => import("./QRScanner"));
import Icon, { type IconName } from "./Icon";
import { lazy, Suspense, useEffect, useRef, useState } from "react";
import { api, key, useHub } from "./hub";
import {
    BRANCHES,
    day,
    money,
    stamp,
    localDateTime,
    statusText,
    type Row,
} from "./data";
import {
    Card,
    cell,
    Confirm,
    Editor,
    Empty,
    Modal,
    Table,
    type Field,
} from "./ui";
import { Logo } from "./Brand";
import { EventDetail, eventTypes } from "./EventsPage";
import { BookingDetail } from "./Account";
export default function Management({ book }: { book: (initial: Row) => void }) {
    const { data, run, toast, refresh, loading } = useHub();
    const user = data!.user,
        admin = user.role === "admin";
    const allows = (p: string) => admin || !!data!.permissions?.[p];
    const writePermissions: Record<string, string> = {
        courts: "courts.write",
        services: "catalog.write",
        products: "catalog.write",
        events: "events.write",
    };
    const tabPermissions: Record<string, string> = {
        dashboard: "bookings.read",
        matrix: "courts.read",
        bookings: "bookings.read",
        calendar: "bookings.read",
        pos: "pos.read",
        customers: "customers.read",
        courts: "courts.read",
        services: "catalog.write",
        products: "catalog.write",
        maintenance: "courts.write",
        events: "events.write",
        reports: "reports.read",
        advanced: "",
    };
    const nav = [
        ["dashboard", "📊", "Tổng quan"],
        ["matrix", "🗓", "Ma trận sân"],
        ["bookings", "📋", "Quản lý lịch"],
        ["calendar", "◷", "Lịch tuần / tháng"],
        ["courts", "🏸", "Sân & bảng giá"],
        ["maintenance", "🔧", "Bảo trì"],
        ["pos", "🛒", "Bán hàng / POS"],
        ["customers", "👥", "Khách hàng"],
        ["services", "🎾", "Dịch vụ"],
        ["products", "📦", "Kho hàng"],
        ["events", "🏆", "Sự kiện"],
        ["reports", "📈", "Báo cáo"],
        ["advanced", "✦", "Vận hành & nội dung"],
        ...(admin
            ? [
                  ["branches", "📍", "Chi nhánh"],
                  ["users", "🔑", "Tài khoản & quyền"],
                  ["plans", "⭐", "Gói hội viên"],
                  ["coupons", "🎁", "Mã ưu đãi"],
                  ["moderation", "🛡", "Kiểm duyệt"],
                  ["audit", "📜", "Nhật ký"],
              ]
            : []),
    ].filter(
        ([id]) =>
            admin ||
            !tabPermissions[id] ||
            data!.permissions?.[tabPermissions[id]],
    );
    const [savedView] = useState(() => {
        try {
            return JSON.parse(
                localStorage.getItem("hub:manage:" + user.id) ?? "{}",
            );
        } catch {
            return {};
        }
    });
    const routeTab = location.hash.split("/")[1]?.split("?")[0];
    const [tab, setTab] = useState(
            nav.find(([id]) => id === (routeTab || savedView.tab))?.[0] ??
                nav[0]?.[0] ??
                "advanced",
        ),
        [branch, setBranch] = useState(
            data!.managed_branch_ids?.includes(savedView.branch)
                ? savedView.branch
                : (user.branch_id ??
                      data!.managed_branch_ids?.[0] ??
                      BRANCHES[0]?.id),
        ),
        [date, setDate] = useState(
            /^\d{4}-\d{2}-\d{2}$/.test(savedView.date ?? "")
                ? savedView.date
                : day(),
        ),
        [hub, setHub] = useState<Row | null>(null),
        [adminData, setAdmin] = useState<Row | null>(null),
        [report, setReport] = useState<Row | null>(null),
        [schedule, setSchedule] = useState<Row | null>(null),
        [from, setFrom] = useState(day(-30)),
        [to, setTo] = useState(day()),
        [search, setSearch] = useState(""),
        [customers, setCustomers] = useState<Row[]>([]),
        [selected, setSelected] = useState<Row | null>(null),
        [event, setEvent] = useState(0),
        [editing, setEditing] = useState<Row | null>(null),
        [cart, setCart] = useState<Row>({}),
        [category, setCategory] = useState(""),
        [allReports, setAllReports] = useState(admin),
        [sale, setSale] = useState<Row | null>(null),
        [found, setFound] = useState<Row | null>(null),
        [quick, setQuick] = useState<Row | null>(null),
        [confirm, setConfirm] = useState<Row | null>(null),
        [navSearch, setNavSearch] = useState(""),
        [loadError, setLoadError] = useState(""),
        [fetching, setFetching] = useState(false);
    const generation = useRef(0);
    const loadedScope = useRef("");
    useEffect(() => {
        try {
            localStorage.setItem(
                "hub:manage:" + user.id,
                JSON.stringify({ tab, branch, date }),
            );
        } catch {}
        const hash = "#manage/" + tab;
        if (location.hash.split("/")[1]?.split("?")[0] !== tab)
            location.hash = hash;
    }, [tab, branch, date]);
    useEffect(() => {
        const follow = () => {
            const target = location.hash.split("/")[1]?.split("?")[0];
            if (
                location.hash.startsWith("#manage/") &&
                nav.some(([id]) => id === target)
            )
                setTab(target);
        };
        window.addEventListener("hashchange", follow);
        return () => window.removeEventListener("hashchange", follow);
    }, []);
    const query = "branch_id=" + branch;
    useEffect(() => {
        setCart({});
        setCategory("");
    }, [branch]);
    async function load() {
        const request = ++generation.current;
        setFetching(true);
        const requests = [
            api("manage/hub?" + query + "&date=" + date),
            data!.permissions?.["reports.read"]
                ? api("manage/reports?" + query + "&from=" + from + "&to=" + to)
                : Promise.resolve({ data: {} }),
            allows("courts.read") || allows("bookings.read")
                ? api("branches/" + branch + "/schedule?date=" + date)
                : Promise.resolve({ data: {} }),
            ...(admin ? [api("admin/hub")] : []),
        ];
        const values = await Promise.allSettled(requests);
        if (generation.current !== request) return;
        setFetching(false);
        const rejected = values.find((v) => v.status === "rejected");
        setLoadError(
            rejected?.status === "rejected" ? rejected.reason.message : "",
        );
        if (values[0].status === "fulfilled") setHub(values[0].value.data);
        if (values[1].status === "fulfilled") setReport(values[1].value.data);
        if (values[2].status === "fulfilled") setSchedule(values[2].value.data);
        if (admin && values[3]?.status === "fulfilled")
            setAdmin(values[3].value.data);
    }
    useEffect(() => {
        if (loadedScope.current !== branch + ":" + date) {
            loadedScope.current = branch + ":" + date;
            setHub(null);
            setSchedule(null);
            setSelected(null);
            setQuick(null);
            setEditing(null);
            setFound(null);
        }
        load().catch((e) => toast(e.message));
    }, [branch, date, from, to]);
    useEffect(() => {
        if (!allows("customers.read")) return;
        let live = true;
        const t = setTimeout(
            () =>
                api(
                    "manage/customers?" +
                        new URLSearchParams({
                            branch_id: String(branch),
                            search,
                        }),
                )
                    .then((r) => {
                        if (live) setCustomers(r.data);
                    })
                    .catch((e) => {
                        if (live) toast(e.message);
                    }),
            250,
        );
        return () => {
            live = false;
            clearTimeout(t);
        };
    }, [search, branch, hub?.bookings?.length]);
    async function changed(fn: () => Promise<any>) {
        await fn();
        await load();
        await refresh();
    }
    function edit(kind: string, row: Row = {}) {
        setEditing({ kind, row });
    }
    const opts = (pairs: string[]) =>
        pairs.map((value) => ({ value, label: value }));
    const branchField: Field = {
        name: "branch_id",
        label: "Chi nhánh",
        required: true,
        options: BRANCHES.filter(
            (b) => admin || data!.managed_branch_ids?.includes(b.id),
        ).map((b) => ({ value: b.id, label: b.rawName })),
    };
    const num = (
        name: string,
        label: string,
        min = 0,
        max = 10000000,
    ): Field => ({ name, label, type: "number", required: true, min, max });
    const text = (name: string, label: string): Field => ({
        name,
        label,
        required: true,
    });
    const dt = (name: string, label: string): Field => ({
        name,
        label,
        type: "datetime-local",
        required: true,
    });
    const fields: Record<string, Field[]> = {
        courts: [
            branchField,
            text("name", "Tên sân"),
            {
                name: "status",
                label: "Trạng thái",
                required: true,
                options: opts(["active", "maintenance", "locked"]),
            },
            num("hourly_rate", "Giá thường / giờ", 1000),
            num("peak_rate", "Giá cao điểm / giờ", 1000),
            num("weekend_rate", "Giá cuối tuần / giờ", 1000),
        ],
        services: [
            branchField,
            text("name", "Tên dịch vụ"),
            text("unit", "Đơn vị"),
            num("price", "Giá"),
            { name: "active", label: "Đang mở", type: "checkbox" },
        ],
        products: [
            branchField,
            text("name", "Tên sản phẩm"),
            text("sku", "Mã SKU"),
            text("category", "Nhóm hàng"),
            num("price", "Giá bán"),
            { name: "icon", label: "Biểu tượng" },
            ...(!editing?.row.id
                ? [num("stock", "Tồn ban đầu", 0, 1000000)]
                : []),
            { name: "active", label: "Đang bán", type: "checkbox" },
        ],
        stock: [
            num("delta", "Thay đổi tồn (+ nhập / − xuất)", -1000000, 1000000),
            text("reason", "Lý do"),
        ],
        maintenance: [
            {
                name: "court_id",
                label: "Sân",
                required: true,
                options: hub?.courts.map((c: Row) => ({
                    value: c.id,
                    label: c.name,
                })),
            },
            dt("starts_at", "Bắt đầu"),
            dt("ends_at", "Kết thúc"),
            text("reason", "Lý do"),
        ],
        branches: [
            text("name", "Tên chi nhánh"),
            text("district", "Phường / xã"),
            text("address", "Địa chỉ"),
            { name: "phone", label: "Điện thoại" },
            {
                name: "amenities_text",
                label: "Tiện ích, ngăn bằng dấu phẩy",
                help: "Parking, Shower, Cafe, WiFi, Locker, Shop",
            },
            {
                name: "opens_time",
                label: "Giờ mở cửa",
                type: "time",
                required: true,
            },
            {
                name: "closes_time",
                label: "Giờ đóng cửa",
                type: "time",
                required: true,
            },
            ...(!editing?.row.id
                ? [
                      num("court_count", "Số sân", 1, 40),
                      num("hourly_rate", "Giá thường / giờ", 1000),
                      num("peak_rate", "Giá cao điểm / giờ", 1000),
                      num("weekend_rate", "Giá cuối tuần / giờ", 1000),
                  ]
                : []),
            {
                name: "manager_id",
                label: "Giao quản lý",
                options: [
                    { value: "", label: "Chọn sau" },
                    ...(adminData?.users ?? [])
                        .filter((u: Row) => u.role === "manager" && u.active)
                        .map((u: Row) => ({ value: u.id, label: u.name })),
                ],
            },
            { name: "active", label: "Đang hoạt động", type: "checkbox" },
        ],
        users: [
            ...(!editing?.row.id
                ? [
                      text("name", "Họ tên"),
                      {
                          name: "email",
                          label: "Email",
                          type: "email",
                          required: true,
                      },
                      { name: "phone", label: "Điện thoại" },
                      {
                          name: "password",
                          label: "Mật khẩu ban đầu",
                          type: "password",
                          required: true,
                      },
                      {
                          name: "password_confirmation",
                          label: "Nhập lại mật khẩu",
                          type: "password",
                          required: true,
                      },
                  ]
                : []),
            {
                name: "role",
                label: "Vai trò",
                required: true,
                options: opts([
                    "customer",
                    "staff",
                    "cashier",
                    "manager",
                    "admin",
                ]),
            },
            { ...branchField, required: false },
        ],
        plans: [
            text("name", "Tên gói"),
            num("price", "Giá gói"),
            num("discount_percent", "Giảm phí sân (%)", 0, 50),
            num("duration_days", "Số ngày", 1, 365),
            { name: "active", label: "Đang bán", type: "checkbox" },
            num("early_days", "Số ngày được đặt sớm", 0, 90),
            num(
                "points_threshold",
                "Điểm để nhận hạng (0: gói mua)",
                0,
                1000000,
            ),
        ],
        coupons: [
            text("code", "Mã ưu đãi"),
            { ...branchField, required: false },
            {
                name: "kind",
                label: "Loại",
                required: true,
                options: [
                    { value: "percent", label: "Phần trăm" },
                    { value: "fixed", label: "Số tiền" },
                ],
            },
            num("value", "Mức giảm"),
            num("minimum_amount", "Phí sân tối thiểu"),
            {
                name: "max_discount",
                label: "Giảm tối đa",
                type: "number",
                min: 1,
            },
            num("usage_limit", "Số lần dùng tối đa", 1, 1000000),
            dt("starts_at", "Có hiệu lực"),
            dt("expires_at", "Hết hạn"),
            { name: "active", label: "Đang áp dụng", type: "checkbox" },
        ],
        events: [
            branchField,
            text("title", "Tên sự kiện"),
            {
                name: "type",
                label: "Loại",
                required: true,
                options: Object.entries(eventTypes).map(([value, label]) => ({
                    value,
                    label,
                })),
            },
            {
                name: "description",
                label: "Mô tả",
                type: "textarea",
                required: true,
            },
            {
                name: "format",
                label: "Thể thức",
                required: true,
                options: [
                    { value: "knockout", label: "Loại trực tiếp" },
                    { value: "round_robin", label: "Vòng tròn" },
                    { value: "swiss", label: "Thụy Sĩ" },
                    { value: "Giao lưu", label: "Giao lưu" },
                ],
            },
            text("skill_level", "Trình độ"),
            dt("starts_at", "Bắt đầu"),
            dt("ends_at", "Kết thúc"),
            num("capacity", "Số người", 2, 500),
            num("fee", "Phí / người"),
            text("court_ids_text", "Mã sân, ngăn bằng dấu phẩy"),
        ],
        topup: [
            num("amount", "Số tiền đã thu", 1000, 50000000),
            {
                name: "method",
                label: "Phương thức",
                required: true,
                options: opts(["cash", "bank"]),
            },
        ],
        membership: [
            {
                name: "plan_id",
                label: "Gói hội viên",
                required: true,
                options: data!.plans.map((p: Row) => ({
                    value: p.id,
                    label: p.name + " · " + money(p.price),
                })),
            },
            {
                name: "method",
                label: "Phương thức đã thu",
                required: true,
                options: opts(["cash", "bank"]),
            },
        ],
        payment: [
            num("amount", "Số tiền"),
            {
                name: "method",
                label: "Phương thức đã thu / hoàn",
                required: true,
                options: opts(["cash", "bank"]),
            },
            {
                name: "kind",
                label: "Loại giao dịch",
                required: true,
                options: [
                    { value: "payment", label: "Thu tiền" },
                    { value: "refund", label: "Hoàn tiền lịch đã hủy" },
                ],
            },
            { name: "notes", label: "Ghi chú" },
        ],
        quickcustomer: [
            branchField,
            text("name", "Tên khách"),
            text("phone", "Điện thoại"),
            { name: "email", label: "Email (nếu có)", type: "email" },
        ],
        checkin: [text("token", "Nội dung QR check-in (quét hoặc dán mã)")],
        sale: [
            {
                name: "method",
                label: "Phương thức đã thu",
                required: true,
                options: opts(["cash", "bank", "wallet"]),
            },
            {
                name: "user_id",
                label: "Mã khách hàng (cần khi dùng ví)",
                type: "number",
                min: 1,
            },
        ],
    };
    async function save(d: Row) {
        const { kind, row } = editing!;
        d = { ...d };
        for (const k of ["branch_id", "user_id", "plan_id", "court_id"])
            if (d[k] !== undefined) d[k] = d[k] === "" ? null : Number(d[k]);
        let path = "manage/" + kind + (row.id ? "/" + row.id : ""),
            method = row.id ? "PUT" : "POST";
        if (["branches", "plans", "coupons"].includes(kind))
            path = "admin/" + kind + (row.id ? "/" + row.id : "");
        if (kind === "branches") {
            for (const [source, target] of [
                ["opens_time", "opens_minute"],
                ["closes_time", "closes_minute"],
            ]) {
                const [h, m] = d[source].split(":").map(Number);
                d[target] = h * 60 + m;
                delete d[source];
            }
            d.manager_id = d.manager_id ? Number(d.manager_id) : null;
            d.amenities = (d.amenities_text ?? "")
                .split(",")
                .map((s: string) => s.trim())
                .filter(Boolean);
            delete d.amenities_text;
        }
        if (kind === "products" && row.id) delete d.stock;
        if (kind === "users") {
            path = row.id ? "admin/users/" + row.id + "/role" : "admin/users";
            method = row.id ? "PATCH" : "POST";
        }
        if (kind === "maintenance") path = "manage/maintenances";
        if (kind === "stock") {
            path = "manage/products/" + row.id + "/stock";
            method = "POST";
            d.request_key ??= key();
        }
        if (kind === "events") {
            d.court_ids = (d.court_ids_text ?? "")
                .split(",")
                .map(Number)
                .filter((n: number) => n > 0);
            delete d.court_ids_text;
        }
        if (kind === "topup" || kind === "membership") {
            path = "manage/users/" + row.id + "/" + kind;
            method = "POST";
            d.branch_id = branch;
            d.request_key ??= key();
        }
        if (kind === "payment") {
            path = "manage/bookings/" + row.id + "/payments";
            method = "POST";
            d.request_key ??= key();
        }
        if (kind === "quickcustomer") {
            path = "manage/customers";
            method = "POST";
            d.branch_id = Number(d.branch_id);
            d.email = d.email || null;
        }
        if (kind === "checkin") {
            path = "manage/checkin";
            method = "POST";
        }
        if (kind === "sale") {
            path = "manage/sales";
            method = "POST";
            d.branch_id = branch;
            d.request_key ??= key();
            d.items = Object.entries(cart)
                .filter(([, n]) => n > 0)
                .map(([id, quantity]) => ({
                    product_id: Number(id),
                    quantity: Number(quantity),
                }));
        }
        const r = await api(path, method, d);
        if (kind === "sale") {
            setSale(r.data);
            setCart({});
        }
        await load();
        await refresh();
        toast(
            kind === "sale"
                ? d.method === "booking"
                    ? "Đã thêm hàng vào hóa đơn sân. Bạn có thể thu tiền trong chi tiết lịch."
                    : "Đã ghi nhận thanh toán giỏ hàng."
                : "Đã lưu thay đổi.",
        );
    }
    const rows =
        tab === "courts"
            ? hub?.courts
            : tab === "services"
              ? hub?.services
              : tab === "products"
                ? hub?.products
                : tab === "branches"
                  ? adminData?.branches
                  : tab === "users"
                    ? adminData?.users
                    : tab === "plans"
                      ? adminData?.plans
                      : tab === "coupons"
                        ? adminData?.coupons
                        : tab === "events"
                          ? hub?.events
                          : [];
    const fieldsFor = fields[tab];
    const invalidCart = Object.entries(cart).some(
        ([id, n]) =>
            n !== "" &&
            (!Number.isInteger(Number(n)) ||
                Number(n) < 0 ||
                Number(n) >
                    Math.min(
                        100,
                        hub?.products.find((p: Row) => p.id === Number(id))
                            ?.stock ?? 0,
                    )),
    );
    const sectionFor = (id: string) =>
        [
            "dashboard",
            "matrix",
            "bookings",
            "calendar",
            "courts",
            "maintenance",
        ].includes(id)
            ? "LỊCH & SÂN"
            : [
                    "pos",
                    "customers",
                    "services",
                    "products",
                    "events",
                    "reports",
                ].includes(id)
              ? "KINH DOANH"
              : "QUẢN TRỊ";
    const visibleNav = nav.filter(([, , label]) =>
        label
            .toLocaleLowerCase("vi")
            .includes(navSearch.toLocaleLowerCase("vi")),
    );
    return (
        <div className="management-shell flex min-h-[calc(100vh-80px)]">
            <aside className="management-sidebar hidden w-60 shrink-0 flex-col md:flex">
                <Logo />
                <p className="mb-5 mt-2 text-xs text-white/40">
                    {admin ? "QUẢN TRỊ HỆ THỐNG" : "QUẢN LÝ CHI NHÁNH"}
                </p>
                <input
                    aria-label="Tìm mục quản lý"
                    className="management-nav-search"
                    placeholder="Tìm chức năng…"
                    value={navSearch}
                    onChange={(e) => setNavSearch(e.target.value)}
                />
                <nav aria-label="Chức năng quản lý" className="management-nav">
                    {visibleNav.map(([id, , label], index) => (
                        <div key={id}>
                            {(index === 0 ||
                                sectionFor(id) !==
                                    sectionFor(visibleNav[index - 1][0])) && (
                                <p className="management-nav-section">
                                    {sectionFor(id)}
                                </p>
                            )}
                            <button
                                className={
                                    "sidebar-link mb-1 text-left " +
                                    (tab === id ? "active" : "")
                                }
                                aria-current={tab === id ? "page" : undefined}
                                onClick={() => setTab(id)}
                            >
                                <Icon
                                    name={
                                        (
                                            {
                                                dashboard: "grid",
                                                matrix: "court",
                                                bookings: "calendar",
                                                pos: "bag",
                                                customers: "people",
                                                courts: "racket",
                                                services: "spark",
                                                products: "bag",
                                                maintenance: "settings",
                                                events: "trophy",
                                                reports: "chart",
                                                calendar: "calendar",
                                                advanced: "settings",
                                                branches: "pin",
                                                users: "user",
                                                plans: "star",
                                                coupons: "gift",
                                                moderation: "shield",
                                                audit: "calendar",
                                            } as Record<string, IconName>
                                        )[id] ?? "grid"
                                    }
                                    size={18}
                                />
                                {label}
                            </button>
                        </div>
                    ))}
                </nav>
                <p className="mt-auto pt-6 text-xs text-white/50">
                    {user.name} · {statusText(user.role)}
                </p>
            </aside>
            <main className="min-w-0 flex-1 p-4 md:p-8">
                <select
                    aria-label="Trang quản lý"
                    className="input mb-4 w-full md:hidden"
                    value={tab}
                    onChange={(e) => setTab(e.target.value)}
                >
                    {nav.map(([id, , label]) => (
                        <option key={id} value={id}>
                            {label}
                        </option>
                    ))}
                </select>
                <div className="management-toolbar mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="eyebrow">
                            {admin && tab === "dashboard"
                                ? "Toàn hệ thống"
                                : BRANCHES.find((b) => b.id === branch)
                                      ?.rawName}
                        </p>
                        <h1 className="font-display text-3xl font-bold">
                            {nav.find((n) => n[0] === tab)?.[2]}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(data!.managed_branch_ids?.length ?? 0) > 1 && (
                            <select
                                aria-label="Chi nhánh quản lý"
                                className="input"
                                value={branch}
                                onChange={(e) => setBranch(+e.target.value)}
                            >
                                {BRANCHES.filter((b) =>
                                    data!.managed_branch_ids.includes(b.id),
                                ).map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <input
                            aria-label="Ngày quản lý"
                            className="input"
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                        {allows("bookings.write") && (
                            <button
                                className="btn"
                                disabled={fetching}
                                onClick={() =>
                                    book({ branch_id: branch, date })
                                }
                            >
                                + Đặt sân
                            </button>
                        )}
                        <button
                            className="btn-outline"
                            onClick={() => run(load)}
                        >
                            ↻ Làm mới
                        </button>
                    </div>
                </div>
                {loadError && (
                    <div className="management-error" role="alert">
                        <span>{loadError}</span>
                        <button
                            className="btn-outline"
                            onClick={() => run(load)}
                        >
                            Tải lại dữ liệu
                        </button>
                    </div>
                )}
                {fetching && hub && (
                    <p role="status" className="mb-3 text-sm text-[#6d8474]">
                        Đang cập nhật dữ liệu…
                    </p>
                )}
                {!hub ? (
                    <Empty text="Đang tải dữ liệu…" />
                ) : (
                    <>
                        {tab === "advanced" && (
                            <AdvancedManagement
                                branch={branch}
                                courts={hub.courts}
                            />
                        )}
                        {tab === "calendar" && (
                            <CalendarBoard
                                branch={branch}
                                courts={hub.courts}
                            />
                        )}
                        {tab === "dashboard" && admin && (
                            <>
                                <div className="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                                    {[
                                        ["Chi nhánh", BRANCHES.length],
                                        [
                                            "Sân toàn hệ thống",
                                            data!.branches.reduce(
                                                (n: number, b: Row) =>
                                                    n + b.courts.length,
                                                0,
                                            ),
                                        ],
                                        [
                                            "Tài khoản",
                                            adminData?.stats?.users ?? 0,
                                        ],
                                        [
                                            "Bài chờ duyệt",
                                            adminData?.stats?.pending_posts ??
                                                0,
                                        ],
                                    ].map(([label, value]) => (
                                        <Card
                                            key={label}
                                            className={
                                                "metric-card " +
                                                (label === "Chi nhánh"
                                                    ? "metric-featured"
                                                    : "")
                                            }
                                        >
                                            <span className="metric-icon">
                                                <Icon
                                                    name={
                                                        label === "Chi nhánh"
                                                            ? "pin"
                                                            : label ===
                                                                "Sân toàn hệ thống"
                                                              ? "court"
                                                              : label ===
                                                                  "Tài khoản"
                                                                ? "people"
                                                                : "shield"
                                                    }
                                                    size={19}
                                                />
                                            </span>
                                            <p className="text-sm text-slate-500">
                                                {label}
                                            </p>
                                            <b className="mt-3 block text-3xl">
                                                {value}
                                            </b>
                                        </Card>
                                    ))}
                                </div>
                                <Suspense
                                    fallback={
                                        <Empty text="Đang tải tổng quan hệ thống…" />
                                    }
                                >
                                    <ReportsView branch={null} />
                                </Suspense>
                            </>
                        )}
                        {tab === "dashboard" && !admin && (
                            <>
                                <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                                    {[
                                        [
                                            "Doanh thu ròng",
                                            money(report?.net_revenue),
                                        ],
                                        ["Lịch hôm nay", hub.bookings.length],
                                        [
                                            "Đang chơi",
                                            hub.bookings.filter(
                                                (b: Row) =>
                                                    b.status === "checked_in" &&
                                                    !b.needs_checkout,
                                            ).length,
                                        ],
                                        [
                                            "Chờ chốt hóa đơn",
                                            hub.bookings.filter(
                                                (b: Row) => b.needs_checkout,
                                            ).length,
                                        ],
                                        [
                                            "Tồn kho thấp",
                                            hub.products.filter(
                                                (p: Row) => p.stock < 5,
                                            ).length,
                                        ],
                                    ].map(([l, v]) => (
                                        <Card
                                            key={l}
                                            className={
                                                "metric-card " +
                                                (l === "Doanh thu ròng"
                                                    ? "metric-featured"
                                                    : "")
                                            }
                                        >
                                            <span className="metric-icon">
                                                <Icon
                                                    name={
                                                        l === "Doanh thu ròng"
                                                            ? "chart"
                                                            : l ===
                                                                "Lịch hôm nay"
                                                              ? "calendar"
                                                              : l ===
                                                                  "Đang chơi"
                                                                ? "racket"
                                                                : "bag"
                                                    }
                                                    size={19}
                                                />
                                            </span>
                                            <p className="text-sm text-[#6d8474]">
                                                {l}
                                            </p>
                                            <h2 className="mt-3 text-3xl font-bold">
                                                {v}
                                            </h2>
                                        </Card>
                                    ))}
                                </div>
                                <Card className="mt-5">
                                    <h2 className="mb-4 text-xl font-bold">
                                        Lịch sân hôm nay
                                    </h2>
                                    <p className="mb-4 text-sm text-[#6d8474]">
                                        Doanh thu theo khoảng {from} đến {to}.
                                        Chọn Báo cáo để đổi kỳ.
                                    </p>
                                    {hub.bookings.length > 0 && (
                                        <div className="dashboard-bookings">
                                            {hub.bookings
                                                .slice(0, 4)
                                                .map((b: Row) => (
                                                    <button
                                                        key={b.id}
                                                        onClick={() =>
                                                            setTab("bookings")
                                                        }
                                                        className="dashboard-booking"
                                                    >
                                                        <span className="dashboard-booking-icon">
                                                            <Icon
                                                                name="court"
                                                                size={20}
                                                            />
                                                        </span>
                                                        <span>
                                                            <strong>
                                                                {b.court
                                                                    ?.name ??
                                                                    "Lịch sân"}
                                                                <span>
                                                                    {" "}
                                                                    ·{" "}
                                                                    {b.user
                                                                        ?.name ??
                                                                        b.reference}
                                                                </span>
                                                            </strong>
                                                            <small>
                                                                {new Date(
                                                                    b.starts_at,
                                                                ).toLocaleTimeString(
                                                                    "vi-VN",
                                                                    {
                                                                        timeZone:
                                                                            "Asia/Ho_Chi_Minh",
                                                                        hour: "2-digit",
                                                                        minute: "2-digit",
                                                                    },
                                                                )}{" "}
                                                                –{" "}
                                                                {new Date(
                                                                    b.ends_at,
                                                                ).toLocaleTimeString(
                                                                    "vi-VN",
                                                                    {
                                                                        timeZone:
                                                                            "Asia/Ho_Chi_Minh",
                                                                        hour: "2-digit",
                                                                        minute: "2-digit",
                                                                    },
                                                                )}
                                                            </small>
                                                        </span>
                                                        <span className="badge badge-green">
                                                            {statusText(
                                                                b.status,
                                                            )}
                                                        </span>
                                                        <Icon
                                                            name="northeast"
                                                            size={17}
                                                        />
                                                    </button>
                                                ))}
                                        </div>
                                    )}
                                    <div className="flex flex-wrap gap-3">
                                        <button
                                            className="btn"
                                            onClick={() => setTab("bookings")}
                                        >
                                            Xem lịch
                                        </button>
                                        <button
                                            className="btn-outline"
                                            onClick={() =>
                                                book({
                                                    branch_id: branch,
                                                    date,
                                                })
                                            }
                                        >
                                            + Đặt tại quầy
                                        </button>
                                        <button
                                            className="btn-outline"
                                            onClick={() => edit("checkin")}
                                        >
                                            Nhận sân bằng QR
                                        </button>
                                    </div>
                                </Card>
                            </>
                        )}
                        {tab === "matrix" && (
                            <CourtMatrix
                                schedule={schedule}
                                onReserved={(s) =>
                                    run(async () => {
                                        const r = await api(
                                            "bookings/" + s.booking_id,
                                        );
                                        setSelected(r.data);
                                    })
                                }
                                onPick={(r) => {
                                    if (allows("bookings.write")) setQuick(r);
                                    else toast("Bạn chưa có quyền tạo lịch.");
                                }}
                            />
                        )}
                        {tab === "bookings" && (
                            <>
                                <div className="mb-4">
                                    <Suspense
                                        fallback={
                                            <Empty text="Đang tải máy quét…" />
                                        }
                                    >
                                        <QRScanner
                                            found={(token) =>
                                                run(async () => {
                                                    await api(
                                                        "manage/checkin",
                                                        "POST",
                                                        { token },
                                                    );
                                                    await load();
                                                    toast("Đã nhận sân.");
                                                })
                                            }
                                        />
                                    </Suspense>
                                </div>
                                <div className="mb-4 flex flex-wrap gap-2">
                                    <button
                                        className="btn"
                                        onClick={() =>
                                            book({ branch_id: branch, date })
                                        }
                                    >
                                        + Đặt sân tại quầy
                                    </button>
                                    <button
                                        className="btn-outline"
                                        onClick={() => edit("checkin")}
                                    >
                                        QR check-in
                                    </button>
                                    <input
                                        aria-label="Mã lịch đặt"
                                        className="input"
                                        placeholder="Tìm mã booking"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                    />
                                    <button
                                        className="btn-outline"
                                        onClick={() =>
                                            run(async () =>
                                                setFound(
                                                    (
                                                        await api(
                                                            "manage/booking-search?" +
                                                                query +
                                                                "&reference=" +
                                                                encodeURIComponent(
                                                                    search,
                                                                ),
                                                        )
                                                    ).data,
                                                ),
                                            )
                                        }
                                    >
                                        Tìm
                                    </button>
                                    {found && (
                                        <button
                                            className="btn-outline"
                                            onClick={() => setSelected(found)}
                                        >
                                            {found.reference} →
                                        </button>
                                    )}
                                </div>
                                <Table
                                    headers={[
                                        "Mã / Khách",
                                        "Sân / Giờ",
                                        "Tổng / Đã thu",
                                        "Trạng thái",
                                        "Thao tác",
                                    ]}
                                >
                                    {hub.bookings.map((b: Row) => (
                                        <tr key={b.id}>
                                            <td className={cell}>
                                                <button
                                                    className="font-bold text-[#16a34a]"
                                                    onClick={() =>
                                                        setSelected(b)
                                                    }
                                                >
                                                    {b.reference}
                                                </button>
                                                <p className="text-xs">
                                                    {b.customer?.name} ·{" "}
                                                    {b.customer?.phone}
                                                </p>
                                            </td>
                                            <td className={cell}>
                                                {b.court.name}
                                                <p className="text-xs">
                                                    {stamp(b.starts_at)}
                                                </p>
                                            </td>
                                            <td className={cell}>
                                                {money(b.total_amount)}
                                                <p className="text-xs text-[#6d8474]">
                                                    {money(b.paid_amount)}
                                                </p>
                                            </td>
                                            <td className={cell}>
                                                {b.needs_checkout
                                                    ? "Chờ chốt hóa đơn"
                                                    : statusText(b.status)}
                                                {b.refund_pending
                                                    ? " · Chờ hoàn"
                                                    : ""}
                                            </td>
                                            <td className={cell}>
                                                <div className="flex flex-wrap gap-2">
                                                    {allows(
                                                        "payments.write",
                                                    ) && (
                                                        <button
                                                            className="btn-outline text-xs"
                                                            onClick={() =>
                                                                edit(
                                                                    "payment",
                                                                    {
                                                                        ...b,
                                                                        amount:
                                                                            b.status ===
                                                                            "cancelled"
                                                                                ? b.paid_amount
                                                                                : b.remaining_amount,
                                                                        method: "cash",
                                                                        kind:
                                                                            b.status ===
                                                                            "cancelled"
                                                                                ? "refund"
                                                                                : "payment",
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {b.status ===
                                                            "cancelled"
                                                                ? "Hoàn"
                                                                : "Thu tiền"}
                                                        </button>
                                                    )}
                                                    {[
                                                        "confirmed",
                                                        "checked_in",
                                                    ].includes(b.status) && (
                                                        <button
                                                            className="btn-outline text-xs"
                                                            onClick={() =>
                                                                setConfirm({
                                                                    title:
                                                                        b.status ===
                                                                        "confirmed"
                                                                            ? "Nhận sân"
                                                                            : "Hoàn tất buổi chơi",
                                                                    path:
                                                                        "bookings/" +
                                                                        b.id +
                                                                        "/status",
                                                                    body: {
                                                                        status:
                                                                            b.status ===
                                                                            "confirmed"
                                                                                ? "checked_in"
                                                                                : "completed",
                                                                    },
                                                                })
                                                            }
                                                        >
                                                            {b.status ===
                                                            "confirmed"
                                                                ? "Check-in"
                                                                : "Hoàn tất"}
                                                        </button>
                                                    )}
                                                    {b.status ===
                                                        "confirmed" && (
                                                        <button
                                                            className="text-xs text-red-600"
                                                            onClick={() =>
                                                                setConfirm({
                                                                    title:
                                                                        "Hủy lịch " +
                                                                        b.reference,
                                                                    path:
                                                                        "bookings/" +
                                                                        b.id +
                                                                        "/status",
                                                                    body: {
                                                                        status: "cancelled",
                                                                    },
                                                                })
                                                            }
                                                        >
                                                            Hủy
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </>
                        )}
                        {tab === "courts" && (
                            <ManagedCourts
                                branch={branch}
                                writable={allows("courts.write")}
                            />
                        )}
                        {fieldsFor && rows && tab !== "courts" && (
                            <>
                                {(admin ||
                                    allows(writePermissions[tab] ?? "")) && (
                                    <button
                                        className="btn mb-4"
                                        onClick={() =>
                                            edit(tab, {
                                                branch_id: branch,
                                                role: "customer",
                                                active: true,
                                                status:
                                                    tab === "courts"
                                                        ? "active"
                                                        : undefined,
                                                price: 0,
                                                stock: 0,
                                                discount_percent: 0,
                                                duration_days: 30,
                                                minimum_amount: 0,
                                                usage_limit: 100,
                                                kind: "percent",
                                                type: "friendly",
                                                format: "Giao lưu",
                                                skill_level: "Mọi trình độ",
                                            })
                                        }
                                    >
                                        + Thêm mới
                                    </button>
                                )}
                                <Table
                                    headers={[
                                        "Mã",
                                        "Tên / Nội dung",
                                        "Chi tiết",
                                        "Trạng thái",
                                        "Thao tác",
                                    ]}
                                >
                                    {rows.map((r: Row) => (
                                        <tr key={r.id}>
                                            <td className={cell}>{r.id}</td>
                                            <td className={cell}>
                                                {r.name ?? r.title ?? r.code}
                                                <p className="text-xs text-[#6d8474]">
                                                    {r.email ??
                                                        r.address ??
                                                        r.sku}
                                                </p>
                                            </td>
                                            <td className={cell}>
                                                {tab === "courts"
                                                    ? money(r.hourly_rate) +
                                                      " / " +
                                                      money(r.peak_rate) +
                                                      " / " +
                                                      money(r.weekend_rate)
                                                    : tab === "users"
                                                      ? r.role +
                                                        " · CN " +
                                                        (r.branch_id ?? "—")
                                                      : tab === "products"
                                                        ? money(r.price) +
                                                          " · tồn " +
                                                          r.stock
                                                        : tab === "events"
                                                          ? stamp(r.starts_at)
                                                          : tab === "coupons"
                                                            ? r.kind +
                                                              " " +
                                                              r.value
                                                            : money(r.price)}
                                            </td>
                                            <td className={cell}>
                                                {r.status
                                                    ? statusText(r.status)
                                                    : r.active
                                                      ? "Đang mở"
                                                      : "Đã tắt"}
                                            </td>
                                            <td className={cell}>
                                                <div className="flex flex-wrap gap-2">
                                                    {(admin ||
                                                        allows(
                                                            writePermissions[
                                                                tab
                                                            ] ?? "",
                                                        )) && (
                                                        <button
                                                            className="btn-outline text-xs"
                                                            onClick={() =>
                                                                edit(tab, {
                                                                    ...r,
                                                                    court_ids_text:
                                                                        r.blocks
                                                                            ?.map(
                                                                                (
                                                                                    b: Row,
                                                                                ) =>
                                                                                    b.court_id,
                                                                            )
                                                                            .join(
                                                                                ", ",
                                                                            ),
                                                                    amenities_text:
                                                                        r.amenities?.join(
                                                                            ", ",
                                                                        ),
                                                                    starts_at:
                                                                        r.starts_at
                                                                            ? localDateTime(
                                                                                  r.starts_at,
                                                                              )
                                                                            : undefined,
                                                                    expires_at:
                                                                        r.expires_at
                                                                            ? localDateTime(
                                                                                  r.expires_at,
                                                                              )
                                                                            : undefined,
                                                                    ends_at:
                                                                        r.ends_at
                                                                            ? localDateTime(
                                                                                  r.ends_at,
                                                                              )
                                                                            : undefined,
                                                                })
                                                            }
                                                        >
                                                            {tab === "users"
                                                                ? "Phân quyền"
                                                                : "Sửa"}
                                                        </button>
                                                    )}
                                                    {tab === "products" && (
                                                        <button
                                                            className="btn-outline text-xs"
                                                            onClick={() =>
                                                                edit("stock", {
                                                                    id: r.id,
                                                                })
                                                            }
                                                        >
                                                            Nhập / xuất kho
                                                        </button>
                                                    )}
                                                    {tab === "events" && (
                                                        <button
                                                            className="btn-outline text-xs"
                                                            onClick={() =>
                                                                setEvent(r.id)
                                                            }
                                                        >
                                                            Vé / Nhánh đấu
                                                        </button>
                                                    )}
                                                    {tab === "users" &&
                                                        r.id !== user.id && (
                                                            <button
                                                                className="text-xs text-red-600"
                                                                onClick={() =>
                                                                    setConfirm({
                                                                        title: r.active
                                                                            ? "Khóa tài khoản"
                                                                            : "Mở tài khoản",
                                                                        path:
                                                                            "admin/users/" +
                                                                            r.id +
                                                                            "/active",
                                                                        body: {
                                                                            active: !r.active,
                                                                        },
                                                                    })
                                                                }
                                                            >
                                                                {r.active
                                                                    ? "Khóa"
                                                                    : "Mở"}
                                                            </button>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </>
                        )}
                        {tab === "maintenance" && (
                            <>
                                <button
                                    className="btn mb-4"
                                    onClick={() => edit("maintenance")}
                                >
                                    + Lịch bảo trì
                                </button>
                                <Table
                                    headers={[
                                        "Sân",
                                        "Thời gian",
                                        "Lý do",
                                        "Trạng thái",
                                        "Thao tác",
                                    ]}
                                >
                                    {hub.maintenances.map((m: Row) => (
                                        <tr key={m.id}>
                                            <td className={cell}>
                                                {m.court.name}
                                            </td>
                                            <td className={cell}>
                                                {stamp(m.starts_at)} →{" "}
                                                {stamp(m.ends_at)}
                                            </td>
                                            <td className={cell}>{m.reason}</td>
                                            <td className={cell}>
                                                {statusText(m.status)}
                                            </td>
                                            <td className={cell}>
                                                {m.status === "scheduled" &&
                                                    [
                                                        "completed",
                                                        "cancelled",
                                                    ].map((s) => (
                                                        <button
                                                            className="btn-outline mr-2 text-xs"
                                                            key={s}
                                                            onClick={() =>
                                                                setConfirm({
                                                                    title:
                                                                        s ===
                                                                        "completed"
                                                                            ? "Hoàn tất bảo trì"
                                                                            : "Hủy bảo trì",
                                                                    path:
                                                                        "manage/maintenances/" +
                                                                        m.id,
                                                                    body: {
                                                                        status: s,
                                                                    },
                                                                })
                                                            }
                                                        >
                                                            {s === "completed"
                                                                ? "Hoàn tất"
                                                                : "Hủy"}
                                                        </button>
                                                    ))}
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </>
                        )}
                        {tab === "customers" && (
                            <>
                                <button
                                    className="btn mb-4"
                                    onClick={() =>
                                        edit("quickcustomer", {
                                            branch_id: branch,
                                        })
                                    }
                                >
                                    + Thêm khách tại quầy
                                </button>
                                <input
                                    aria-label="Tìm khách hàng"
                                    className="input mb-4 w-full max-w-lg"
                                    placeholder="Tìm theo tên, email hoặc số điện thoại"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                                <Table
                                    headers={[
                                        "Mã",
                                        "Khách hàng",
                                        "Liên hệ",
                                        "Lịch đã đặt",
                                        "Đã chi tiêu",
                                        "Hội viên",
                                        "Thao tác",
                                    ]}
                                >
                                    {customers.map((c) => (
                                        <tr key={c.id}>
                                            <td className={cell}>{c.id}</td>
                                            <td className={cell}>{c.name}</td>
                                            <td className={cell}>
                                                {c.email}
                                                <p>{c.phone}</p>
                                            </td>
                                            <td className={cell}>
                                                <b>{c.visits}</b>
                                                <p className="text-xs text-slate-500">
                                                    {c.last_visit
                                                        ? stamp(c.last_visit)
                                                        : "Chưa có lịch"}
                                                </p>
                                            </td>
                                            <td className={cell}>
                                                {money(Number(c.spent ?? 0))}
                                            </td>
                                            <td className={cell}>
                                                {c.memberships?.[0]?.plan
                                                    ?.name ?? "Basic"}
                                            </td>
                                            <td className={cell}>
                                                <button
                                                    className="btn-outline mr-2 text-xs"
                                                    onClick={() =>
                                                        book({
                                                            branch_id: branch,
                                                            user_id: c.id,
                                                            date,
                                                        })
                                                    }
                                                >
                                                    Đặt sân
                                                </button>
                                                <button
                                                    className="btn-outline mr-2 text-xs"
                                                    onClick={() =>
                                                        edit("topup", {
                                                            id: c.id,
                                                            amount: 100000,
                                                            method: "cash",
                                                        })
                                                    }
                                                >
                                                    Nạp ví
                                                </button>
                                                <button
                                                    className="btn-outline text-xs"
                                                    onClick={() =>
                                                        edit("membership", {
                                                            id: c.id,
                                                            method: "cash",
                                                        })
                                                    }
                                                >
                                                    Mua hội viên
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </>
                        )}
                        {tab === "pos" && (
                            <>
                                <div className="mb-4 flex flex-wrap gap-2">
                                    {[
                                        "",
                                        ...Array.from(
                                            new Set(
                                                hub.products.map(
                                                    (p: Row) => p.category,
                                                ),
                                            ),
                                        ),
                                    ].map((c: any) => (
                                        <button
                                            key={c}
                                            className={
                                                category === c
                                                    ? "btn"
                                                    : "btn-outline"
                                            }
                                            onClick={() => setCategory(c)}
                                        >
                                            {c || "Tất cả sản phẩm"}
                                        </button>
                                    ))}
                                </div>
                                <div className="grid gap-5 xl:grid-cols-[1fr_320px]">
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {hub.products
                                            .filter(
                                                (p: Row) =>
                                                    p.active &&
                                                    (!category ||
                                                        p.category ===
                                                            category),
                                            )
                                            .map((p: Row) => (
                                                <Card key={p.id}>
                                                    <div className="text-3xl">
                                                        {p.icon}
                                                    </div>
                                                    <b className="mt-3 block">
                                                        {p.name}
                                                    </b>
                                                    <p className="my-3 text-sm">
                                                        {money(p.price)} · Tồn{" "}
                                                        {p.stock}
                                                    </p>
                                                    <button
                                                        className="btn-outline w-full"
                                                        disabled={
                                                            (cart[p.id] ?? 0) >=
                                                            p.stock
                                                        }
                                                        onClick={() =>
                                                            setCart({
                                                                ...cart,
                                                                [p.id]:
                                                                    Number(
                                                                        cart[
                                                                            p.id
                                                                        ] ?? 0,
                                                                    ) + 1,
                                                            })
                                                        }
                                                    >
                                                        + Thêm
                                                    </button>
                                                </Card>
                                            ))}
                                    </div>
                                    <Card className="h-fit">
                                        <h2 className="mb-4 text-xl font-bold">
                                            Giỏ hàng
                                        </h2>
                                        {Object.entries(cart).map(([id, n]) => (
                                            <div className="my-3" key={id}>
                                                <p className="text-sm">
                                                    {
                                                        hub.products.find(
                                                            (p: Row) =>
                                                                p.id ===
                                                                Number(id),
                                                        )?.name
                                                    }
                                                </p>
                                                <input
                                                    aria-label="Số lượng giỏ hàng"
                                                    className="input mt-2 w-20"
                                                    type="number"
                                                    min={0}
                                                    max={100}
                                                    value={n}
                                                    onChange={(e) =>
                                                        setCart({
                                                            ...cart,
                                                            [id]: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                        ))}
                                        <b className="my-4 block text-xl text-[#16a34a]">
                                            {money(
                                                Object.entries(cart).reduce(
                                                    (s, [id, n]) =>
                                                        s +
                                                        n *
                                                            (hub.products.find(
                                                                (p: Row) =>
                                                                    p.id ===
                                                                    +id,
                                                            )?.price ?? 0),
                                                    0,
                                                ),
                                            )}
                                        </b>
                                        <button
                                            className="btn w-full"
                                            disabled={
                                                invalidCart ||
                                                !Object.values(cart).some(
                                                    (n) => n > 0,
                                                )
                                            }
                                            onClick={() => edit("sale")}
                                        >
                                            Thanh toán / Gộp vào sân
                                        </button>
                                        {invalidCart && (
                                            <p
                                                role="alert"
                                                className="mt-2 text-sm text-red-700"
                                            >
                                                Số lượng phải là số nguyên, tối
                                                đa 100 và không vượt tồn kho.
                                            </p>
                                        )}
                                    </Card>
                                </div>
                                <h2 className="my-5 text-xl font-bold">
                                    Đơn bán gần đây
                                </h2>
                                <Table
                                    headers={[
                                        "Mã đơn",
                                        "Thời gian",
                                        "Tổng tiền",
                                        "Thanh toán",
                                        "Hóa đơn",
                                    ]}
                                >
                                    {hub.sales.map((s: Row) => (
                                        <tr key={s.id}>
                                            <td className={cell}>
                                                {s.reference}
                                            </td>
                                            <td className={cell}>
                                                {stamp(s.created_at)}
                                            </td>
                                            <td className={cell}>
                                                {money(s.total_amount)}
                                            </td>
                                            <td className={cell}>
                                                {s.method === "booking"
                                                    ? "Gộp hóa đơn sân"
                                                    : ((
                                                          {
                                                              cash: "Tiền mặt",
                                                              bank: "Chuyển khoản",
                                                              wallet: "Ví HUB",
                                                          } as Row
                                                      )[s.method] ?? s.method)}
                                            </td>
                                            <td className={cell}>
                                                <button
                                                    className="text-[#16a34a]"
                                                    onClick={() =>
                                                        run(async () =>
                                                            setSale(
                                                                (
                                                                    await api(
                                                                        "manage/sales/" +
                                                                            s.id +
                                                                            "/receipt",
                                                                    )
                                                                ).data,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Xem / In
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </>
                        )}
                        {tab === "reports" && (
                            <Suspense
                                fallback={<Empty text="Đang tải báo cáo…" />}
                            >
                                <>
                                    {(admin ||
                                        (data!.managed_branch_ids?.length ??
                                            0) > 1) && (
                                        <label className="mb-5 block text-sm">
                                            <input
                                                type="checkbox"
                                                checked={allReports}
                                                onChange={(e) =>
                                                    setAllReports(
                                                        e.target.checked,
                                                    )
                                                }
                                            />{" "}
                                            {admin
                                                ? "Báo cáo toàn hệ thống"
                                                : "Tất cả chi nhánh được giao"}
                                        </label>
                                    )}
                                    <ReportsView
                                        branch={allReports ? null : branch}
                                    />
                                </>
                            </Suspense>
                        )}
                        {tab === "moderation" && (
                            <>
                                <h2 className="mb-4 font-bold">
                                    Báo cáo cộng đồng
                                </h2>
                                <Table
                                    headers={[
                                        "Bài đăng",
                                        "Lý do",
                                        "Trạng thái",
                                        "Xử lý",
                                    ]}
                                >
                                    {adminData?.reports.map((r: Row) => (
                                        <tr key={r.id}>
                                            <td className={cell}>{r.title}</td>
                                            <td className={cell}>{r.reason}</td>
                                            <td className={cell}>
                                                {statusText(r.status)}
                                            </td>
                                            <td className={cell}>
                                                {["hidden", "open"].map((s) => (
                                                    <button
                                                        className="btn-outline mr-2 text-xs"
                                                        key={s}
                                                        onClick={() =>
                                                            setConfirm({
                                                                title:
                                                                    s ===
                                                                    "hidden"
                                                                        ? "Ẩn bài đăng"
                                                                        : "Giữ bài / đóng báo cáo",
                                                                path:
                                                                    "admin/posts/" +
                                                                    r.community_post_id +
                                                                    "/status",
                                                                body: {
                                                                    status: s,
                                                                },
                                                            })
                                                        }
                                                    >
                                                        {s === "hidden"
                                                            ? "Ẩn bài"
                                                            : "Giữ bài"}
                                                    </button>
                                                ))}
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                                <h2 className="my-4 font-bold">Đánh giá sân</h2>
                                <Table
                                    headers={[
                                        "Khách",
                                        "Đánh giá",
                                        "Hiển thị",
                                        "Thao tác",
                                    ]}
                                >
                                    {adminData?.reviews.map((r: Row) => (
                                        <tr key={r.id}>
                                            <td className={cell}>
                                                {r.user?.name}
                                            </td>
                                            <td className={cell}>
                                                {r.rating}★ · {r.content}
                                            </td>
                                            <td className={cell}>
                                                {r.visible ? "Có" : "Ẩn"}
                                            </td>
                                            <td className={cell}>
                                                <button
                                                    className="btn-outline text-xs"
                                                    onClick={() =>
                                                        setConfirm({
                                                            title: "Đổi hiển thị đánh giá",
                                                            path:
                                                                "admin/reviews/" +
                                                                r.id +
                                                                "/status",
                                                            body: {
                                                                visible:
                                                                    !r.visible,
                                                            },
                                                        })
                                                    }
                                                >
                                                    Ẩn / hiện
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            </>
                        )}
                        {tab === "audit" && (
                            <Table
                                headers={[
                                    "Thời gian",
                                    "Người thực hiện",
                                    "Hành động",
                                    "Đối tượng",
                                    "Chi tiết",
                                ]}
                            >
                                {adminData?.audits.map((a: Row) => (
                                    <tr key={a.id}>
                                        <td className={cell}>
                                            {stamp(a.created_at)}
                                        </td>
                                        <td className={cell}>{a.user_id}</td>
                                        <td className={cell}>{a.action}</td>
                                        <td className={cell}>
                                            {a.subject_type} #{a.subject_id}
                                        </td>
                                        <td className={cell}>
                                            <span className="block max-w-xs break-words text-xs">
                                                {a.details}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </Table>
                        )}
                    </>
                )}
                {quick && (
                    <QuickBooking
                        range={quick}
                        close={() => setQuick(null)}
                        done={async (b) => {
                            setQuick(null);
                            await load();
                            setSelected(b);
                        }}
                    />
                )}
                {editing &&
                    (editing.kind === "sale" ? (
                        <PosCheckout
                            branch={branch}
                            total={Object.entries(cart).reduce(
                                (n, [id, q]) =>
                                    n +
                                    q *
                                        (hub?.products.find(
                                            (p: Row) => p.id === Number(id),
                                        )?.price ?? 0),
                                0,
                            )}
                            close={() => setEditing(null)}
                            save={save}
                        />
                    ) : (
                        <Editor
                            title={
                                (editing.row.id ? "Cập nhật " : "Thêm ") +
                                (
                                    {
                                        branches: "chi nhánh",
                                        courts: "sân",
                                        users: "tài khoản",
                                        plans: "gói hội viên",
                                        coupons: "mã ưu đãi",
                                        products: "sản phẩm",
                                        services: "dịch vụ",
                                        maintenance: "lịch bảo trì",
                                        stock: "nhập / xuất kho",
                                        events: "sự kiện",
                                        quickcustomer: "khách hàng",
                                        payment: "giao dịch",
                                        topup: "tiền vào ví",
                                        membership: "gói hội viên",
                                    } as Row
                                )[editing.kind]
                            }
                            close={() => setEditing(null)}
                            initial={{
                                branch_id: branch,
                                active: true,
                                method: "cash",
                                ...(editing.kind === "branches"
                                    ? {
                                          opens_minute: 180,
                                          closes_minute: 1380,
                                          court_count: 6,
                                          hourly_rate: 100000,
                                          peak_rate: 180000,
                                          weekend_rate: 200000,
                                      }
                                    : {}),
                                ...editing.row,
                                ...(editing.kind === "branches"
                                    ? {
                                          opens_time:
                                              String(
                                                  Math.floor(
                                                      (editing.row
                                                          .opens_minute ??
                                                          180) / 60,
                                                  ),
                                              ).padStart(2, "0") +
                                              ":" +
                                              String(
                                                  (editing.row.opens_minute ??
                                                      180) % 60,
                                              ).padStart(2, "0"),
                                          closes_time:
                                              String(
                                                  Math.floor(
                                                      (editing.row
                                                          .closes_minute ??
                                                          1380) / 60,
                                                  ),
                                              ).padStart(2, "0") +
                                              ":" +
                                              String(
                                                  (editing.row.closes_minute ??
                                                      1380) % 60,
                                              ).padStart(2, "0"),
                                      }
                                    : {}),
                            }}
                            fields={(fields[editing.kind] ?? []).map((f) =>
                                f.name === "branch_id" && editing.row.id
                                    ? {
                                          ...f,
                                          options: (f.options ?? []).filter(
                                              (o) =>
                                                  Number(o.value) ===
                                                  editing.row.branch_id,
                                          ),
                                      }
                                    : f,
                            )}
                            save={save}
                        />
                    ))}
                {confirm && (
                    <Confirm
                        title={confirm.title}
                        close={() => setConfirm(null)}
                        action={() =>
                            changed(() =>
                                api(confirm.path, "PATCH", confirm.body),
                            )
                        }
                    >
                        Thao tác được lưu vào nhật ký hệ thống. Bạn xác nhận
                        thực hiện?
                    </Confirm>
                )}
                {selected && (
                    <BookingDetail
                        booking={selected}
                        close={() => setSelected(null)}
                        refreshBookings={load}
                    />
                )}
                {event > 0 && (
                    <EventDetail
                        id={event}
                        close={() => setEvent(0)}
                        managed
                        changed={load}
                    />
                )}
                {sale && (
                    <Modal
                        title={"Hóa đơn " + sale.reference}
                        close={() => setSale(null)}
                    >
                        <div className="print-receipt">
                            <p>
                                {sale.branch?.name} · {stamp(sale.created_at)}
                            </p>
                            {sale.items.map((i: Row) => (
                                <p
                                    className="my-3 flex justify-between"
                                    key={i.id}
                                >
                                    <span>
                                        {i.name} × {i.quantity}
                                    </span>
                                    <b>{money(i.amount)}</b>
                                </p>
                            ))}
                            <h2 className="my-4 text-xl font-bold">
                                Tổng {money(sale.total_amount)}
                            </h2>
                            <p>
                                {(
                                    {
                                        cash: "Đã thu tiền mặt",
                                        bank: "Đã xác nhận chuyển khoản",
                                        wallet: "Đã thanh toán bằng ví",
                                        booking:
                                            "Đã thêm vào hóa đơn sân · chưa thu tiền hàng tại đây",
                                    } as Row
                                )[sale.method] ?? sale.method}
                            </p>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <button
                                className="btn-outline"
                                onClick={() => window.print()}
                            >
                                In hóa đơn
                            </button>
                            {sale.booking_id && allows("bookings.read") && (
                                <button
                                    className="btn"
                                    disabled={loading}
                                    onClick={() =>
                                        run(async () => {
                                            const response = await api(
                                                "bookings/" + sale.booking_id,
                                            );
                                            setSale(null);
                                            setSelected(response.data);
                                        })
                                    }
                                >
                                    Xem hóa đơn sân
                                </button>
                            )}
                        </div>
                    </Modal>
                )}
            </main>
        </div>
    );
}
