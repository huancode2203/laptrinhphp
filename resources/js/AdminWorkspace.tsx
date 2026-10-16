import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { money, statusText, stamp, localDateTime, type Row } from "./data";
import { Card, Editor, Empty, Table, cell, type Field } from "./ui";
import ReportsView from "./ReportsView";
import AdvancedManagement from "./AdvancedManagement";
import ManagedCourts from "./ManagedCourts";
import WorkspaceAccount from "./WorkspaceAccount";
import AdminModeration from "./AdminModeration";
import IntegrationPanel from "./IntegrationPanel";
import { Logo } from "./Brand";

const menu = [
    ["dashboard", "Tổng quan & doanh thu"],
    ["branches", "Chi nhánh"],
    ["courts", "Danh mục sân"],
    ["users", "Tài khoản & vai trò"],
    ["plans", "Gói hội viên"],
    ["coupons", "Mã ưu đãi"],
    ["moderation", "Kiểm duyệt cộng đồng"],
    ["payments", "Thanh toán & kết nối"],
    ["operations", "Cấu hình & phân quyền"],
    ["audits", "Nhật ký hoạt động"],
    ["profile", "Tài khoản quản trị"],
];
export default function AdminWorkspace({ logout }: { logout: () => void }) {
    const { data, refresh, toast, run, loading } = useHub();
    const route = () =>
        menu.some(([id]) => id === location.hash.split("/")[1])
            ? location.hash.split("/")[1]
            : "dashboard";
    const [tab, setTab] = useState(route),
        [hub, setHub] = useState<Row | null>(null),
        [branch, setBranch] = useState(0),
        [search, setSearch] = useState(""),
        [page, setPage] = useState(1),
        [version, setVersion] = useState(0),
        [error, setError] = useState(""),
        [editing, setEditing] = useState<Row | null>(null),
        [courts, setCourts] = useState<Row[]>([]),
        [state, setState] = useState("");
    const go = (next: string) => {
        setTab(next);
        setSearch("");
        setState("");
        setPage(1);
        location.hash = "admin/" + next;
    };
    useEffect(() => {
        const follow = () => setTab(route());
        window.addEventListener("hashchange", follow);
        if (!location.hash.startsWith("#admin/"))
            location.hash = "admin/dashboard";
        return () => window.removeEventListener("hashchange", follow);
    }, []);
    useEffect(() => {
        let live = true;
        api("admin/hub")
            .then((r) => {
                if (live) {
                    setHub(r.data);
                    setError("");
                }
            })
            .catch((e) => {
                if (live) setError(e.message);
            });
        return () => {
            live = false;
        };
    }, [version]);
    const selectedBranch = branch || hub?.branches?.[0]?.id;
    useEffect(() => {
        if (!selectedBranch) return;
        let live = true;
        setCourts([]);
        api("manage/courts?branch_id=" + selectedBranch)
            .then((r) => {
                if (live) setCourts(r.data);
            })
            .catch((e) => toast(e.message));
        return () => {
            live = false;
        };
    }, [selectedBranch, version]);
    const changed = async () => {
        setVersion((v) => v + 1);
        await refresh();
    };
    const branches = hub?.branches ?? [];
    const branchField: Field = {
        name: "branch_id",
        label: "Chi nhánh phụ trách",
        options: branches.map((b: Row) => ({ value: b.id, label: b.name })),
    };
    const text = (name: string, label: string): Field => ({
        name,
        label,
        required: true,
    });
    const number = (
        name: string,
        label: string,
        min = 0,
        max = 10000000,
    ): Field => ({ name, label, type: "number", required: true, min, max });
    const active: Field = {
        name: "active",
        label: "Đang hoạt động",
        type: "checkbox",
    };
    const fields: Record<string, Field[]> = {
        branches: [
            text("name", "Tên chi nhánh"),
            text("ward", "Phường / xã"),
            text("address", "Địa chỉ đầy đủ"),
            { name: "phone", label: "Số điện thoại" },
            active,
            {
                name: "google_place_id",
                label: "Google Maps Place ID",
                help: "Có thể lấy từ Google Maps để chỉ đường chính xác hơn.",
            },
            {
                name: "latitude",
                label: "Vĩ độ",
                type: "number",
                step: 0.0000001,
                min: -90,
                max: 90,
            },
            {
                name: "longitude",
                label: "Kinh độ",
                type: "number",
                step: 0.0000001,
                min: -180,
                max: 180,
            },
        ],
        users: [
            text("name", "Họ và tên"),
            { ...text("email", "Email"), type: "email" },
            { name: "phone", label: "Số điện thoại" },
            ...(!editing?.row.id
                ? [
                      {
                          ...text("password", "Mật khẩu ban đầu"),
                          type: "password",
                          maxLength: 72,
                      },
                      {
                          ...text("password_confirmation", "Nhập lại mật khẩu"),
                          type: "password",
                          maxLength: 72,
                      },
                  ]
                : []),
            {
                name: "role",
                label: "Vai trò",
                required: true,
                options: [
                    "customer",
                    "manager",
                    "staff",
                    "cashier",
                    "admin",
                ].map((value) => ({ value, label: statusText(value) })),
            },
            branchField,
        ],
        plans: [
            text("name", "Tên gói"),
            number("price", "Giá gói"),
            number("discount_percent", "Giảm giá (%)", 0, 50),
            number("duration_days", "Số ngày hiệu lực", 1, 365),
            number("early_days", "Số ngày đặt sớm", 0, 90),
            number("points_threshold", "Điểm yêu cầu"),
            active,
        ],
        coupons: [
            text("code", "Mã ưu đãi"),
            branchField,
            {
                name: "kind",
                label: "Hình thức",
                required: true,
                options: [
                    { value: "percent", label: "Phần trăm" },
                    { value: "fixed", label: "Số tiền" },
                ],
            },
            number("value", "Mức giảm", 1),
            number("minimum_amount", "Hóa đơn tối thiểu"),
            {
                name: "max_discount",
                label: "Giảm tối đa",
                type: "number",
                min: 1,
                max: 10000000,
            },
            number("usage_limit", "Số lượt tối đa", 1, 1000000),
            { ...text("starts_at", "Bắt đầu"), type: "datetime-local" },
            { ...text("expires_at", "Kết thúc"), type: "datetime-local" },
            active,
        ],
    };
    const source = hub?.[tab] ?? [];
    const filtered = source.filter(
        (row: Row) =>
            JSON.stringify([
                row.name,
                row.email,
                row.ward,
                row.address,
                row.code,
                row.role,
            ])
                .toLocaleLowerCase("vi")
                .includes(search.toLocaleLowerCase("vi")) &&
            (!state || String(!!row.active) === state),
    );
    const pages = Math.max(1, Math.ceil(filtered.length / 10));
    const visible = filtered.slice(
        (Math.min(page, pages) - 1) * 10,
        Math.min(page, pages) * 10,
    );
    return (
        <div className="admin-workspace">
            <aside className="admin-sidebar">
                <Logo />
                <p className="mt-3 text-xs text-white/60">
                    QUẢN TRỊ TOÀN HỆ THỐNG
                </p>
                <nav aria-label="Quản trị hệ thống" className="mt-7">
                    {menu.map(([id, label]) => (
                        <button
                            key={id}
                            aria-current={tab === id ? "page" : undefined}
                            onClick={() => go(id)}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
            </aside>
            <div className="min-w-0 flex-1">
                <header className="workspace-topbar">
                    <div>
                        <b>Trung tâm quản trị</b>
                        <p className="text-xs text-slate-500">
                            Hoạt động chi nhánh · Doanh thu · Phân quyền
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="text-sm">{data!.user.name}</span>
                        <button
                            className="btn-outline text-sm"
                            disabled={loading}
                            onClick={logout}
                        >
                            Đăng xuất
                        </button>
                    </div>
                </header>
                <main className="p-4 md:p-8">
                    <select
                        className="input mb-5 w-full md:hidden"
                        aria-label="Mục quản trị"
                        value={tab}
                        onChange={(e) => go(e.target.value)}
                    >
                        {menu.map(([id, label]) => (
                            <option key={id} value={id}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="eyebrow">ADMIN / HỆ THỐNG</p>
                            <h1 className="mt-2 text-3xl font-bold">
                                {menu.find(([id]) => id === tab)?.[1]}
                            </h1>
                        </div>
                        <button
                            className="btn-outline"
                            disabled={loading}
                            onClick={() => setVersion((v) => v + 1)}
                        >
                            Làm mới
                        </button>
                    </div>
                    {error && (
                        <p role="alert" className="form-alert">
                            {error}
                        </p>
                    )}
                    {!hub ? (
                        <Empty text="Đang tải dữ liệu quản trị…" />
                    ) : (
                        <>
                            {tab === "dashboard" && (
                                <>
                                    <div className="mb-6 grid gap-4 sm:grid-cols-3">
                                        {[
                                            [
                                                "Chi nhánh",
                                                branches.length,
                                                "branches",
                                            ],
                                            [
                                                "Tài khoản",
                                                hub.stats.users,
                                                "users",
                                            ],
                                            [
                                                "Bài chờ duyệt",
                                                hub.stats.pending_posts,
                                                "moderation",
                                            ],
                                        ].map(([label, value, destination]) => (
                                            <button
                                                key={String(label)}
                                                className="admin-stat-link"
                                                onClick={() =>
                                                    go(String(destination))
                                                }
                                                aria-label={"Xem " + label}
                                            >
                                                <Card>
                                                    <p>{label}</p>
                                                    <b className="mt-3 block text-3xl">
                                                        {value}
                                                    </b>
                                                    <span className="mt-3 block text-xs font-bold text-emerald-700">
                                                        Xem danh sách ↗
                                                    </span>
                                                </Card>
                                            </button>
                                        ))}
                                    </div>
                                </>
                            )}
                            {["dashboard", "courts", "operations"].includes(
                                tab,
                            ) && (
                                <label className="mb-6 block max-w-xl text-sm font-bold">
                                    {tab === "dashboard"
                                        ? "Phạm vi báo cáo"
                                        : "Chi nhánh"}
                                    <select
                                        aria-label="Phạm vi chi nhánh quản trị"
                                        className="input mt-2 w-full"
                                        value={
                                            tab === "dashboard"
                                                ? branch
                                                : selectedBranch
                                        }
                                        onChange={(e) =>
                                            setBranch(Number(e.target.value))
                                        }
                                    >
                                        {tab === "dashboard" && (
                                            <option value={0}>
                                                Toàn hệ thống
                                            </option>
                                        )}
                                        {branches.map((b: Row) => (
                                            <option key={b.id} value={b.id}>
                                                {b.name} ·{" "}
                                                {b.ward || b.district}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            {tab === "dashboard" && (
                                <ReportsView
                                    key={"reports-" + branch + "-" + version}
                                    branch={branch || null}
                                />
                            )}
                            {tab === "courts" && selectedBranch && (
                                <ManagedCourts branch={selectedBranch} />
                            )}
                            {tab === "moderation" && (
                                <AdminModeration after={changed} />
                            )}
                            {tab === "payments" && <IntegrationPanel />}
                            {tab === "operations" && selectedBranch && (
                                <AdvancedManagement
                                    key={tab + selectedBranch}
                                    branch={selectedBranch}
                                    courts={courts}
                                />
                            )}
                            {tab === "profile" && <WorkspaceAccount />}
                            {fields[tab] && (
                                <>
                                    <div className="mb-5 flex flex-wrap gap-3">
                                        <input
                                            aria-label="Tìm danh mục quản trị"
                                            className="input min-w-48 flex-1"
                                            value={search}
                                            placeholder="Tìm tên, phường/xã, email hoặc mã…"
                                            onChange={(e) => {
                                                setSearch(e.target.value);
                                                setPage(1);
                                            }}
                                        />
                                        {tab !== "users" && (
                                            <select
                                                aria-label="Lọc hoạt động"
                                                className="input"
                                                value={state}
                                                onChange={(e) => {
                                                    setState(e.target.value);
                                                    setPage(1);
                                                }}
                                            >
                                                <option value="">
                                                    Mọi trạng thái
                                                </option>
                                                <option value="true">
                                                    Đang hoạt động
                                                </option>
                                                <option value="false">
                                                    Đã ngừng
                                                </option>
                                            </select>
                                        )}
                                        <button
                                            className="btn"
                                            onClick={() =>
                                                setEditing({
                                                    kind: tab,
                                                    row: {
                                                        active: true,
                                                        role: "customer",
                                                        branch_id:
                                                            selectedBranch,
                                                        ward: "",
                                                        price: 0,
                                                        discount_percent: 0,
                                                        duration_days: 30,
                                                        early_days: 0,
                                                        points_threshold: 0,
                                                        kind: "percent",
                                                        value: 10,
                                                        minimum_amount: 0,
                                                        usage_limit: 100,
                                                    },
                                                })
                                            }
                                        >
                                            + Thêm mới
                                        </button>
                                    </div>
                                    <p className="mb-3 text-sm">
                                        {filtered.length} kết quả · Trang{" "}
                                        {Math.min(page, pages)}/{pages}
                                    </p>
                                    <Table
                                        headers={[
                                            "Tên / nội dung",
                                            "Chi tiết",
                                            "Trạng thái",
                                            "Thao tác",
                                        ]}
                                    >
                                        {visible.map((row: Row) => (
                                            <tr key={row.id}>
                                                <td className={cell}>
                                                    <b>
                                                        {row.name || row.code}
                                                    </b>
                                                    <p className="text-xs">
                                                        {row.ward ||
                                                            row.email ||
                                                            row.district}
                                                    </p>
                                                </td>
                                                <td className={cell}>
                                                    {row.address ||
                                                        (row.role
                                                            ? statusText(
                                                                  row.role,
                                                              )
                                                            : money(
                                                                  row.price ??
                                                                      row.value,
                                                              ))}
                                                </td>
                                                <td className={cell}>
                                                    {row.active
                                                        ? "Hoạt động"
                                                        : "Đã ngừng"}
                                                </td>
                                                <td className={cell}>
                                                    <div className="flex flex-wrap gap-2">
                                                        {!(
                                                            tab === "users" &&
                                                            row.id ===
                                                                data!.user.id
                                                        ) && (
                                                            <button
                                                                className="btn-outline text-sm"
                                                                onClick={() =>
                                                                    setEditing({
                                                                        kind: tab,
                                                                        row: {
                                                                            ...row,
                                                                            ward:
                                                                                row.ward ||
                                                                                row.district,
                                                                            ...(tab ===
                                                                            "coupons"
                                                                                ? {
                                                                                      starts_at:
                                                                                          localDateTime(
                                                                                              row.starts_at,
                                                                                          ),
                                                                                      expires_at:
                                                                                          localDateTime(
                                                                                              row.expires_at,
                                                                                          ),
                                                                                  }
                                                                                : {}),
                                                                        },
                                                                    })
                                                                }
                                                            >
                                                                {tab === "users"
                                                                    ? "Phân vai trò"
                                                                    : "Sửa"}
                                                            </button>
                                                        )}
                                                        {tab === "users" &&
                                                            row.id !==
                                                                data!.user
                                                                    .id && (
                                                                <button
                                                                    className="btn-outline text-sm"
                                                                    disabled={
                                                                        loading
                                                                    }
                                                                    onClick={() =>
                                                                        run(
                                                                            async () => {
                                                                                await api(
                                                                                    "admin/users/" +
                                                                                        row.id +
                                                                                        "/active",
                                                                                    "PATCH",
                                                                                    {
                                                                                        active: !row.active,
                                                                                    },
                                                                                );
                                                                                await changed();
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    {row.active
                                                                        ? "Khóa"
                                                                        : "Mở khóa"}
                                                                </button>
                                                            )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </Table>
                                    {!visible.length && <Empty />}
                                    <div className="mt-4 flex items-center justify-between">
                                        <button
                                            className="btn-outline"
                                            disabled={page <= 1}
                                            onClick={() =>
                                                setPage((p) => p - 1)
                                            }
                                        >
                                            Trước
                                        </button>
                                        <span>
                                            {Math.min(page, pages)} / {pages}
                                        </span>
                                        <button
                                            className="btn-outline"
                                            disabled={page >= pages}
                                            onClick={() =>
                                                setPage((p) => p + 1)
                                            }
                                        >
                                            Sau
                                        </button>
                                    </div>
                                </>
                            )}
                            {tab === "audits" && (
                                <Table
                                    headers={[
                                        "Thời gian",
                                        "Hoạt động",
                                        "Đối tượng",
                                        "Chi tiết",
                                    ]}
                                >
                                    {hub.audits.map((row: Row) => (
                                        <tr key={row.id}>
                                            <td className={cell}>
                                                {stamp(row.created_at)}
                                            </td>
                                            <td className={cell}>
                                                {row.action}
                                            </td>
                                            <td className={cell}>
                                                {row.subject_type} #
                                                {row.subject_id}
                                            </td>
                                            <td className={cell}>
                                                {typeof row.details === "string"
                                                    ? row.details
                                                    : JSON.stringify(
                                                          row.details,
                                                      )}
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            )}
                        </>
                    )}
                    {editing && (
                        <Editor
                            title={
                                (editing.row.id ? "Cập nhật " : "Thêm ") +
                                (menu.find(
                                    ([id]) => id === editing.kind,
                                )?.[1] ?? "thông tin")
                            }
                            initial={editing.row}
                            close={() => setEditing(null)}
                            fields={fields[editing.kind]}
                            save={async (d) => {
                                let path =
                                    "admin/" +
                                    editing.kind +
                                    (editing.row.id
                                        ? "/" + editing.row.id
                                        : "");
                                let method = editing.row.id ? "PUT" : "POST";
                                if (
                                    editing.kind === "users" &&
                                    editing.row.id
                                ) {
                                    path += "/role";
                                    method = "PATCH";
                                }
                                if (editing.kind === "branches")
                                    d.district = d.ward;
                                await api(path, method, d);
                                await changed();
                                toast("Đã cập nhật dữ liệu quản trị.");
                            }}
                        />
                    )}
                </main>
            </div>
        </div>
    );
}
