import UsageGuide from "./UsageGuide";
import RescheduleBooking from "./RescheduleBooking";
import HistoryBrowser from "./HistoryBrowser";
import SeriesCancel from "./SeriesCancel";
import Profile from "./Profile";
import { AccountExtras } from "./FeaturePages";
import { useEffect, useRef, useState } from "react";
import QRCode from "qrcode";
import { api, key, useHub } from "./hub";
import { money, stamp, statusText, type Row } from "./data";
import { Card, Confirm, Editor, Empty, Modal, Table, cell } from "./ui";
export function BookingDetail({
    booking: initialBooking,
    close,
    refreshBookings,
}: {
    booking: Row;
    close: () => void;
    refreshBookings: () => Promise<void>;
}) {
    const { run, toast, data, refresh, loading } = useHub();
    const [qr, setQr] = useState(""),
        [form, setForm] = useState(""),
        [receipt, setReceipt] = useState<Row | null>(null);
    const booking = receipt?.booking ?? initialBooking;
    useEffect(() => {
        let live = true;
        api("bookings/" + booking.id + "/receipt")
            .then((r) => {
                if (live) setReceipt(r.data);
            })
            .catch((e) => {
                if (live) toast(e.message);
            });
        api("bookings/" + booking.id + "/checkin-token")
            .then((r) => QRCode.toDataURL(r.data.token, { width: 240 }))
            .then((value) => {
                if (live) setQr(value);
            })
            .catch((e) => {
                if (live) toast(e.message);
            });
        return () => {
            live = false;
        };
    }, [booking.id]);
    async function refreshPayment() {
        setReceipt((await api("bookings/" + booking.id + "/receipt")).data);
        await refreshBookings();
        await refresh();
    }
    useEffect(() => {
        if (
            !data?.bank ||
            !["confirmed", "checked_in"].includes(booking.status) ||
            booking.remaining_amount <= 0
        )
            return;
        let active = true;
        const timer = window.setInterval(() => {
            api("bookings/" + booking.id + "/receipt")
                .then((r) => {
                    if (active) setReceipt(r.data);
                })
                .catch(() => {});
        }, 15000);
        return () => {
            active = false;
            window.clearInterval(timer);
        };
    }, [booking.id, booking.status, booking.remaining_amount, !!data?.bank]);
    async function update(fn: () => Promise<any>) {
        await fn();
        await refresh();
        await refreshBookings();
        close();
    }
    return (
        <Modal title={booking.reference} close={close}>
            {booking.needs_checkout && (
                <div
                    role="status"
                    className="mb-4 rounded-2xl bg-amber-50 p-4 text-amber-900"
                >
                    <b>Buổi chơi đã hết giờ · Chờ chốt hóa đơn</b>
                    <p className="mt-2 text-sm">
                        Kiểm tra tiền sân, sản phẩm và dịch vụ đã sử dụng. Còn
                        cần thanh toán {money(booking.remaining_amount)} trước
                        khi hoàn tất.
                    </p>
                </div>
            )}
            <p className="text-sm text-[#6d8474]">
                {booking.court?.branch?.name} · {booking.court?.name}
            </p>
            <p className="my-3">
                {stamp(booking.starts_at)} → {stamp(booking.ends_at)}
            </p>
            <div className="grid grid-cols-2 gap-3">
                <Card>
                    Tổng phí{" "}
                    <b className="block">{money(booking.total_amount)}</b>
                </Card>
                <Card>
                    Đã trả{" "}
                    <b className="block text-[#16a34a]">
                        {money(booking.paid_amount)}
                    </b>
                </Card>
            </div>
            <p className="mt-3 text-sm">
                Trạng thái: {booking.status_label || statusText(booking.status)}{" "}
                {booking.refund_pending
                    ? "· Cần quầy đối chiếu giao dịch cũ"
                    : ""}
            </p>
            {booking.deposit_remaining > 0 && booking.payment_expires_at && (
                <p className="mt-4 rounded-xl bg-amber-50 p-4 text-sm">
                    Cần đặt cọc {money(booking.deposit_remaining)} trước{" "}
                    {stamp(booking.payment_expires_at)} để giữ lịch.
                </p>
            )}
            {data!.bank &&
                data!.bank.mode !== "demo" &&
                ["confirmed", "checked_in"].includes(booking.status) &&
                booking.remaining_amount > 0 && (
                    <Card className="mt-4">
                        <h3 className="font-bold">Chuyển khoản ngân hàng</h3>
                        <p className="mt-3 text-sm">
                            {data!.bank.name} · {data!.bank.account}
                        </p>
                        <p className="my-2 text-sm">
                            Số tiền:{" "}
                            <b>
                                {money(
                                    booking.deposit_remaining > 0
                                        ? booking.deposit_remaining
                                        : booking.remaining_amount,
                                )}
                            </b>
                        </p>
                        <p className="text-sm">
                            Nội dung: <b>{booking.reference}</b>
                        </p>
                        <img
                            className="mx-auto my-4 w-64 max-w-full rounded-xl"
                            alt="VietQR chuyển khoản cho lịch đặt này"
                            src={
                                "https://img.vietqr.io/image/" +
                                encodeURIComponent(data!.bank.bank_bin) +
                                "-" +
                                encodeURIComponent(data!.bank.account) +
                                "-compact2.png?" +
                                new URLSearchParams({
                                    amount: String(
                                        booking.deposit_remaining > 0
                                            ? booking.deposit_remaining
                                            : booking.remaining_amount,
                                    ),
                                    addInfo: booking.reference.replaceAll(
                                        "-",
                                        "",
                                    ),
                                    accountName: data!.bank.name,
                                }).toString()
                            }
                            onError={(event) => {
                                event.currentTarget.hidden = true;
                            }}
                        />
                        <button
                            className="btn-outline mt-3"
                            onClick={() =>
                                navigator.clipboard
                                    .writeText(booking.reference)
                                    .then(() =>
                                        toast(
                                            "Đã sao chép nội dung chuyển khoản.",
                                        ),
                                    )
                            }
                        >
                            Sao chép nội dung
                        </button>
                        <button
                            className="btn-outline mt-3 ml-2"
                            disabled={loading}
                            onClick={() => run(refreshPayment)}
                        >
                            Kiểm tra chuyển khoản
                        </button>
                        <p className="mt-3 text-xs text-slate-500">
                            {data!.bank.mode === "automatic"
                                ? "Chuyển đúng nội dung; trạng thái cập nhật khi ngân hàng xác nhận tiền vào."
                                : "Sau khi chuyển khoản, liên hệ chi nhánh. Nhân viên kiểm tra tiền vào tài khoản và xác nhận thanh toán; lịch chưa được thanh toán chỉ vì bạn quét QR."}
                        </p>
                    </Card>
                )}

            {(data!.user.id === booking.user_id ||
                data!.permissions?.["payments.write"]) &&
                receipt?.pending_payment_attempts
                    ?.filter((attempt: Row) => attempt.partner_code === "PAYOS")
                    .map((attempt: Row) => (
                        <div
                            key={attempt.id}
                            className="mt-4 flex flex-wrap gap-3"
                        >
                            {attempt.pay_url &&
                                Date.parse(attempt.expires_at) > Date.now() && (
                                    <a
                                        className="btn-outline"
                                        href={attempt.pay_url}
                                    >
                                        Tiếp tục chuyển khoản ·{" "}
                                        {money(attempt.amount)}
                                    </a>
                                )}
                            <button
                                className="btn-outline"
                                disabled={loading}
                                onClick={() =>
                                    run(async () => {
                                        await api(
                                            "payments/payos/" +
                                                attempt.id +
                                                "/sync",
                                            "POST",
                                        );
                                        await refreshPayment();
                                    })
                                }
                            >
                                Kiểm tra chuyển khoản · {money(attempt.amount)}
                            </button>
                        </div>
                    ))}
            {qr && ["confirmed", "checked_in"].includes(booking.status) && (
                <details className="my-4 rounded-2xl border border-slate-200 p-4">
                    <summary className="cursor-pointer font-bold">
                        Mã nhận sân · dùng khi đến chi nhánh
                    </summary>
                    <p className="mt-3 text-sm leading-6 text-slate-500">
                        Đưa mã này cho nhân viên để tìm đúng lịch và xác nhận
                        bạn đã đến sân. Mã nhận sân không dùng để chuyển tiền;
                        mã thanh toán được mở ở nút chuyển khoản PayOS.
                    </p>
                    <img
                        src={qr}
                        alt="Mã nhận sân cho nhân viên quét khi khách đến"
                        className="mx-auto my-3 w-52"
                    />
                    <p className="text-center text-sm">
                        Bạn cũng có thể đọc mã lịch <b>{booking.reference}</b>{" "}
                        cho nhân viên.
                    </p>
                </details>
            )}
            <div className="my-4 flex flex-wrap gap-2">
                {data!.user.role !== "customer" &&
                    data!.permissions?.["bookings.write"] &&
                    ["confirmed", "checked_in"].includes(booking.status) && (
                        <button
                            className="btn-outline"
                            onClick={() => setForm("add-services")}
                        >
                            + Thêm dịch vụ vào hóa đơn sân
                        </button>
                    )}
                {data!.payos_enabled &&
                    (data!.user.id === booking.user_id ||
                        data!.permissions?.["payments.write"]) &&
                    ["confirmed", "checked_in"].includes(booking.status) &&
                    booking.remaining_amount > 0 && (
                        <>
                            {(booking.deposit_remaining > 0
                                ? [true, false]
                                : [false]
                            ).map((deposit) => (
                                <button
                                    key={String(deposit)}
                                    className={deposit ? "btn-outline" : "btn"}
                                    disabled={loading}
                                    onClick={() =>
                                        run(async () => {
                                            const r = await api(
                                                "payments/payos",
                                                "POST",
                                                {
                                                    request_key: key(),
                                                    purpose: "booking",
                                                    booking_id: booking.id,
                                                    deposit_only: deposit,
                                                },
                                            );
                                            location.assign(r.data.pay_url);
                                        })
                                    }
                                >
                                    {deposit
                                        ? "Chuyển khoản đặt cọc · " +
                                          money(booking.deposit_remaining)
                                        : "Chuyển khoản toàn bộ · " +
                                          money(booking.remaining_amount)}
                                </button>
                            ))}
                        </>
                    )}
                <a
                    className="btn-outline"
                    href={"/api/v1/bookings/" + booking.id + "/receipt.pdf"}
                >
                    Tải phiếu PDF
                </a>
                <button
                    className="btn-outline"
                    onClick={() =>
                        run(async () => {
                            const r = await api(
                                "bookings/" + booking.id + "/conversation",
                                "POST",
                            );
                            location.hash = "community/chat/" + r.data.id;
                            close();
                        })
                    }
                >
                    Nhắn hỗ trợ chi nhánh
                </button>
                {booking.deposit_remaining > 0 &&
                    booking.status === "confirmed" &&
                    data!.user.id === booking.user_id && (
                        <button
                            className="btn-outline"
                            onClick={() => setForm("wallet-deposit")}
                        >
                            Đặt cọc bằng ví
                        </button>
                    )}

                {["confirmed", "checked_in"].includes(booking.status) && (
                    <>
                        {booking.remaining_amount > 0 && (
                            <>
                                <button
                                    className="btn"
                                    onClick={() => setForm("wallet")}
                                >
                                    Trả bằng ví
                                </button>
                            </>
                        )}
                        {booking.status === "confirmed" && (
                            <button
                                className="btn-outline"
                                onClick={() => setForm("reschedule")}
                            >
                                Đổi lịch
                            </button>
                        )}
                        {booking.status === "confirmed" &&
                            booking.paid_amount === 0 && (
                                <button
                                    className="btn-outline text-red-600"
                                    onClick={() => setForm("cancel")}
                                >
                                    Hủy lịch
                                </button>
                            )}
                        {booking.status === "confirmed" &&
                            booking.series_key &&
                            booking.paid_amount === 0 && (
                                <button
                                    className="btn-outline text-red-600"
                                    onClick={() => setForm("cancel-series")}
                                >
                                    Hủy các buổi còn lại
                                </button>
                            )}
                    </>
                )}
                {booking.status === "completed" &&
                    data!.user.id === booking.user_id &&
                    data!.user.role === "customer" && (
                        <button
                            className="btn-outline"
                            onClick={() => setForm("review")}
                        >
                            Viết đánh giá
                        </button>
                    )}
                <button className="btn-outline" onClick={() => window.print()}>
                    In hóa đơn
                </button>
            </div>
            {receipt && (
                <div className="print-receipt space-y-2 border-t border-[#dce4dc] pt-4">
                    <h3 className="font-bold">Hóa đơn · {receipt.reference}</h3>
                    <p>
                        {receipt.customer?.name} · {receipt.customer?.phone}
                    </p>
                    {booking.services?.map((s: Row) => (
                        <p className="flex justify-between text-sm" key={s.id}>
                            <span>
                                {s.name} × {s.quantity}
                            </span>
                            <span>{money(s.amount)}</span>
                        </p>
                    ))}
                    <p className="flex justify-between text-sm">
                        <span>Tiền sân</span>
                        <span>
                            {money(
                                receipt.booking?.court_amount ??
                                    booking.court_amount,
                            )}
                        </span>
                    </p>
                    {receipt.booking?.product_items?.map((i: Row) => (
                        <p className="flex justify-between text-sm" key={i.id}>
                            <span>
                                {i.name} × {i.quantity}
                            </span>
                            <span>{money(i.amount)}</span>
                        </p>
                    ))}
                    <p className="flex justify-between text-sm">
                        <span>Ưu đãi</span>
                        <span>{money(booking.discount_amount)}</span>
                    </p>
                    <p>
                        Đã thu {money(receipt.collected)}
                        {receipt.refunded > 0 && (
                            <> · Đã hoàn trước đây {money(receipt.refunded)}</>
                        )}
                    </p>
                    {booking.payments?.map((p: Row) => (
                        <p key={p.id} className="text-xs">
                            {stamp(p.created_at)} ·{" "}
                            {p.kind === "refund" ? "Hoàn tiền" : "Thu tiền"} ·{" "}
                            {statusText(p.method)} · {money(p.amount)}
                        </p>
                    ))}
                </div>
            )}
            {(form === "wallet" || form === "wallet-deposit") && (
                <Confirm
                    title="Thanh toán bằng ví"
                    close={() => setForm("")}
                    action={(requestKey) =>
                        update(() =>
                            api(
                                "bookings/" + booking.id + "/wallet-payment",
                                "POST",
                                {
                                    request_key: requestKey,
                                    deposit_only: form === "wallet-deposit",
                                },
                            ),
                        )
                    }
                >
                    Trừ{" "}
                    {money(
                        form === "wallet-deposit"
                            ? booking.deposit_remaining
                            : booking.remaining_amount,
                    )}{" "}
                    từ ví HUB. Số dư hiện tại {money(data!.user.wallet_balance)}
                    .
                </Confirm>
            )}

            {form === "cancel" && (
                <Confirm
                    title="Hủy lịch sân"
                    close={() => setForm("")}
                    action={() =>
                        update(() =>
                            api("bookings/" + booking.id + "/status", "PATCH", {
                                status: "cancelled",
                            }),
                        )
                    }
                >
                    Bạn xác nhận hủy lịch chưa thanh toán này? Khung giờ sẽ được
                    giải phóng sau khi hủy.
                </Confirm>
            )}
            {form === "add-services" && (
                <Editor
                    title="Thêm dịch vụ phát sinh"
                    description="Dịch vụ được cộng nguyên giá vào hóa đơn cuối buổi. Ưu đãi chỉ áp dụng cho tiền sân."
                    close={() => setForm("")}
                    initial={{ service_id: "", quantity: 1 }}
                    fields={[
                        {
                            name: "service_id",
                            label: "Dịch vụ tại chi nhánh",
                            required: true,
                            options: (
                                data!.branches.find(
                                    (b: Row) =>
                                        b.id === booking.court?.branch_id,
                                )?.services ?? []
                            )
                                .filter(
                                    (s: Row) => s.name !== "Huấn luyện viên",
                                )
                                .map((s: Row) => ({
                                    value: s.id,
                                    label:
                                        s.name +
                                        " · " +
                                        money(s.price) +
                                        "/" +
                                        s.unit,
                                })),
                        },
                        {
                            name: "quantity",
                            label: "Số lượng thêm",
                            type: "number",
                            required: true,
                            min: 1,
                            max: 100,
                            step: 1,
                        },
                    ]}
                    save={async (values) => {
                        await api(
                            "manage/bookings/" + booking.id + "/services",
                            "POST",
                            {
                                request_key: values.request_key,
                                services: [
                                    {
                                        id: Number(values.service_id),
                                        quantity: Number(values.quantity),
                                    },
                                ],
                            },
                        );
                        setForm("");
                        await refreshPayment();
                        toast(
                            "Đã cộng dịch vụ vào hóa đơn, không áp dụng giảm giá dịch vụ.",
                        );
                    }}
                />
            )}
            {form === "reschedule" && (
                <RescheduleBooking
                    booking={booking}
                    close={() => setForm("")}
                    after={refreshPayment}
                />
            )}
            {form === "cancel-series" && (
                <SeriesCancel
                    booking={booking}
                    close={() => setForm("")}
                    after={refreshPayment}
                />
            )}
            {form === "review" && (
                <Editor
                    title="Đánh giá buổi chơi"
                    close={() => setForm("")}
                    initial={{ rating: 5 }}
                    fields={[
                        {
                            name: "images",
                            label: "Ảnh buổi chơi",
                            type: "images",
                        },
                        {
                            name: "rating",
                            label: "Số sao",
                            type: "number",
                            min: 1,
                            max: 5,
                            required: true,
                        },
                        {
                            name: "content",
                            label: "Nhận xét",
                            type: "textarea",
                            required: true,
                        },
                    ]}
                    save={async (d) => {
                        await api(
                            "bookings/" + booking.id + "/review",
                            "POST",
                            d,
                        );
                        toast("Đã lưu đánh giá.");
                    }}
                />
            )}
        </Modal>
    );
}
export default function Account({
    book,
    initialTab = "bookings",
}: {
    book: () => void;
    initialTab?: string;
}) {
    const { data, refresh, toast, run } = useHub();
    const loadVersion = useRef(0);
    const [tab, setTab] = useState(initialTab),
        [bookings, setBookings] = useState<Row[]>([]),
        [account, setAccount] = useState<Row | null>(null),
        [notifications, setNotifications] = useState<Row[]>([]),
        [selected, setSelected] = useState<Row | null>(null),
        [form, setForm] = useState(""),
        [status, setStatus] = useState(""),
        [pendingOnly, setPendingOnly] = useState(
            location.hash.endsWith("/pending"),
        ),
        [emailSent, setEmailSent] = useState(false),
        [page, setPage] = useState(1),
        [couponPage, setCouponPage] = useState(1),
        [paymentVersion, setPaymentVersion] = useState(0),
        [lastPage, setLastPage] = useState(1),
        [deletingNotification, setDeletingNotification] = useState<Row | null>(
            null,
        );
    useEffect(() => {
        let live = true;
        const followLink = () => {
            const hash = location.hash;
            const target = location.hash.split("/")[1];
            if (
                location.hash.startsWith("#account/") &&
                [
                    "bookings",
                    "waiting",
                    "wallet",
                    "membership",
                    "notifications",
                    "profile",
                    "extras",
                    "activity",
                    "guide",
                ].includes(target)
            ) {
                setTab(target);
                setPendingOnly(location.hash.endsWith("/pending"));
            }
            const detail = location.hash.match(/^#account\/bookings\/(\d+)$/);
            if (detail)
                api("bookings/" + detail[1])
                    .then((r) => {
                        if (live && location.hash === hash) setSelected(r.data);
                    })
                    .catch((e) => {
                        if (live && location.hash === hash) toast(e.message);
                    });
            else setSelected(null);
        };
        followLink();
        window.addEventListener("hashchange", followLink);
        return () => {
            live = false;
            loadVersion.current++;
            window.removeEventListener("hashchange", followLink);
        };
    }, []);
    async function load() {
        const version = ++loadVersion.current;
        const [b, a, n] = await Promise.all([
            api(
                "bookings?page=" +
                    page +
                    (status ? "&status=" + status : "") +
                    (pendingOnly ? "&pending=1" : ""),
            ),
            api("account"),
            api("notifications"),
        ]);
        if (version !== loadVersion.current) return;
        setBookings(b.data);
        setLastPage(b.meta?.last_page ?? 1);
        setAccount(a.data);
        setNotifications(n.data.data);
    }
    useEffect(() => {
        let live = true;
        load().catch((e) => {
            if (live) toast(e.message);
        });
        return () => {
            live = false;
        };
    }, [page, status, pendingOnly]);
    async function changed(fn: () => Promise<any>) {
        await fn();
        await refresh();
        await load();
    }
    const user = data!.user;
    const couponPages = Math.max(
        1,
        Math.ceil((account?.coupons?.length ?? 0) / 6),
    );
    const currentCouponPage = Math.min(couponPage, couponPages);
    return (
        <div className="product-page mx-auto max-w-6xl p-5 md:p-10">
            <div className="page-heading">
                <div>
                    <p className="eyebrow text-[#16a34a]">GÓC CỦA BẠN</p>
                    <h1 className="font-display text-3xl font-bold">
                        Xin chào, {user.name}
                    </h1>
                    <p className="mt-2 text-[#6d8474]">
                        {account?.membership
                            ? "Hội viên " +
                              account.membership.plan.name +
                              " đến " +
                              stamp(account.membership.expires_at)
                            : "Hội viên Basic"}
                    </p>
                </div>
                <button className="btn h-fit" onClick={book}>
                    + Đặt sân mới
                </button>
            </div>
            <div className="page-tabs">
                {[
                    ["bookings", "Lịch của tôi"],
                    ["waiting", "Sân đang chờ"],
                    ["wallet", "Ví HUB"],
                    ["membership", "Hội viên"],
                    ["notifications", "Thông báo"],
                    ["profile", "Hồ sơ cá nhân"],
                    ["extras", "Quyền lợi & đã lưu"],
                    ["activity", "Bài viết & trận của tôi"],
                    ["guide", "Hướng dẫn sử dụng"],
                ].map(([id, label]) => (
                    <button
                        key={id}
                        aria-pressed={tab === id}
                        className={tab === id ? "btn" : "btn-outline"}
                        onClick={() => {
                            setTab(id);
                            location.hash = "account/" + id;
                        }}
                    >
                        {label}
                    </button>
                ))}
            </div>
            {tab === "extras" && <AccountExtras />}
            {tab === "bookings" && (
                <>
                    <div className="mb-4 flex gap-3">
                        <select
                            className="input"
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="">Tất cả trạng thái</option>
                            {[
                                "confirmed",
                                "checked_in",
                                "completed",
                                "cancelled",
                            ].map((s) => (
                                <option key={s} value={s}>
                                    {statusText(s)}
                                </option>
                            ))}
                        </select>
                        <button
                            className={pendingOnly ? "btn" : "btn-outline"}
                            aria-pressed={pendingOnly}
                            onClick={() => {
                                setPage(1);
                                setStatus("");
                                location.hash = pendingOnly
                                    ? "account/bookings"
                                    : "account/bookings/pending";
                            }}
                        >
                            Chờ thanh toán
                        </button>
                        <button
                            className="btn-outline"
                            onClick={() => run(load)}
                        >
                            Làm mới
                        </button>
                    </div>
                    {bookings.length ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            {bookings.map((b) => (
                                <Card key={b.id}>
                                    <div className="flex justify-between">
                                        <b>{b.reference}</b>
                                        <span className="badge badge-green">
                                            {b.needs_checkout
                                                ? "Chờ chốt hóa đơn"
                                                : b.status_label ||
                                                  statusText(b.status)}
                                        </span>
                                    </div>
                                    <p className="my-3">
                                        {b.court?.branch?.ward ||
                                            b.court?.branch?.district}{" "}
                                        · {b.court?.name}
                                    </p>
                                    <p className="text-sm text-[#6d8474]">
                                        {stamp(b.starts_at)}
                                    </p>
                                    <div className="mt-4 flex justify-between">
                                        <b>{money(b.total_amount)}</b>
                                        <button
                                            className="text-sm font-bold text-[#16a34a]"
                                            onClick={() => {
                                                setSelected(b);
                                                location.hash =
                                                    "account/bookings/" + b.id;
                                            }}
                                        >
                                            Chi tiết →
                                        </button>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <Empty text="Bạn chưa có lịch đặt sân trong nhóm này." />
                    )}
                    <div className="my-4 flex items-center gap-4">
                        <button
                            className="btn-outline"
                            disabled={page <= 1}
                            onClick={() => setPage(page - 1)}
                        >
                            Trước
                        </button>
                        <span>
                            {page}/{lastPage}
                        </span>
                        <button
                            className="btn-outline"
                            disabled={page >= lastPage}
                            onClick={() => setPage(page + 1)}
                        >
                            Sau
                        </button>
                    </div>
                </>
            )}
            {tab === "waiting" && (
                <>
                    <h2 className="my-4 text-lg font-bold">
                        Sân đang chờ của bạn
                    </h2>
                    <p className="mb-4 text-sm text-[#6d8474]">
                        Thông báo sân trống không tự động đặt sân. Kiểm tra lại
                        lịch và xác nhận đặt khi nhận được thông báo.
                    </p>
                    {!account?.waitlist.length && (
                        <Empty text="Bạn chưa đăng ký chờ sân nào." />
                    )}
                    {account?.waitlist.map((e: Row) => (
                        <Card className="mb-3" key={e.id}>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <span>
                                    <b>
                                        {e.court.branch.name} · {e.court.name}
                                    </b>
                                    <small className="mt-1 block">
                                        {stamp(e.starts_at)} –{" "}
                                        {stamp(e.ends_at)} ·{" "}
                                        {Date.parse(e.ends_at) <= Date.now()
                                            ? "Đã qua khung giờ"
                                            : statusText(e.status)}
                                    </small>
                                </span>
                                {Date.parse(e.ends_at) > Date.now() && (
                                    <a
                                        className="btn-outline"
                                        href={"#branch/" + e.court.branch_id}
                                    >
                                        Xem lịch sân
                                    </a>
                                )}
                                {e.status === "waiting" && (
                                    <button
                                        className="btn-outline"
                                        onClick={() =>
                                            run(() =>
                                                changed(() =>
                                                    api(
                                                        "waitlist/" + e.id,
                                                        "DELETE",
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        Rời danh sách
                                    </button>
                                )}
                            </div>
                        </Card>
                    ))}
                </>
            )}
            {tab === "activity" && (
                <div className="space-y-6">
                    {[
                        ["my_posts", "Bài viết của tôi"],
                        ["my_matches", "Trận tôi tham gia"],
                    ].map(([field, title]) => (
                        <section key={field}>
                            <h2 className="mb-4 text-xl font-bold">{title}</h2>
                            <div className="grid gap-4 md:grid-cols-2">
                                {account?.[field]?.map((p: Row) => (
                                    <Card key={p.id}>
                                        <b>{p.title}</b>
                                        <p className="mt-2 text-sm text-slate-500">
                                            {p.branch?.ward ||
                                                p.branch?.district}{" "}
                                            · {stamp(p.starts_at)}
                                        </p>
                                        <span className="badge badge-green mt-3">
                                            {p.moderation_status === "pending"
                                                ? "Chờ duyệt"
                                                : statusText(p.status)}
                                        </span>
                                        <a
                                            className="btn-outline mt-3 block w-fit"
                                            href={"#community/post/" + p.id}
                                        >
                                            Xem bài & thành viên
                                        </a>
                                    </Card>
                                ))}
                            </div>
                            {!account?.[field]?.length && (
                                <Empty text="Chưa có hoạt động trong nhóm này." />
                            )}
                        </section>
                    ))}
                </div>
            )}
            {tab === "guide" && <UsageGuide />}
            {tab === "wallet" && (
                <>
                    <Card>
                        <p>Số dư khả dụng</p>
                        <h2 className="my-3 text-4xl font-bold text-[#16a34a]">
                            {money(user.wallet_balance)}
                        </h2>
                        <div className="flex flex-wrap gap-3">
                            {data!.payos_enabled && (
                                <button
                                    className="btn"
                                    onClick={() => setForm("payos-topup")}
                                >
                                    Nạp bằng chuyển khoản
                                </button>
                            )}
                        </div>
                        <p className="mt-3 text-sm text-[#6d8474]">
                            {data!.payos_enabled
                                ? "Nạp qua PayOS hoặc tại quầy. Số dư chỉ tăng sau khi khoản tiền được xác nhận."
                                : "Bạn có thể nạp tiền trực tiếp tại quầy."}
                        </p>
                    </Card>
                    <h2 className="my-5 text-xl font-bold">Voucher của bạn</h2>
                    <div className="mb-6 grid gap-3 sm:grid-cols-2">
                        {account?.coupons
                            ?.slice(
                                (currentCouponPage - 1) * 6,
                                currentCouponPage * 6,
                            )
                            .map((c: Row) => (
                                <Card key={c.code}>
                                    <span className="badge badge-green">
                                        {c.kind === "percent"
                                            ? "Giảm " + c.value + "%"
                                            : money(c.value)}
                                    </span>
                                    <b className="mt-3 block">{c.code}</b>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Đơn từ {money(c.minimum_amount)} ·{" "}
                                        {c.branch_id
                                            ? data!.branches.find(
                                                  (b: Row) =>
                                                      b.id === c.branch_id,
                                              )?.district
                                            : "Toàn hệ thống"}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Hết hạn {stamp(c.expires_at)}
                                    </p>
                                    <button
                                        className="btn-outline mt-3"
                                        onClick={() =>
                                            run(async () => {
                                                await navigator.clipboard.writeText(
                                                    c.code,
                                                );
                                                toast("Đã sao chép mã ưu đãi.");
                                            })
                                        }
                                    >
                                        Sao chép mã
                                    </button>
                                </Card>
                            ))}
                    </div>
                    {(account?.coupons?.length ?? 0) > 6 && (
                        <div
                            className="mb-6 flex items-center justify-between gap-3 text-sm"
                            aria-label="Phân trang voucher"
                        >
                            <button
                                className="btn-outline"
                                disabled={currentCouponPage <= 1}
                                onClick={() =>
                                    setCouponPage(currentCouponPage - 1)
                                }
                            >
                                Trước
                            </button>
                            <span>
                                Voucher · Trang {currentCouponPage}/
                                {couponPages}
                            </span>
                            <button
                                className="btn-outline"
                                disabled={currentCouponPage >= couponPages}
                                onClick={() =>
                                    setCouponPage(currentCouponPage + 1)
                                }
                            >
                                Sau
                            </button>
                        </div>
                    )}
                    <HistoryBrowser kind="wallet" />
                    <HistoryBrowser
                        kind="payments"
                        version={paymentVersion}
                        onSelect={(payment) => {
                            if (payment.partner_code !== "PAYOS") {
                                toast(
                                    "Giao dịch lịch sử được giữ để đối chiếu. Liên hệ chi nhánh nếu cần hỗ trợ.",
                                );
                                return;
                            }
                            run(async () => {
                                await api(
                                    "payments/payos" +
                                        "/" +
                                        payment.id +
                                        "/sync",
                                    "POST",
                                );
                                await load();
                                await refresh();
                                setPaymentVersion((value) => value + 1);
                                toast("Đã cập nhật trạng thái từ máy chủ.");
                            });
                        }}
                    />
                </>
            )}
            {tab === "membership" && (
                <div className="grid gap-4 md:grid-cols-3">
                    {data!.plans.map((p: Row) => (
                        <Card key={p.id}>
                            <h2 className="font-display text-2xl font-bold">
                                {p.name}
                            </h2>
                            <p className="my-4 text-2xl font-bold text-[#16a34a]">
                                {money(p.price)}
                            </p>
                            <p>
                                Giảm {p.discount_percent}% phí sân ·{" "}
                                {p.duration_days} ngày
                            </p>
                            <button
                                className="btn mt-5"
                                onClick={() => setForm("plan:" + p.id)}
                            >
                                Mua bằng ví
                            </button>
                        </Card>
                    ))}
                </div>
            )}
            {tab === "notifications" && (
                <>
                    <button
                        className="btn-outline mb-4"
                        onClick={() =>
                            run(() =>
                                changed(() =>
                                    api("notifications/read-all", "PATCH"),
                                ),
                            )
                        }
                    >
                        Đánh dấu tất cả đã đọc
                    </button>
                    {notifications.length ? (
                        notifications.map((n) => (
                            <Card
                                className={
                                    "mb-3 " +
                                    (!n.read_at ? "border-[#16a34a]" : "")
                                }
                                key={n.id}
                            >
                                <div className="flex justify-between gap-3">
                                    <b>{n.data.title}</b>
                                    <small>{stamp(n.created_at)}</small>
                                </div>
                                <p className="mt-2 text-sm">{n.data.body}</p>
                                <div className="mt-3 flex gap-3">
                                    <button
                                        className="text-sm font-bold text-green-700"
                                        onClick={() =>
                                            run(async () => {
                                                await api(
                                                    "notifications/" +
                                                        n.id +
                                                        "/read",
                                                    "PATCH",
                                                );
                                                if (n.data.booking_id) {
                                                    const r = await api(
                                                        "bookings/" +
                                                            n.data.booking_id,
                                                    );
                                                    setSelected(r.data);
                                                } else if (n.data.event_id) {
                                                    location.hash =
                                                        "events/" +
                                                        n.data.event_id;
                                                } else if (n.data.post_id) {
                                                    location.hash =
                                                        "community/post/" +
                                                        n.data.post_id;
                                                }
                                                await refresh();
                                                await load();
                                            })
                                        }
                                    >
                                        Xem chi tiết
                                    </button>
                                    <button
                                        className="text-sm text-slate-500"
                                        onClick={() =>
                                            setDeletingNotification(n)
                                        }
                                    >
                                        Xóa thông báo
                                    </button>
                                </div>

                                {!n.read_at && (
                                    <button
                                        className="mt-3 text-sm text-[#16a34a]"
                                        onClick={() =>
                                            run(() =>
                                                changed(() =>
                                                    api(
                                                        "notifications/" +
                                                            n.id +
                                                            "/read",
                                                        "PATCH",
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        Đã đọc
                                    </button>
                                )}
                            </Card>
                        ))
                    ) : (
                        <Empty />
                    )}
                </>
            )}
            {tab === "profile" && (
                <Profile
                    account={account}
                    edit={() => setForm("profile")}
                    password={() => setForm("password")}
                    verifyEmail={() => setForm("verifyEmail")}
                    changed={changed}
                />
            )}
            {deletingNotification && (
                <Confirm
                    title="Xóa thông báo?"
                    close={() => setDeletingNotification(null)}
                    action={() =>
                        changed(() =>
                            api(
                                "notifications/" + deletingNotification.id,
                                "DELETE",
                            ),
                        )
                    }
                >
                    Thông báo “{deletingNotification.data.title}” sẽ được xóa
                    khỏi danh sách của bạn.
                </Confirm>
            )}
            {selected && (
                <BookingDetail
                    booking={selected}
                    close={() => {
                        setSelected(null);
                        location.hash = pendingOnly
                            ? "account/bookings/pending"
                            : "account/bookings";
                    }}
                    refreshBookings={load}
                />
            )}
            {form === "profile" && (
                <Editor
                    title="Hồ sơ cá nhân"
                    close={() => setForm("")}
                    initial={user}
                    fields={[
                        { name: "name", label: "Họ tên", required: true },
                        {
                            name: "email",
                            label: "Email",
                            type: "email",
                            required: true,
                        },
                        { name: "phone", label: "Điện thoại" },
                        {
                            name: "skill_level",
                            label: "Trình độ",
                            options: ["Sơ cấp", "Trung cấp", "Cao cấp"].map(
                                (value) => ({ value, label: value }),
                            ),
                        },
                        {
                            name: "public_profile",
                            label: "Cho phép người chơi tìm và nhắn tin",
                            type: "checkbox",
                        },
                        {
                            name: "current_password",
                            label: "Mật khẩu hiện tại nếu đổi email",
                            type: "password",
                        },
                    ]}
                    save={(d) => changed(() => api("me", "PATCH", d))}
                />
            )}
            {form === "verifyEmail" &&
                (emailSent ? (
                    <Editor
                        title="Xác nhận email"
                        close={() => setForm("")}
                        fields={[
                            {
                                name: "code",
                                label: "Mã 6 chữ số",
                                required: true,
                            },
                        ]}
                        save={async (d) => {
                            await changed(async () => {
                                const response = await api(
                                    "recovery/verify",
                                    "POST",
                                    {
                                        email: user.email,
                                        code: d.code,
                                    },
                                );
                                toast(response.message);
                            });
                            setEmailSent(false);
                        }}
                    />
                ) : (
                    <Confirm
                        title="Gửi mã xác nhận"
                        close={() => setForm("")}
                        action={async () => {
                            const response = await api(
                                "recovery/send",
                                "POST",
                                {
                                    email: user.email,
                                },
                            );
                            toast(response.message);
                            setEmailSent(true);
                            setTimeout(() => setForm("verifyEmail"), 0);
                        }}
                    >
                        {`Gửi mã tới ${user.email}. Nếu chưa nhận được email, liên hệ quản trị để kiểm tra dịch vụ gửi thư.`}
                    </Confirm>
                ))}
            {form === "password" && (
                <Editor
                    title="Đổi mật khẩu"
                    close={() => setForm("")}
                    fields={[
                        {
                            name: "current_password",
                            label: "Mật khẩu hiện tại",
                            type: "password",
                            required: true,
                        },
                        {
                            name: "password",
                            label: "Mật khẩu mới",
                            type: "password",
                            required: true,
                        },
                        {
                            name: "password_confirmation",
                            label: "Nhập lại",
                            type: "password",
                            required: true,
                        },
                    ]}
                    save={(d) => changed(() => api("me/password", "PUT", d))}
                />
            )}
            {form === "payos-topup" && (
                <Editor
                    title="Nạp ví bằng chuyển khoản"
                    label="Tiếp tục chuyển khoản"
                    description="Số dư cập nhật sau khi ngân hàng xác nhận giao dịch qua PayOS."
                    close={() => setForm("")}
                    initial={{ amount: 100000 }}
                    fields={[
                        {
                            name: "amount",
                            label: "Số tiền",
                            type: "number",
                            min: 1000,
                            max: 50000000,
                            required: true,
                        },
                    ]}
                    save={async (values) => {
                        const response = await api("payments/payos", "POST", {
                            ...values,
                            purpose: "wallet",
                        });
                        location.assign(response.data.pay_url);
                    }}
                />
            )}
            {form.startsWith("plan:") && (
                <Confirm
                    title="Kích hoạt hội viên"
                    close={() => setForm("")}
                    action={(requestKey) =>
                        changed(() =>
                            api(
                                "memberships/" + form.split(":")[1] + "/buy",
                                "POST",
                                { request_key: requestKey },
                            ),
                        )
                    }
                >
                    Phí gói sẽ được trừ từ ví HUB. Kiểm tra số dư trước khi xác
                    nhận.
                </Confirm>
            )}
        </div>
    );
}
