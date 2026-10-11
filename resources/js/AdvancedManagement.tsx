import { useEffect, useState } from "react";
import IntegrationPanel from "./IntegrationPanel";
import { api, useHub } from "./hub";
import { BRANCHES, money, stamp, type Row } from "./data";
import { Card, Editor, Empty, Table, cell, type Field } from "./ui";
const labels: Record<string, string> = {
    "bookings.read": "Xem lịch",
    "bookings.write": "Đặt, đổi và nhận sân",
    "payments.write": "Thu và hoàn tiền",
    "pos.read": "Xem bán hàng",
    "pos.write": "Bán hàng",
    "courts.read": "Xem sân và giá",
    "courts.write": "Sửa sân, giá và giờ mở",
    "customers.read": "Xem khách hàng",
    "customers.write": "Tạo khách hàng",
    "reports.read": "Xem và xuất báo cáo",
    "catalog.write": "Sửa dịch vụ và kho",
    "events.write": "Quản lý giải và huấn luyện",
    "support.write": "Trả lời hỗ trợ",
};
const hm = (n: number) =>
    String(Math.floor(n / 60)).padStart(2, "0") +
    ":" +
    String(n % 60).padStart(2, "0");
const mins = (s: string) => Number(s.slice(0, 2)) * 60 + Number(s.slice(3));
const td = (value: React.ReactNode) => <td className={cell}>{value}</td>;

