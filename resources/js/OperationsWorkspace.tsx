import { useEffect, useState } from "react";
import { api, key, useHub } from "./hub";
import { money, stamp, day, type Row } from "./data";
import { Card, Editor, Empty, Table, Modal, cell, type Field } from "./ui";

const labels: Record<string, string> = {
    claims: "Chuyển khoản chờ xác nhận",
    reconciliations: "Đối soát PayOS",
    shifts: "Ca thu ngân",
    suppliers: "Nhà cung cấp",
    purchases: "Nhập kho",
    expenses: "Chi phí vận hành",
    payroll: "Lương nội bộ",
    sales: "Đổi / gỡ hàng POS",
    finance: "Tổng hợp thu chi",
};
const states: Record<string, string> = {
    submitted: "Chờ đối chiếu",
    approved: "Đã xác nhận",
    rejected: "Không xác nhận",
    pending: "Cần đối soát",
    resolved: "Đã đối soát",
    open: "Đang mở",
    closed: "Đã chốt",
    received: "Đã nhập kho",
    draft: "Chưa chi",
    paid: "Đã chi",
};
const methods = [
    { value: "cash", label: "Tiền mặt" },
    { value: "bank", label: "Chuyển khoản" },
];
export default function OperationsWorkspace({ branch }: { branch: number }) {
    const { data, run, toast, loading } = useHub();
    const admin = data!.user.role === "admin",
        manager = ["admin", "manager"].includes(data!.user.role);
    const allowed = (id: string) => {
        if (id === "payroll") return admin;
        if (["expenses", "finance"].includes(id)) return manager;
        if (["suppliers", "purchases"].includes(id))
            return !!data!.permissions?.["catalog.write"];
        if (id === "sales") return !!data!.permissions?.["pos.write"];
        return !!data!.permissions?.["payments.write"];
    };
    const [tab, setTab] = useState(
            Object.keys(labels).find(allowed) ?? "claims",
        ),
        [page, setPage] = useState(1),
        [state, setState] = useState(""),
        [search, setSearch] = useState("");
    const [from, setFrom] = useState(day().slice(0, 8) + "01"),
        [to, setTo] = useState(day());
    const [result, setResult] = useState<Row | null>(null),
        [choices, setChoices] = useState<Row>({}),
        [error, setError] = useState(""),
        [version, setVersion] = useState(0),
        [editing, setEditing] = useState<Row | null>(null);
    const [exchangePreview, setExchangePreview] = useState<Row | null>(null);
    const query = new URLSearchParams({
        branch_id: branch ? String(branch) : "",
        page: String(page),
        state,
        search,
        from,
        to,
    });
    useEffect(() => {
        let live = true;
        setResult(null);
        setError("");
        const timer = setTimeout(
            () =>
                api("manage/operations/" + tab + "?" + query)
                    .then((r) => {
                        if (live) setResult(r.data);
                    })
                    .catch((e) => {
                        if (live) setError(e.message);
                    }),
            220,
        );
        return () => {
            live = false;
            clearTimeout(timer);
        };
    }, [tab, branch, page, state, search, from, to, version]);
    useEffect(() => {
        if (!branch) {
            setChoices({});
            return;
        }
        let live = true;
        api("manage/operations/choices?branch_id=" + branch)
            .then((r) => {
                if (live) setChoices(r.data);
            })
            .catch(() => {});
        return () => {
            live = false;
        };
    }, [branch, version]);
    const changed = () => {
        setVersion((v) => v + 1);
        setEditing(null);
        toast("Đã lưu nghiệp vụ.");
    };
    async function action(name: string, payload: Row) {
        await api("manage/operations/" + name, "POST", payload);
        changed();
    }
    const option = (rows: Row[] = []) =>
        rows.map((r) => ({ value: r.id, label: r.name }));
    const amount = (name: string, label: string): Field => ({
        name,
        label,
        type: "number",
        min: 0,
        max: 100000000,
        required: true,
    });
    let fields: Field[] = [],
        title = "",
        submit = "";
    if (editing?.action === "open-shift") {
        title = "Mở ca thu ngân";
        submit = "open-shift";
        fields = [amount("opening_cash", "Tiền mặt đầu ca")];
    }
    if (editing?.action === "close-shift") {
        title = "Chốt ca và kiểm kê tiền mặt";
        submit = "close-shift";
        fields = [
            amount("counted_cash", "Tiền thực đếm cuối ca"),
            {
                name: "notes",
                label: "Giải thích chênh lệch / ghi chú",
                type: "textarea",
                maxLength: 1000,
            },
        ];
    }
    if (editing?.action === "supplier") {
        title = "Thông tin nhà cung cấp";
        submit = "supplier";
        fields = [
            {
                name: "name",
                label: "Tên nhà cung cấp",
                required: true,
                maxLength: 150,
            },
            { name: "phone", label: "Điện thoại", maxLength: 30 },
            { name: "email", label: "Email", type: "email" },
            { name: "address", label: "Địa chỉ", maxLength: 300 },
            { name: "active", label: "Đang hợp tác", type: "checkbox" },
        ];
    }
    if (editing?.action === "purchase") {
        title = "Nhập hàng và ghi nhận tiền đã chi";
        submit = "purchase";
        fields = [
            {
                name: "supplier_id",
                label: "Nhà cung cấp",
                required: true,
                options: option(choices.suppliers),
            },
            {
                name: "product_id",
                label: "Sản phẩm",
                required: true,
                options: option(choices.products),
            },
            amount("quantity", "Số lượng nhập"),
            amount("unit_cost", "Giá nhập mỗi sản phẩm"),
            {
                name: "method",
                label: "Đã thanh toán bằng",
                options: methods,
                required: true,
            },
            { name: "notes", label: "Ghi chú", type: "textarea" },
        ];
    }
    if (editing?.action === "expense") {
        title = "Ghi nhận chi phí đã chi";
        submit = "expense";
        fields = [
            {
                name: "category",
                label: "Nhóm chi phí",
                required: true,
                options: [
                    { value: "rent", label: "Thuê mặt bằng" },
                    { value: "electricity", label: "Điện nước" },
                    { value: "maintenance", label: "Bảo trì" },
                    { value: "salary", label: "Lương" },
                    { value: "other", label: "Khác" },
                ],
            },
            {
                name: "description",
                label: "Nội dung",
                required: true,
                maxLength: 300,
            },
            amount("amount", "Số tiền"),
            {
                name: "incurred_on",
                label: "Ngày chi",
                type: "date",
                required: true,
                max: day(),
            },
            {
                name: "method",
                label: "Phương thức",
                required: true,
                options: methods,
            },
        ];
    }
    if (editing?.action === "payroll") {
        title = "Lập lương nội bộ";
        submit = "payroll";
        fields = [
            {
                name: "user_id",
                label: "Nhân sự tại chi nhánh",
                required: true,
                options: option(choices.staff),
            },
            {
                name: "month",
                label: "Tháng lương (YYYY-MM)",
                required: true,
                maxLength: 7,
            },
            amount("base_amount", "Lương thỏa thuận"),
            amount("allowances", "Phụ cấp"),
            amount("deductions", "Khấu trừ"),
            { name: "notes", label: "Ghi chú", type: "textarea" },
        ];
    }
    if (editing?.action === "pay-payroll") {
        title = "Xác nhận đã chi lương " + editing.month;
        submit = "pay-payroll";
        fields = [
            {
                name: "method",
                label: "Phương thức đã chi",
                options: methods,
                required: true,
            },
        ];
    }
    if (editing?.action === "review-claim") {
        title = "Đối chiếu sao kê ngân hàng";
        submit = "review-claim";
        fields = [
            {
                name: "status",
                label: "Kết quả",
                required: true,
                options: [
                    {
                        value: "approved",
                        label: "Tiền đã vào tài khoản · Xác nhận",
                    },
                    {
                        value: "rejected",
                        label: "Chưa xác nhận được · Từ chối",
                    },
                ],
            },
            {
                name: "notes",
                label: "Kết quả đối chiếu gửi khách hàng",
                type: "textarea",
                required: true,
                maxLength: 1000,
            },
        ];
    }
    if (editing?.action === "reconcile") {
        title = "Chốt khoản thực nhận từ PayOS";
        submit = "reconcile";
        fields = [
            {
                name: "notes",
                label: "Ghi chú đối soát",
                type: "textarea",
                required: true,
                maxLength: 1000,
            },
        ];
    }
    if (editing?.action === "return-unpaid") {
        title = "Gỡ sản phẩm chưa thanh toán khỏi hóa đơn sân";
        submit = "return-unpaid";
        fields = [
            {
                name: "item_id",
                label: "Sản phẩm trả lại kho",
                required: true,
                options: (editing.items ?? [])
                    .filter((r: Row) => r.quantity > r.returned_quantity)
                    .map((r: Row) => ({
                        value: r.id,
                        label:
                            r.name +
                            " · Còn " +
                            (r.quantity - r.returned_quantity),
                    })),
            },
            amount("quantity", "Số lượng trả"),
            {
                name: "notes",
                label: "Lý do trả hàng",
                required: true,
                type: "textarea",
                maxLength: 1000,
            },
        ];
    }
    if (editing?.action === "exchange") {
        title = "Đổi hàng đã thanh toán";
        submit = "exchange";
        fields = [
            {
                name: "item_id",
                label: "Hàng khách trả để đổi",
                required: true,
                options: (editing.items ?? [])
                    .filter((r: Row) => r.quantity > r.returned_quantity)
                    .map((r: Row) => ({
                        value: r.id,
                        label:
                            r.name +
                            " · " +
                            money(r.unit_price) +
                            " · Còn " +
                            (r.quantity - r.returned_quantity),
                    })),
            },
            amount("quantity", "Số lượng trả"),
            {
                name: "replacement_id",
                label: "Hàng thay thế",
                required: true,
                options: (choices.products ?? []).map((p: Row) => ({
                    value: p.id,
                    label: p.name + " · " + money(p.price),
                })),
            },
            amount("replacement_quantity", "Số lượng hàng thay thế"),
            {
                name: "method",
                label: "Thu chênh lệch bằng",
                required: true,
                options: methods,
            },
            {
                name: "notes",
                label: "Lý do đổi hàng",
                type: "textarea",
                required: true,
                maxLength: 1000,
            },
        ];
    }
    return (
        <div className="space-y-5">
            <div className="flex flex-wrap gap-2">
                {Object.entries(labels)
                    .filter(([id]) => allowed(id))
                    .map(([id, label]) => (
                        <button
                            key={id}
                            className={tab === id ? "btn" : "btn-outline"}
                            onClick={() => {
                                setTab(id);
                                if (id === "finance") {
                                    if (!from)
                                        setFrom(day().slice(0, 8) + "01");
                                    if (!to) setTo(day());
                                }
                                setPage(1);
                                setState("");
                                setSearch("");
                            }}
                        >
                            {label}
                        </button>
                    ))}
            </div>
            <Card>
                <h2 className="text-xl font-bold">{labels[tab]}</h2>
                <div className="my-4 flex flex-wrap gap-3">
                    <label>
                        Từ ngày
                        <input
                            className="input block"
                            type="date"
                            value={from}
                            onChange={(e) => {
                                setFrom(e.target.value);
                                setPage(1);
                            }}
                        />
                    </label>
                    <label>
                        Đến ngày
                        <input
                            className="input block"
                            type="date"
                            value={to}
                            min={from}
                            onChange={(e) => {
                                setTo(e.target.value);
                                setPage(1);
                            }}
                        />
                    </label>
                    {tab !== "finance" && (
                        <>
                            <input
                                className="input"
                                placeholder="Tìm trong danh sách…"
                                aria-label="Tìm nghiệp vụ"
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    setPage(1);
                                }}
                            />
                            {!["suppliers", "sales", "expenses"].includes(
                                tab,
                            ) && (
                                <select
                                    className="input"
                                    aria-label="Lọc trạng thái"
                                    value={state}
                                    onChange={(e) => {
                                        setState(e.target.value);
                                        setPage(1);
                                    }}
                                >
                                    <option value="">Tất cả trạng thái</option>
                                    {Object.entries(states)
                                        .filter(([id]) =>
                                            (
                                                (
                                                    ({
                                                        claims: [
                                                            "submitted",
                                                            "approved",
                                                            "rejected",
                                                        ],
                                                        reconciliations: [
                                                            "pending",
                                                            "resolved",
                                                        ],
                                                        shifts: [
                                                            "open",
                                                            "closed",
                                                        ],
                                                        purchases: ["received"],
                                                        payroll: [
                                                            "draft",
                                                            "paid",
                                                        ],
                                                    }) as Record<
                                                        string,
                                                        string[]
                                                    >
                                                )[tab] ?? []
                                            ).includes(id),
                                        )
                                        .map(([id, label]) => (
                                            <option key={id} value={id}>
                                                {label}
                                            </option>
                                        ))}
                                </select>
                            )}
                            <button
                                className="btn-outline"
                                onClick={() => {
                                    setFrom("");
                                    setTo("");
                                    setPage(1);
                                }}
                            >
                                Tất cả thời gian
                            </button>
                        </>
                    )}
                    {(
                        {
                            shifts: "open-shift",
                            suppliers: "supplier",
                            purchases: "purchase",
                            expenses: "expense",
                            payroll: "payroll",
                        } as Row
                    )[tab] && (
                        <button
                            className="btn"
                            disabled={!branch}
                            title={
                                !branch
                                    ? "Chọn một chi nhánh để tạo nghiệp vụ"
                                    : undefined
                            }
                            onClick={() =>
                                setEditing({
                                    action: (
                                        {
                                            shifts: "open-shift",
                                            suppliers: "supplier",
                                            purchases: "purchase",
                                            expenses: "expense",
                                            payroll: "payroll",
                                        } as Row
                                    )[tab],
                                    active: true,
                                    amount: "",
                                    quantity: 1,
                                    allowances: 0,
                                    deductions: 0,
                                    month: day().slice(0, 7),
                                    incurred_on: day(),
                                    method: "cash",
                                })
                            }
                        >
                            +{" "}
                            {
                                (
                                    {
                                        shifts: "Mở ca",
                                        suppliers: "Nhà cung cấp",
                                        purchases: "Phiếu nhập",
                                        expenses: "Khoản chi",
                                        payroll: "Bảng lương",
                                    } as Row
                                )[tab]
                            }
                        </button>
                    )}
                </div>
                {error && (
                    <p role="alert" className="field-error">
                        {error}{" "}
                        <button onClick={() => setVersion((v) => v + 1)}>
                            Thử lại
                        </button>
                    </p>
                )}
                {!result && !error && <Empty text="Đang tải danh sách…" />}
                {result && tab === "finance" && (
                    <>
                        <div className="grid gap-3 md:grid-cols-4">
                            {[
                                [
                                    "Thu từ sân, hàng và dịch vụ",
                                    result.collected,
                                ],
                                ["Chi vận hành", result.expenses],
                                ["Tiền nhập kho", result.purchases],
                                ["Chênh lệch thu chi", result.difference],
                            ].map(([label, value]) => (
                                <Card key={String(label)}>
                                    {label}
                                    <b className="mt-3 block text-xl">
                                        {money(value)}
                                    </b>
                                </Card>
                            ))}
                        </div>
                        <p className="mt-4 text-sm text-slate-600">
                            {result.basis}
                        </p>
                        <a
                            className="btn-outline mt-4"
                            href={
                                "api/v1/manage/operations/finance?" +
                                query +
                                "&format=pdf"
                            }
                        >
                            Tải PDF thu chi
                        </a>
                    </>
                )}
                {result && tab !== "finance" && (
                    <>
                        <Table
                            headers={[
                                "Nội dung",
                                "Số tiền / thông tin",
                                "Trạng thái",
                                "Thao tác",
                            ]}
                            pageSize={null}
                        >
                            {result.data.map((r: Row) => (
                                <tr key={r.id}>
                                    {[
                                        <>
                                            <b>
                                                {r.reference ||
                                                    r.name ||
                                                    r.description ||
                                                    (tab === "payroll"
                                                        ? "Tháng " + r.month
                                                        : "Ca #" + r.id)}
                                            </b>
                                            <p className="text-xs text-slate-500">
                                                {stamp(r.created_at)}{" "}
                                                {r.customer_name ||
                                                    r.employee_name ||
                                                    r.supplier_name ||
                                                    ""}
                                            </p>
                                            {tab === "purchases" &&
                                                r.items?.map((item: Row) => (
                                                    <p
                                                        key={item.id}
                                                        className="mt-2 text-sm"
                                                    >
                                                        {item.name} ×{" "}
                                                        {item.quantity} ·{" "}
                                                        {money(item.unit_cost)}{" "}
                                                        / đơn vị
                                                    </p>
                                                ))}
                                            {r.notes && (
                                                <p className="mt-2 text-sm">
                                                    {r.notes}
                                                </p>
                                            )}
                                            {tab === "claims" && (
                                                <>
                                                    {r.bank_reference}
                                                    <div className="flex gap-2">
                                                        {r.media_ids?.map(
                                                            (id: number) => (
                                                                <a
                                                                    key={id}
                                                                    href={
                                                                        "api/v1/media/" +
                                                                        id
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    Xem ảnh đối
                                                                    chiếu #{id}
                                                                </a>
                                                            ),
                                                        )}
                                                    </div>
                                                </>
                                            )}
                                        </>,
                                        tab === "shifts" ? (
                                            <>
                                                Đầu ca: {money(r.opening_cash)}
                                                <br />
                                                Dự kiến:{" "}
                                                {money(r.expected_cash)}
                                                {r.status === "closed" && (
                                                    <>
                                                        <br />
                                                        Thực đếm:{" "}
                                                        {money(r.counted_cash)}
                                                        <br />
                                                        Chênh lệch:{" "}
                                                        {money(r.difference)}
                                                    </>
                                                )}
                                            </>
                                        ) : tab === "reconciliations" ? (
                                            <>
                                                Yêu cầu:{" "}
                                                {money(r.expected_amount)}
                                                <br />
                                                Ngân hàng báo:{" "}
                                                {money(r.received_amount)}
                                                <p>{r.reason}</p>
                                            </>
                                        ) : tab === "suppliers" ? (
                                            <>
                                                {r.phone}
                                                <br />
                                                {r.email}
                                                <br />
                                                {r.address}
                                            </>
                                        ) : (
                                            money(
                                                r.total_amount ??
                                                    r.net_amount ??
                                                    r.amount,
                                            )
                                        ),
                                        states[r.status] ||
                                            (tab === "suppliers"
                                                ? r.active
                                                    ? "Đang hợp tác"
                                                    : "Ngừng hợp tác"
                                                : "Đã ghi nhận"),
                                        <div className="flex flex-wrap gap-2">
                                            {tab === "shifts" &&
                                                r.status === "open" &&
                                                (manager ||
                                                    r.user_id ===
                                                        data!.user.id) && (
                                                    <button
                                                        className="btn-outline"
                                                        onClick={() =>
                                                            setEditing({
                                                                ...r,
                                                                action: "close-shift",
                                                            })
                                                        }
                                                    >
                                                        Chốt ca
                                                    </button>
                                                )}
                                            {tab === "suppliers" && (
                                                <button
                                                    className="btn-outline"
                                                    onClick={() =>
                                                        setEditing({
                                                            ...r,
                                                            active: !!r.active,
                                                            action: "supplier",
                                                        })
                                                    }
                                                >
                                                    Chỉnh sửa
                                                </button>
                                            )}
                                            {tab === "payroll" &&
                                                r.status === "draft" && (
                                                    <button
                                                        className="btn-outline"
                                                        onClick={() =>
                                                            setEditing({
                                                                ...r,
                                                                action: "pay-payroll",
                                                                method: "bank",
                                                            })
                                                        }
                                                    >
                                                        Ghi nhận đã chi
                                                    </button>
                                                )}
                                            {tab === "claims" &&
                                                r.status === "submitted" && (
                                                    <button
                                                        className="btn-outline"
                                                        onClick={() =>
                                                            setEditing({
                                                                ...r,
                                                                action: "review-claim",
                                                            })
                                                        }
                                                    >
                                                        Đối chiếu sao kê
                                                    </button>
                                                )}
                                            {tab === "reconciliations" &&
                                                r.status === "pending" && (
                                                    <button
                                                        className="btn-outline"
                                                        onClick={() =>
                                                            setEditing({
                                                                ...r,
                                                                action: "reconcile",
                                                            })
                                                        }
                                                    >
                                                        Đối soát PayOS
                                                    </button>
                                                )}
                                            {tab === "sales" &&
                                                r.booking_id && (
                                                    <button
                                                        className="btn-outline"
                                                        disabled={
                                                            loading || !branch
                                                        }
                                                        onClick={() =>
                                                            run(async () => {
                                                                const receipt =
                                                                    (
                                                                        await api(
                                                                            "manage/sales/" +
                                                                                r.id +
                                                                                "/receipt",
                                                                        )
                                                                    ).data;
                                                                setEditing({
                                                                    ...receipt,
                                                                    action: "return-unpaid",
                                                                    quantity: 1,
                                                                });
                                                            })
                                                        }
                                                    >
                                                        Gỡ hàng chưa thu tiền
                                                    </button>
                                                )}
                                            {tab === "sales" &&
                                                !r.booking_id && (
                                                    <button
                                                        className="btn-outline"
                                                        disabled={
                                                            loading || !branch
                                                        }
                                                        onClick={() =>
                                                            run(async () => {
                                                                const receipt =
                                                                    (
                                                                        await api(
                                                                            "manage/sales/" +
                                                                                r.id +
                                                                                "/receipt",
                                                                        )
                                                                    ).data;
                                                                setEditing({
                                                                    ...receipt,
                                                                    action: "exchange",
                                                                    quantity: 1,
                                                                    replacement_quantity: 1,
                                                                    method: "cash",
                                                                });
                                                            })
                                                        }
                                                    >
                                                        Đổi sang sản phẩm khác
                                                    </button>
                                                )}
                                        </div>,
                                    ].map((value, index) => (
                                        <td key={index} className={cell}>
                                            {value}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </Table>
                        {!result.data.length && (
                            <Empty text="Chưa có nội dung phù hợp bộ lọc." />
                        )}
                        {result.last_page > 1 && (
                            <div className="mt-4 flex items-center gap-4">
                                <button
                                    className="btn-outline"
                                    disabled={page <= 1}
                                    onClick={() => setPage(page - 1)}
                                >
                                    Trước
                                </button>
                                <span>
                                    Trang {page}/{result.last_page} ·{" "}
                                    {result.total} mục
                                </span>
                                <button
                                    className="btn-outline"
                                    disabled={page >= result.last_page}
                                    onClick={() => setPage(page + 1)}
                                >
                                    Tiếp
                                </button>
                            </div>
                        )}
                    </>
                )}
            </Card>
            {editing && (
                <Editor
                    title={title}
                    fields={fields}
                    initial={editing}
                    description={
                        editing.action === "review-claim"
                            ? "Chỉ xác nhận sau khi kiểm tra tiền thực nhận trong tài khoản ngân hàng; ảnh chuyển khoản chưa đủ để xác nhận."
                            : editing.action === "reconcile"
                              ? "Hệ thống đọc lại giao dịch có chữ ký từ PayOS. Chỉ ghi nhận số tiền ngân hàng xác nhận; phần còn thiếu vẫn phải thanh toán."
                              : undefined
                    }
                    close={() => setEditing(null)}
                    save={async (d) => {
                        const values = Object.fromEntries(
                            fields
                                .filter(
                                    (f) =>
                                        d[f.name] !== undefined &&
                                        d[f.name] !== "",
                                )
                                .map((f) => [
                                    f.name,
                                    f.type === "number"
                                        ? Number(d[f.name])
                                        : d[f.name],
                                ]),
                        );
                        let payload: Row = {
                            ...values,
                            request_key: d.request_key || key(),
                            branch_id: editing.branch_id || branch,
                        };
                        if (
                            [
                                "close-shift",
                                "supplier",
                                "review-claim",
                                "pay-payroll",
                                "reconcile",
                            ].includes(submit) &&
                            editing.id
                        )
                            payload.id = editing.id;
                        if (submit === "purchase") {
                            payload.items = [
                                {
                                    product_id: Number(payload.product_id),
                                    quantity: Number(payload.quantity),
                                    unit_cost: Number(payload.unit_cost),
                                },
                            ];
                            delete payload.product_id;
                            delete payload.quantity;
                            delete payload.unit_cost;
                            payload.supplier_id = Number(payload.supplier_id);
                        }
                        if (submit === "payroll")
                            payload.user_id = Number(payload.user_id);
                        if (submit === "return-unpaid") {
                            payload.sale_id = editing.id;
                            payload.items = [
                                {
                                    id: Number(payload.item_id),
                                    quantity: Number(payload.quantity),
                                },
                            ];
                            delete payload.item_id;
                            delete payload.quantity;
                        }
                        if (submit === "exchange") {
                            payload.sale_id = editing.id;
                            payload.items = [
                                {
                                    id: Number(payload.item_id),
                                    quantity: Number(payload.quantity),
                                },
                            ];
                            payload.replacements = [
                                {
                                    product_id: Number(payload.replacement_id),
                                    quantity: Number(
                                        payload.replacement_quantity,
                                    ),
                                },
                            ];
                            delete payload.item_id;
                            delete payload.quantity;
                            delete payload.replacement_id;
                            delete payload.replacement_quantity;
                            const quote = (
                                await api(
                                    "manage/operations/exchange-quote",
                                    "POST",
                                    payload,
                                )
                            ).data;
                            setExchangePreview({ ...quote, payload });
                            setEditing(null);
                            return;
                        }
                        await action(submit, payload);
                    }}
                />
            )}
            {exchangePreview && (
                <Modal
                    title="Xác nhận đổi hàng"
                    close={() => setExchangePreview(null)}
                >
                    <dl className="space-y-4">
                        <div className="flex justify-between">
                            <dt>Giá trị hàng trả</dt>
                            <dd>{money(exchangePreview.credit)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt>Giá trị hàng nhận mới</dt>
                            <dd>{money(exchangePreview.total)}</dd>
                        </div>
                        <div className="flex justify-between font-bold">
                            <dt>Cần thu thêm</dt>
                            <dd>{money(exchangePreview.difference)}</dd>
                        </div>
                    </dl>
                    <p className="my-5 text-sm text-slate-600">
                        Kiểm tra hàng trả đủ điều kiện nhập lại kho. Chỉ xác
                        nhận sau khi đã thu phần chênh lệch; hệ thống lưu cả đơn
                        cũ và đơn thay thế.
                    </p>
                    <button
                        className="btn"
                        disabled={loading}
                        onClick={() =>
                            run(async () => {
                                await action("exchange", {
                                    ...exchangePreview.payload,
                                    expected_difference:
                                        exchangePreview.difference,
                                });
                                setExchangePreview(null);
                            })
                        }
                    >
                        {exchangePreview.difference > 0
                            ? "Đã thu chênh lệch · Xác nhận đổi"
                            : "Xác nhận đổi ngang giá"}
                    </button>
                </Modal>
            )}
        </div>
    );
}
