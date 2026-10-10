import { AccountExtras } from "./FeaturePages";
import { useEffect, useState } from "react";
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
        api("bookings/" + booking.id + "/receipt")
            .then((r) => setReceipt(r.data))
            .catch((e) => toast(e.message));
        api("bookings/" + booking.id + "/checkin-token")
            .then((r) => QRCode.toDataURL(r.data.token, { width: 240 }))
            .then(setQr)
            .catch((e) => toast(e.message));
    }, [booking.id]);
    async function refreshPayment() {
        setReceipt((await api("bookings/" + booking.id + "/receipt")).data);
        await refreshBookings();
        await refresh();
    }
    useEffect(() => {
        if (
            !data?.bank ||
            booking.status !== "confirmed" ||
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
                Trạng thái: {statusText(booking.status)}{" "}
                {booking.refund_pending ? "· Chờ quầy hoàn tiền" : ""}
            </p>
            {booking.deposit_remaining > 0 && booking.payment_expires_at && (
                <p className="mt-4 rounded-xl bg-amber-50 p-4 text-sm">
                    Cần đặt cọc {money(booking.deposit_remaining)} trước{" "}
                    {stamp(booking.payment_expires_at)} để giữ lịch.
                </p>
            )}
            {data!.bank &&
                booking.status === "confirmed" &&
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
                            Chuyển khoản đúng nội dung để tiền được ghi nhận vào
                            lịch. Trạng thái tự cập nhật khi ngân hàng xác nhận.
                        </p>
                    </Card>
                )}
            {data!.user.id === booking.user_id &&
                data!.momo_enabled &&
                receipt?.pending_payment_attempts?.map((attempt: Row) => (
                    <button
                        key={attempt.id}
                        className="btn-outline mt-4"
                        disabled={loading}
                        onClick={() =>
                            run(async () => {
                                await api(
                                    "payments/momo/" + attempt.id + "/sync",
                                    "POST",
                                );
                                await refreshPayment();
                            })
                        }
                    >
                        Kiểm tra thanh toán MoMo · {money(attempt.amount)}
                    </button>
                ))}
            {data!.user.role !== "customer" &&
                data!.permissions?.["payments.write"] &&
                booking.status === "cancelled" &&
                booking.paid_amount > 0 &&
                data!.momo_enabled &&
                receipt?.payment_attempts?.length > 0 && (
                    <div className="my-4">
                        <button
                            className="btn-outline"
                            onClick={() => setForm("momo-refund")}
                        >
                            Hoàn qua MoMo
                        </button>
                        {receipt?.refund_requests
                            ?.filter((v: Row) => v.status === "pending")
                            .map((v: Row) => (
                                <button
                                    key={v.id}
                                    className="btn-outline ml-2"
                                    onClick={() =>
                                        run(async () => {
                                            await api(
                                                "manage/bookings/" +
                                                    booking.id +
                                                    "/momo-refund",
                                                "POST",
                                                {
                                                    request_key: v.request_key,
                                                    attempt_id:
                                                        v.payment_attempt_id,
                                                    amount: v.amount,
                                                },
                                            );
                                            await refreshBookings();
                                            setReceipt(
                                                (
                                                    await api(
                                                        "bookings/" +
                                                            booking.id +
                                                            "/receipt",
                                                    )
                                                ).data,
                                            );
                                            toast("Đã kiểm tra kết quả hoàn.");
                                        })
                                    }
                                >
                                    Kiểm tra hoàn {money(v.amount)}
                                </button>
                            ))}
                    </div>
                )}
            {qr && (
                <div className="my-4 text-center">
                    <img src={qr} alt="Mã QR nhận sân" className="mx-auto" />
                    <small>Đưa mã QR cho nhân viên khi đến sân.</small>
                </div>
            )}
            <div className="my-4 flex flex-wrap gap-2">
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
                {booking.deposit_remaining > 0 &&
                    booking.status === "confirmed" &&
                    data!.momo_enabled &&
                    data!.user.id === booking.user_id && (
                        <button
                            className="btn-outline"
                            onClick={() =>
                                run(async () => {
                                    const r = await api(
                                        "payments/momo",
                                        "POST",
                                        {
                                            request_key: key(),
                                            purpose: "booking",
                                            booking_id: booking.id,
                                            deposit_only: true,
                                        },
                                    );
                                    location.assign(r.data.pay_url);
                                })
                            }
                        >
                            Đặt cọc MoMo
                        </button>
                    )}
                {booking.status === "confirmed" && (
                    <>
                        {booking.remaining_amount > 0 && (
                            <>
                                <button
                                    className="btn"
                                    onClick={() => setForm("wallet")}
                                >
                                    Trả bằng ví
                                </button>
                                {data!.momo_enabled && (
                                    <button
                                        className="btn-outline"
                                        onClick={() =>
                                            run(async () => {
                                                const p = await api(
                                                    "payments/momo",
                                                    "POST",
                                                    {
                                                        request_key: key(),
                                                        purpose: "booking",
                                                        booking_id: booking.id,
                                                    },
                                                );
                                                location.assign(p.data.pay_url);
                                            })
                                        }
                                    >
                                        Thanh toán MoMo
                                    </button>
                                )}
                            </>
                        )}
                        {!booking.paid_amount && (
                            <button
                                className="btn-outline"
                                onClick={() => setForm("reschedule")}
                            >
                                Đổi lịch
                            </button>
                        )}
                        <button
                            className="btn-outline text-red-600"
                            onClick={() => setForm("cancel")}
                        >
                            Hủy lịch
                        </button>
                    </>
                )}
                {booking.status === "completed" && (
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
                        Đã thu {money(receipt.collected)} · Đã hoàn{" "}
                        {money(receipt.refunded)}
                    </p>
                    {booking.payments?.map((p: Row) => (
                        <p key={p.id} className="text-xs">
                            {stamp(p.created_at)} · {p.kind} · {p.method} ·{" "}
                            {money(p.amount)}
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
            {form === "momo-refund" && (
                <Editor
                    title="Hoàn tiền qua MoMo"
                    initial={{
                        attempt_id: receipt?.payment_attempts?.[0]?.id,
                        amount: Math.min(
                            booking.paid_amount,
                            receipt?.payment_attempts?.[0]?.amount ?? 0,
                        ),
                    }}
                    close={() => setForm("")}
                    fields={[
                        {
                            name: "attempt_id",
                            label: "Giao dịch gốc",
                            required: true,
                            options: receipt?.payment_attempts.map(
                                (v: Row) => ({
                                    value: v.id,
                                    label: v.order_id + " · " + money(v.amount),
                                }),
                            ),
                        },
                        {
                            name: "amount",
                            label: "Số tiền hoàn",
                            type: "number",
                            min: 1000,
                            max: booking.paid_amount,
                            required: true,
                        },
                    ]}
                    save={async (d) => {
                        const r = await api(
                            "manage/bookings/" + booking.id + "/momo-refund",
                            "POST",
                            {
                                ...d,
                                attempt_id: Number(d.attempt_id),
                                amount: Number(d.amount),
                            },
                        );
                        setReceipt(
                            (await api("bookings/" + booking.id + "/receipt"))
                                .data,
                        );
                        await refreshBookings();
                        toast(
                            r.data.status === "paid"
                                ? "Đã hoàn tiền."
                                : "Đang chờ kết quả từ MoMo.",
                        );
                    }}
                />
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
                    Bạn xác nhận hủy lịch này? Lịch trả bằng ví cần hủy trước 4
                    giờ để hoàn ví; tiền mặt, ngân hàng hoặc MoMo cần liên hệ
                    quầy.
                </Confirm>
            )}
            {form === "reschedule" && (
                <Editor
                    title="Đổi lịch"
                    close={() => setForm("")}
                    fields={[
                        {
                            name: "starts_at",
                            label: "Bắt đầu",
                            type: "datetime-local",
                            required: true,
                        },
                        {
                            name: "ends_at",
                            label: "Kết thúc",
                            type: "datetime-local",
                            required: true,
                        },
                    ]}
                    save={(d) =>
                        update(() =>
                            api(
                                "bookings/" + booking.id + "/reschedule",
                                "PATCH",
                                d,
                            ),
                        )
                    }
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
    const [tab, setTab] = useState(initialTab),
        [bookings, setBookings] = useState<Row[]>([]),
        [account, setAccount] = useState<Row | null>(null),
        [notifications, setNotifications] = useState<Row[]>([]),
        [selected, setSelected] = useState<Row | null>(null),
        [form, setForm] = useState(""),
        [status, setStatus] = useState(""),
        [emailSent, setEmailSent] = useState(false),
        [page, setPage] = useState(1),
        [lastPage, setLastPage] = useState(1),
        [deletingNotification, setDeletingNotification] = useState<Row | null>(
            null,
        );
    useEffect(() => {
        const followLink = () => {
            const target = location.hash.split("/")[1];
            if (
                location.hash.startsWith("#account/") &&
                [
                    "bookings",
                    "wallet",
                    "membership",
                    "notifications",
                    "profile",
                    "extras",
                    "activity",
                ].includes(target)
            )
                setTab(target);
        };
        window.addEventListener("hashchange", followLink);
        return () => window.removeEventListener("hashchange", followLink);
    }, []);
    async function load() {
        const [b, a, n] = await Promise.all([
            api("bookings?page=" + page + (status ? "&status=" + status : "")),
            api("account"),
            api("notifications"),
        ]);
        setBookings(b.data);
        setLastPage(b.meta?.last_page ?? 1);
        setAccount(a.data);
        setNotifications(n.data.data);
    }
    useEffect(() => {
        load().catch((e) => toast(e.message));
    }, [page, status]);
    async function changed(fn: () => Promise<any>) {
        await fn();
        await refresh();
        await load();
    }
    const user = data!.user;
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
                    ["wallet", "Ví HUB"],
                    ["membership", "Hội viên"],
                    ["notifications", "Thông báo"],
                    ["profile", "Tài khoản"],
                    ["extras", "Quyền lợi & đã lưu"],
                    ["activity", "Bài viết & trận của tôi"],
                ].map(([id, label]) => (
                    <button
                        key={id}
                        aria-pressed={tab === id}
                        className={tab === id ? "btn" : "btn-outline"}
                        onClick={() => setTab(id)}
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
                                            {statusText(b.status)}
                                        </span>
                                    </div>
                                    <p className="my-3">
                                        {b.court?.branch?.district} ·{" "}
                                        {b.court?.name}
                                    </p>
                                    <p className="text-sm text-[#6d8474]">
                                        {stamp(b.starts_at)}
                                    </p>
                                    <div className="mt-4 flex justify-between">
                                        <b>{money(b.total_amount)}</b>
                                        <button
                                            className="text-sm font-bold text-[#16a34a]"
                                            onClick={() => setSelected(b)}
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
                    <h2 className="my-4 text-lg font-bold">Danh sách chờ</h2>
                    {account?.waitlist.map((e: Row) => (
                        <Card className="mb-3" key={e.id}>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <span>
                                    {e.court.name} · {stamp(e.starts_at)} ·{" "}
                                    {statusText(e.status)}
                                </span>
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
                                            {p.branch?.district} ·{" "}
                                            {stamp(p.starts_at)}
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
            {tab === "wallet" && (
                <>
                    <Card>
                        <p>Số dư khả dụng</p>
                        <h2 className="my-3 text-4xl font-bold text-[#16a34a]">
                            {money(user.wallet_balance)}
                        </h2>
                        <button
                            className="btn"
                            disabled={!data!.momo_enabled}
                            onClick={() => setForm("topup")}
                        >
                            Nạp qua MoMo
                        </button>
                        <p className="mt-3 text-sm text-[#6d8474]">
                            Bạn có thể nạp tiền tại quầy.{" "}
                        </p>
                    </Card>
                    <h2 className="my-5 text-xl font-bold">Voucher của bạn</h2>
                    <div className="mb-6 grid gap-3 sm:grid-cols-2">
                        {account?.coupons?.map((c: Row) => (
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
                                              (b: Row) => b.id === c.branch_id,
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
                    <h2 className="my-5 text-xl font-bold">Lịch sử ví</h2>
                    <Table
                        headers={["Thời gian", "Nội dung", "Số tiền", "Số dư"]}
                    >
                        {account?.wallet_entries.map((e: Row) => (
                            <tr key={e.id}>
                                <td className={cell}>{stamp(e.created_at)}</td>
                                <td className={cell}>{e.description}</td>
                                <td className={cell}>{money(e.amount)}</td>
                                <td className={cell}>
                                    {money(e.balance_after)}
                                </td>
                            </tr>
                        ))}
                    </Table>
                </>
            )}
            {tab === "wallet" && account?.payment_attempts?.length > 0 && (
                <>
                    <h2 className="my-5 text-xl font-bold">Giao dịch MoMo</h2>
                    {account!.payment_attempts.map((p: Row) => (
                        <Card key={p.id} className="mb-3">
                            <div className="flex flex-wrap justify-between gap-2">
                                <span>
                                    {money(p.amount)} · {stamp(p.created_at)} ·{" "}
                                    {statusText(p.status)}
                                </span>
                                <button
                                    className="btn-outline text-xs"
                                    onClick={() =>
                                        run(async () => {
                                            await api(
                                                "payments/momo/" +
                                                    p.id +
                                                    "/sync",
                                                "POST",
                                            );
                                            await load();
                                            await refresh();
                                            toast(
                                                "Đã cập nhật trạng thái từ máy chủ.",
                                            );
                                        })
                                    }
                                >
                                    Kiểm tra trạng thái
                                </button>
                            </div>
                            {p.status === "pending" &&
                                p.pay_url &&
                                new Date(p.expires_at).getTime() >
                                    Date.now() && (
                                    <a
                                        className="mt-3 inline-block text-sm font-bold text-[#16a34a]"
                                        href={p.pay_url}
                                    >
                                        Tiếp tục trên MoMo →
                                    </a>
                                )}
                        </Card>
                    ))}
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
                <Card>
                    <h2 className="text-xl font-bold">{user.name}</h2>
                    <p className="my-3">
                        {user.email} · {user.phone}
                    </p>
                    <p>
                        {user.skill_level} ·{" "}
                        {user.public_profile
                            ? "Hồ sơ công khai trong cộng đồng"
                            : "Hồ sơ riêng tư"}
                    </p>
                    <div className="mt-5 flex gap-3">
                        <button
                            className="btn"
                            onClick={() => setForm("profile")}
                        >
                            Sửa hồ sơ
                        </button>
                        <button
                            className="btn-outline"
                            onClick={() => setForm("password")}
                        >
                            Đổi mật khẩu
                        </button>
                        {!user.email_verified_at && data!.email_enabled && (
                            <button
                                className="btn-outline"
                                onClick={() => setForm("verifyEmail")}
                            >
                                Xác nhận email
                            </button>
                        )}
                    </div>
                </Card>
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
                    close={() => setSelected(null)}
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
                            await changed(() =>
                                api("recovery/verify", "POST", {
                                    email: user.email,
                                    code: d.code,
                                }),
                            );
                            toast("Đã xác nhận email.");
                            setEmailSent(false);
                        }}
                    />
                ) : (
                    <Confirm
                        title="Gửi mã xác nhận"
                        close={() => setForm("")}
                        action={async () => {
                            await api("recovery/send", "POST", {
                                email: user.email,
                            });
                            setEmailSent(true);
                            setTimeout(() => setForm("verifyEmail"), 0);
                        }}
                    >
                        Gửi mã tới {user.email}. Nếu chưa nhận được email, liên
                        hệ quản trị để kiểm tra dịch vụ gửi thư.
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
            {form === "topup" && (
                <Editor
                    title="Nạp ví qua MoMo"
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
                    save={async (d) => {
                        const p = await api("payments/momo", "POST", {
                            ...d,
                            purpose: "wallet",
                        });
                        location.assign(p.data.pay_url);
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