export default function AdvancedManagement({
    branch,
    courts,
}: {
    branch: number;
    courts: Row[];
}) {
    const { data, run, toast, refresh } = useHub();
    const admin = data!.user.role === "admin",
        perms = data!.permissions ?? {};
    const [extras, setExtras] = useState<Row | null>(null),
        [ad, setAd] = useState<Row | null>(null),
        [users, setUsers] = useState<Row[]>([]),
        [reviews, setReviews] = useState<Row[]>([]),
        [tab, setTab] = useState(() => {
            try {
                return (
                    sessionStorage.getItem("hub:operations:" + data!.user.id) ??
                    "support"
                );
            } catch {
                return "support";
            }
        }),
        [editing, setEditing] = useState<Row | null>(null),
        [assign, setAssign] = useState<Row | null>(null),
        [assigned, setAssigned] = useState<number[]>([]);
    const nav = [
        ...(perms["support.write"] ? [["support", "Hỗ trợ khách"]] : []),
        ...(perms["courts.write"]
            ? [
                  ["pricing", "Giá linh hoạt"],
                  ["policy", "Quy tắc đặt sân"],
              ]
            : []),
        ...(perms["events.write"] ? [["coaches", "Huấn luyện viên"]] : []),
        ...(perms["courts.write"] ? [["reviews", "Phản hồi đánh giá"]] : []),
        ...(admin
            ? [
                  ["moderation", "Duyệt bài"],
                  ["pages", "Nội dung website"],
                  ["rewards", "Điểm thưởng"],
                  ["permissions", "Phân quyền"],
                  ["assignments", "Phân chi nhánh"],
                  ["transactions", "Đối soát"],
                  ["integrations", "Kết nối dịch vụ"],
              ]
            : []),
    ];
    useEffect(() => {
        if (nav.length && !nav.some(([id]) => id === tab)) setTab(nav[0][0]);
    }, [JSON.stringify(nav)]);
    useEffect(() => {
        try {
            sessionStorage.setItem("hub:operations:" + data!.user.id, tab);
        } catch {}
    }, [tab]);
    async function load() {
        const r = await api("manage/features?branch_id=" + branch);
        setExtras(r.data);
        setReviews((await api("branches/" + branch + "/reviews")).data.data);
        if (admin) {
            setAd((await api("admin/features")).data);
            setUsers((await api("admin/hub")).data.users);
        }
    }
    useEffect(() => {
        load().catch((e) => toast(e.message));
    }, [branch]);
    const branchField: Field = {
        name: "branch_id",
        label: "Chi nhánh",
        required: true,
        options: [
            {
                value: branch,
                label:
                    BRANCHES.find((b) => b.id === branch)?.rawName ??
                    String(branch),
            },
        ],
    };
    const number = (
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
    const active: Field = {
        name: "active",
        label: "Đang áp dụng",
        type: "checkbox",
    };
    const fields: Record<string, Field[]> = {
        pricing: [
            branchField,
            text("name", "Tên khung giá"),
            {
                name: "court_id",
                label: "Sân (để trống áp dụng toàn chi nhánh)",
                options: courts.map((c) => ({ value: c.id, label: c.name })),
            },
            {
                name: "weekday",
                label: "Ngày trong tuần",
                options: [
                    "Chủ nhật",
                    "Thứ hai",
                    "Thứ ba",
                    "Thứ tư",
                    "Thứ năm",
                    "Thứ sáu",
                    "Thứ bảy",
                ].map((label, value) => ({ value, label })),
            },
            {
                name: "date",
                label: "Ngày riêng (ưu tiên hơn thứ)",
                type: "date",
            },
            {
                name: "from_time",
                label: "Từ giờ",
                type: "time",
                required: true,
                step: 1800,
            },
            {
                name: "to_time",
                label: "Đến giờ",
                type: "time",
                required: true,
                step: 1800,
            },
            number("hourly_rate", "Giá / giờ", 1000),
            number("priority", "Độ ưu tiên", 0, 1000),
            active,
        ],
        coaches: [
            branchField,
            text("name", "Tên huấn luyện viên"),
            {
                name: "bio",
                label: "Giới thiệu và kinh nghiệm",
                type: "textarea",
                required: true,
            },
            number("hourly_rate", "Phí học / giờ", 1000),
            active,
        ],
        pages: [
            text("title", "Tiêu đề"),
            text("slug", "Đường dẫn ngắn"),
            {
                name: "type",
                label: "Mục",
                required: true,
                options: [
                    { value: "page", label: "Giới thiệu / hợp tác" },
                    { value: "faq", label: "Câu hỏi thường gặp" },
                    { value: "blog", label: "Góc cầu lông" },
                    { value: "job", label: "Tuyển dụng" },
                ],
            },
            {
                name: "content",
                label: "Nội dung",
                type: "textarea",
                required: true,
            },
            {
                name: "published",
                label: "Công bố trên website",
                type: "checkbox",
            },
        ],
        rewards: [
            text("name", "Tên quà"),
            number("points", "Số điểm", 1, 1000000),
            number("wallet_amount", "Số tiền đổi vào ví", 1000),
            active,
        ],
        bank: [
            { name: "reference", label: "Mã lịch nhận tiền", required: true },
        ],
        support: [
            {
                name: "reply",
                label: "Phản hồi",
                type: "textarea",
                required: true,
            },
            {
                name: "status",
                label: "Trạng thái",
                required: true,
                options: [
                    { value: "open", label: "Tiếp tục xử lý" },
                    { value: "resolved", label: "Đã xử lý" },
                ],
            },
        ],
        reviews: [
            {
                name: "reply",
                label: "Phản hồi của chi nhánh",
                type: "textarea",
                required: true,
            },
        ],
        moderation: [
            {
                name: "status",
                label: "Quyết định",
                required: true,
                options: [
                    { value: "approved", label: "Duyệt và công khai" },
                    { value: "rejected", label: "Từ chối" },
                ],
            },
            {
                name: "reason",
                label: "Lý do phản hồi tới tác giả",
                type: "textarea",
                required: true,
            },
        ],
        policy: [
            {
                name: "open_time",
                label: "Giờ mở cửa",
                type: "time",
                required: true,
                step: 1800,
            },
            {
                name: "close_time",
                label: "Giờ đóng cửa",
                type: "time",
                required: true,
                step: 1800,
            },
            {
                name: "peak_time",
                label: "Bắt đầu giờ cao điểm",
                type: "time",
                required: true,
                step: 1800,
            },
            number("booking_days", "Số ngày được đặt trước", 1, 365),
            number("deposit_percent", "Tỷ lệ đặt cọc (%)", 0, 100),
            number("payment_minutes", "Thời hạn đặt cọc (phút)", 5, 120),
            number("fixed_discount", "Giảm giá lịch cố định (%)", 0, 50),
        ],
    };
    function edit(kind: string, row: Row = {}) {
        setEditing({
            kind,
            row: {
                branch_id: branch,
                active: true,
                priority: 0,
                from_time: "06:00",
                to_time: "16:00",
                ...row,
                ...(kind === "pricing" && row.id
                    ? {
                          from_time: hm(row.from_minute),
                          to_time: hm(row.to_minute),
                      }
                    : {}),
                ...(kind === "policy"
                    ? {
                          open_time: hm(row.opens_minute),
                          close_time: hm(row.closes_minute),
                          peak_time: hm(row.peak_minute),
                      }
                    : {}),
            },
        });
    }
    async function save(d: Row) {
        const kind = editing!.kind,
            id = editing!.row.id;
        let path = "",
            method = id ? "PUT" : "POST";
        for (const f of fields[kind])
            if (f.type === "number") d[f.name] = Number(d[f.name]);
        if (kind === "pricing") {
            d.branch_id = Number(d.branch_id);
            d.court_id = d.court_id ? Number(d.court_id) : null;
            d.weekday =
                d.weekday === "" || d.weekday == null
                    ? null
                    : Number(d.weekday);
            d.date = d.date || null;
            d.from_minute = mins(d.from_time);
            d.to_minute = mins(d.to_time);
        }
        if (kind === "coaches") d.branch_id = Number(d.branch_id);
        if (["pricing", "coaches"].includes(kind))
            path = "manage/" + kind + (id ? "/" + id : "");
        if (["pages", "rewards"].includes(kind))
            path = "admin/" + kind + (id ? "/" + id : "");
        if (kind === "bank") {
            path = "admin/bank-transactions/" + id + "/match";
            method = "POST";
        }
        if (kind === "support") {
            path = "manage/support/" + id;
            method = "PATCH";
        }
        if (kind === "reviews") {
            path = "manage/reviews/" + id + "/reply";
            method = "PATCH";
        }
        if (kind === "moderation") {
            d.decision = d.status;
            path = "admin/posts/" + id + "/moderation";
            method = "PATCH";
        }
        if (kind === "policy") {
            path = "manage/branches/" + branch + "/policy";
            method = "PUT";
            d.opens_minute = mins(d.open_time);
            d.closes_minute = mins(d.close_time);
            d.peak_minute = mins(d.peak_time);
        }
        await api(path, method, d);
        await load();
        await refresh();
        toast("Đã lưu thay đổi.");
    }
    if (!extras) return <Empty text="Đang tải…" />;
    const tickets = admin ? (ad?.all_tickets ?? []) : extras.tickets;
    return (
        <section>
            <div className="mb-6 flex flex-wrap gap-2">
                {nav.map(([id, label]) => (
                    <button
                        key={id}
                        className={tab === id ? "btn" : "btn-outline"}
                        onClick={() => setTab(id)}
                    >
                        {label}
                    </button>
                ))}
            </div>
            {tab === "integrations" && admin && <IntegrationPanel />}
            {["pricing", "coaches", "pages", "rewards"].includes(tab) && (
                <div>
                    <button className="btn mb-4" onClick={() => edit(tab)}>
                        + Thêm mới
                    </button>
                    <Table headers={["Tên", "Thông tin", "Trạng thái", ""]}>
                        {(tab === "pricing"
                            ? extras.pricing
                            : tab === "coaches"
                              ? extras.coaches
                              : (ad?.[tab] ?? [])
                        ).map((v: Row) => (
                            <tr key={v.id}>
                                {td(v.name ?? v.title)}
                                {td(
                                    tab === "pricing"
                                        ? hm(v.from_minute) +
                                              "–" +
                                              hm(v.to_minute) +
                                              " · " +
                                              money(v.hourly_rate) +
                                              "/giờ"
                                        : tab === "coaches"
                                          ? money(v.hourly_rate) + "/giờ"
                                          : tab === "rewards"
                                            ? v.points +
                                              " điểm → " +
                                              money(v.wallet_amount)
                                            : v.slug,
                                )}
                                {td(
                                    (v.active ?? v.published)
                                        ? "Đang mở"
                                        : "Tạm ẩn",
                                )}
                                {td(
                                    <button
                                        className="btn-outline"
                                        onClick={() => edit(tab, v)}
                                    >
                                        Sửa
                                    </button>,
                                )}
                            </tr>
                        ))}
                    </Table>
                    {tab === "pricing" && (
                        <p className="mt-3 text-sm text-slate-500">
                            Khung giá có độ ưu tiên cao hơn được áp dụng trước.
                            Ngoài khung này, dùng giá thường / cao điểm / cuối
                            tuần của sân.
                        </p>
                    )}
                </div>
            )}
            {tab === "policy" &&
                extras.branches
                    .filter((b: Row) => b.id === branch)
                    .map((b: Row) => (
                        <Card key={b.id}>
                            <h2 className="text-xl font-bold">{b.name}</h2>
                            <div className="my-5 grid gap-3 sm:grid-cols-2">
                                <p>
                                    Mở cửa {hm(b.opens_minute)}–
                                    {hm(b.closes_minute)}
                                </p>
                                <p>Giờ cao điểm từ {hm(b.peak_minute)}</p>
                                <p>Đặt trước {b.booking_days} ngày</p>
                                <p>
                                    Cọc {b.deposit_percent}% trong{" "}
                                    {b.payment_minutes} phút
                                </p>
                                <p>Lịch cố định giảm {b.fixed_discount}%</p>
                            </div>
                            <button
                                className="btn"
                                onClick={() => edit("policy", b)}
                            >
                                Sửa quy tắc
                            </button>
                        </Card>
                    ))}
            {tab === "support" && (
                <div className="space-y-4">
                    {tickets.map((v: Row) => (
                        <Card key={v.id}>
                            <span className="badge badge-green">
                                {v.status === "resolved"
                                    ? "Đã xử lý"
                                    : "Đang chờ"}
                            </span>
                            <h2 className="my-3 text-lg font-bold">
                                {v.subject}
                            </h2>
                            <p className="whitespace-pre-wrap text-sm">
                                {v.content}
                            </p>
                            {v.reply && (
                                <p className="my-3 rounded-xl bg-slate-50 p-3 text-sm">
                                    {v.reply}
                                </p>
                            )}
                            {perms["support.write"] && (
                                <button
                                    className="btn-outline mt-4"
                                    onClick={() => edit("support", v)}
                                >
                                    Phản hồi
                                </button>
                            )}
                        </Card>
                    ))}
                    {!tickets.length && (
                        <Empty text="Chưa có yêu cầu hỗ trợ." />
                    )}
                </div>
            )}
            {tab === "reviews" && (
                <div className="space-y-4">
                    {reviews.map((v) => (
                        <Card key={v.id}>
                            <b>
                                {v.user?.name} · {"★".repeat(v.rating)}
                            </b>
                            <p className="my-3">{v.content}</p>
                            {v.reply && (
                                <p className="mb-3 text-sm text-[#168449]">
                                    {v.reply}
                                </p>
                            )}
                            {perms["courts.write"] && (
                                <button
                                    className="btn-outline"
                                    onClick={() => edit("reviews", v)}
                                >
                                    Phản hồi đánh giá
                                </button>
                            )}
                        </Card>
                    ))}
                    {!reviews.length && <Empty text="Chưa có đánh giá." />}
                </div>
            )}
            {tab === "moderation" && (
                <>
                    <Card>
                        <h2 className="font-bold">Duyệt bán tự động</h2>
                        <p className="mt-3 text-sm">
                            Bài bị đánh dấu hoặc chưa có kết quả kiểm tra được
                            đưa vào hàng chờ. Người duyệt đọc nội dung và quyết
                            định cuối cùng.
                        </p>
                        <p className="mt-3 text-xs text-slate-500">
                            Tìm sân: {ad?.ai.search_provider} /{" "}
                            {ad?.ai.search_model} · Duyệt bài:{" "}
                            {ad?.ai.moderation_provider} /{" "}
                            {ad?.ai.moderation_model}
                        </p>
                    </Card>
                    <div className="mt-4 space-y-4">
                        {ad?.moderation.map((v: Row) => (
                            <Card key={v.id}>
                                <b>{v.title}</b>
                                <p className="mt-2 text-xs text-slate-500">
                                    {v.user?.name} · {v.branch?.name} ·{" "}
                                    {stamp(v.created_at)}
                                </p>
                                <p className="my-4 whitespace-pre-wrap">
                                    {v.content}
                                </p>
                                <div className="mb-3 flex flex-wrap gap-2">
                                    {v.images?.map((id: number) => (
                                        <a
                                            href={"/api/v1/media/" + id}
                                            target="_blank"
                                            rel="noreferrer"
                                            key={id}
                                        >
                                            <img
                                                className="h-24 w-24 rounded-xl object-cover"
                                                src={"/api/v1/media/" + id}
                                                alt="Ảnh trong bài"
                                            />
                                        </a>
                                    ))}
                                </div>
                                <p className="rounded-xl bg-amber-50 p-3 text-sm">
                                    {v.moderation_result?.reason} · Độ tin cậy{" "}
                                    {Math.round(
                                        (v.moderation_result?.confidence ?? 0) *
                                            100,
                                    )}
                                    %
                                </p>
                                <button
                                    className="btn mt-3"
                                    onClick={() =>
                                        edit("moderation", {
                                            id: v.id,
                                            status: "approved",
                                            reason: "Đã kiểm tra nội dung.",
                                        })
                                    }
                                >
                                    Duyệt / từ chối
                                </button>
                            </Card>
                        ))}
                        {ad && !ad.moderation.length && (
                            <Empty text="Hàng chờ đã được xử lý." />
                        )}
                    </div>
                </>
            )}
            {tab === "permissions" && (
                <Table headers={["Quyền", "Quản lý", "Nhân viên", "Thu ngân"]}>
                    {ad?.permission_names.map((p: string) => (
                        <tr key={p}>
                            {td(labels[p] ?? p)}
                            {["manager", "staff", "cashier"].map((role) => {
                                const found = ad.permissions.find(
                                    (v: Row) =>
                                        v.role === role && v.permission === p,
                                );
                                const checked = found
                                    ? !!found.allowed
                                    : role === "manager" ||
                                      [
                                          "bookings.read",
                                          "bookings.write",
                                          "payments.write",
                                          "pos.read",
                                          "pos.write",
                                          "courts.read",
                                          "customers.read",
                                          "customers.write",
                                          "reports.read",
                                          "support.write",
                                      ].includes(p);
                                return (
                                    <td key={role} className="p-3">
                                        <input
                                            aria-label={
                                                (labels[p] ?? p) +
                                                " cho " +
                                                role
                                            }
                                            type="checkbox"
                                            checked={checked}
                                            onChange={(e) =>
                                                run(async () => {
                                                    await api(
                                                        "admin/permissions",
                                                        "PUT",
                                                        {
                                                            role,
                                                            permission: p,
                                                            allowed:
                                                                e.target
                                                                    .checked,
                                                        },
                                                    );
                                                    await load();
                                                })
                                            }
                                        />
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </Table>
            )}
            {tab === "assignments" && (
                <Table
                    headers={["Nhân sự", "Vai trò", "Chi nhánh được giao", ""]}
                >
                    {users
                        .filter((u) =>
                            ["manager", "staff", "cashier"].includes(u.role),
                        )
                        .map((u) => {
                            const ids = [
                                u.branch_id,
                                ...(ad?.user_branches
                                    .filter((v: Row) => v.user_id === u.id)
                                    .map((v: Row) => v.branch_id) ?? []),
                            ].filter(Boolean);
                            return (
                                <tr key={u.id}>
                                    {td(u.name)}
                                    {td(u.role)}
                                    {td(
                                        BRANCHES.filter((b) =>
                                            ids.includes(b.id),
                                        )
                                            .map((b) => b.name)
                                            .join(", "),
                                    )}
                                    {td(
                                        <button
                                            className="btn-outline"
                                            onClick={() => {
                                                setAssign(u);
                                                setAssigned([...new Set(ids)]);
                                            }}
                                        >
                                            Phân chi nhánh
                                        </button>,
                                    )}
                                </tr>
                            );
                        })}
                </Table>
            )}
            {assign && (
                <Card>
                    <h2 className="mb-4 text-xl font-bold">
                        Chi nhánh của {assign.name}
                    </h2>
                    <div className="mb-4 flex flex-wrap gap-4">
                        {BRANCHES.map((b) => (
                            <label key={b.id}>
                                <input
                                    type="checkbox"
                                    checked={assigned.includes(b.id)}
                                    onChange={(e) =>
                                        setAssigned(
                                            e.target.checked
                                                ? [...assigned, b.id]
                                                : assigned.filter(
                                                      (v) => v !== b.id,
                                                  ),
                                        )
                                    }
                                />{" "}
                                {b.name}
                            </label>
                        ))}
                    </div>
                    <button
                        className="btn"
                        onClick={() =>
                            run(async () => {
                                await api(
                                    "admin/users/" + assign.id + "/branches",
                                    "PUT",
                                    { branch_ids: assigned },
                                );
                                setAssign(null);
                                await load();
                            })
                        }
                    >
                        Lưu phân công
                    </button>
                    <button
                        className="btn-outline ml-3"
                        onClick={() => setAssign(null)}
                    >
                        Đóng
                    </button>
                </Card>
            )}
            {tab === "transactions" && (
                <>
                    <h2 className="mb-4 text-xl font-bold">
                        Chuyển khoản đã nhận
                    </h2>
                    <Table
                        headers={[
                            "Mã giao dịch",
                            "Nội dung",
                            "Lịch",
                            "Số tiền",
                            "Trạng thái",
                            "",
                        ]}
                    >
                        {ad?.bank_transactions.map((v: Row) => (
                            <tr key={v.id}>
                                {td(v.transaction_id)}
                                {td(v.content)}
                                {td(v.booking_id ?? "Chưa khớp nội dung")}
                                {td(money(v.amount))}
                                {td(
                                    v.status === "unmatched"
                                        ? "Cần đối soát"
                                        : "Đã ghi nhận",
                                )}
                                {td(
                                    v.status === "unmatched" ? (
                                        <button
                                            className="btn-outline"
                                            onClick={() =>
                                                setEditing({
                                                    kind: "bank",
                                                    row: v,
                                                })
                                            }
                                        >
                                            Khớp lịch
                                        </button>
                                    ) : (
                                        ""
                                    ),
                                )}
                            </tr>
                        ))}
                    </Table>
                    <h2 className="my-4 text-xl font-bold">
                        Yêu cầu hoàn MoMo
                    </h2>
                    <Table headers={["Lịch", "Số tiền", "Trạng thái", ""]}>
                        {ad?.refund_requests.map((v: Row) => (
                            <tr key={v.id}>
                                {td(v.booking_id)}
                                {td(money(v.amount))}
                                {td(v.message ?? "Đang chờ")}
                                {td(
                                    v.status === "pending" ? (
                                        <button
                                            className="btn-outline"
                                            onClick={() =>
                                                run(async () => {
                                                    await api(
                                                        "manage/bookings/" +
                                                            v.booking_id +
                                                            "/momo-refund",
                                                        "POST",
                                                        {
                                                            request_key:
                                                                v.request_key,
                                                            attempt_id:
                                                                v.payment_attempt_id,
                                                            amount: v.amount,
                                                        },
                                                    );
                                                    await load();
                                                })
                                            }
                                        >
                                            Kiểm tra kết quả
                                        </button>
                                    ) : (
                                        "Đã hoàn"
                                    ),
                                )}
                            </tr>
                        ))}
                    </Table>
                </>
            )}
            {editing && (
                <Editor
                    title={
                        editing.kind === "moderation"
                            ? "Quyết định duyệt bài"
                            : "Cập nhật " +
                              (nav.find((v) => v[0] === editing.kind)?.[1] ??
                                  "nội dung")
                    }
                    fields={fields[editing.kind]}
                    initial={editing.row}
                    close={() => setEditing(null)}
                    save={save}
                />
            )}
        </section>
    );
}
