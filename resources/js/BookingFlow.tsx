import Icon from "./Icon";
import CourtMatrix from "./CourtMatrix";
import CustomerPicker from "./CustomerPicker";
import { useEffect, useState } from "react";
import { api, key, useHub } from "./hub";
import { BRANCHES, day, money, type Row } from "./data";
import { Card, Empty, Modal } from "./ui";
export default function BookingFlow({
    initial = {},
    done,
    close,
}: {
    initial?: Row;
    done: (b: Row) => void;
    close: () => void;
}) {
    const { data, run, toast, loading, refresh } = useHub();
    const [branch, setBranch] = useState(initial.branch_id ?? BRANCHES[0]?.id),
        [date, setDate] = useState(initial.date ?? day(1)),
        [time, setTime] = useState(initial.time ?? "18:00"),
        [duration, setDuration] = useState(initial.duration ?? 2),
        [court, setCourt] = useState<Row | null>(null),
        [available, setAvailable] = useState<Row[]>([]),
        [schedule, setSchedule] = useState<Row | null>(null),
        [services, setServices] = useState<Row>({}),
        [coupon, setCoupon] = useState(""),
        [notes, setNotes] = useState(""),
        [quote, setQuote] = useState<Row | null>(null),
        [fixed, setFixed] = useState(false),
        [weeks, setWeeks] = useState(4),
        [hold, setHold] = useState<Row | null>(null),
        [seconds, setSeconds] = useState(0),
        [step, setStep] = useState(0),
        [requestKey] = useState(key),
        [method, setMethod] = useState("cash"),
        [depositOnly, setDepositOnly] = useState(true),
        [customer, setCustomer] = useState(initial.user_id ?? ""),
        [error, setError] = useState("");
    const b = data!.branches.find((b: Row) => b.id === Number(branch));
    const minuteOf = (v: string) =>
        Number(v.slice(0, 2)) * 60 + Number(v.slice(3));
    const timeOptions = Array.from({ length: 48 }, (_, i) => i * 30)
        .filter(
            (m) =>
                m >= (b?.opens_minute ?? 180) &&
                m + duration * 60 <= Math.min(b?.closes_minute ?? 1380, 1410),
        )
        .map(
            (m) =>
                String(Math.floor(m / 60)).padStart(2, "0") +
                ":" +
                String(m % 60).padStart(2, "0"),
        );
    useEffect(() => {
        if (timeOptions.length && !timeOptions.includes(time))
            setTime(timeOptions[0]);
    }, [branch, duration, b?.opens_minute, b?.closes_minute]);
    const start = date + "T" + time + ":00+07:00";
    const [hour, minute] = time.split(":").map(Number);
    const endHour = hour + Math.floor((minute + duration * 60) / 60),
        endMinute = (minute + duration * 60) % 60;
    const end =
        date +
        "T" +
        String(endHour).padStart(2, "0") +
        ":" +
        String(endMinute).padStart(2, "0") +
        ":00+07:00";
    const selected = Object.entries(services)
        .filter(([, v]) => v > 0)
        .map(([id, quantity]) => ({ id: Number(id), quantity }));
    useEffect(() => {
        let active = true;
        setCourt(null);
        setQuote(null);
        setError("");
        api(
            "branches/" +
                branch +
                "/availability?" +
                new URLSearchParams({ starts_at: start, ends_at: end }),
        )
            .then((r) => {
                if (active) {
                    setAvailable(r.data);
                    if (initial.court_id)
                        setCourt(
                            r.data.find(
                                (c: Row) =>
                                    c.id === initial.court_id && c.available,
                            ) ?? null,
                        );
                }
            })
            .catch((e) => {
                if (active) {
                    setAvailable([]);
                    setError(e.message);
                }
            });
        return () => {
            active = false;
        };
    }, [branch, start, end]);
    useEffect(() => {
        let live = true;
        setSchedule(null);
        api("branches/" + branch + "/schedule?date=" + date)
            .then((r) => {
                if (live) setSchedule(r.data);
            })
            .catch((e) => {
                if (live) toast(e.message);
            });
        return () => {
            live = false;
        };
    }, [branch, date]);
    useEffect(() => {
        if (!court) return;
        let active = true;
        const timer = setTimeout(
            () =>
                api("courts/" + court.id + "/quote", "POST", {
                    starts_at: start,
                    ends_at: end,
                    services: selected,
                    coupon_code: coupon || null,
                    fixed,
                    ...(customer ? { user_id: Number(customer) } : {}),
                })
                    .then((r) => {
                        if (active) {
                            setQuote(r.data);
                            setError("");
                        }
                    })
                    .catch((e) => {
                        if (active) {
                            setQuote(null);
                            setError(e.message);
                        }
                    }),
            350,
        );
        return () => {
            active = false;
            clearTimeout(timer);
        };
    }, [court, start, end, JSON.stringify(selected), coupon, fixed, customer]);
    useEffect(() => {
        if (!hold) return;
        const update = () =>
            setSeconds(
                Math.max(
                    0,
                    Math.floor(
                        (new Date(hold.expires_at).getTime() - Date.now()) /
                            1000,
                    ),
                ),
            );
        update();
        const timer = setInterval(update, 1000);
        return () => clearInterval(timer);
    }, [hold]);
    async function confirm() {
        if (!court || !quote) return;
        if (data!.user.role === "customer" && hold && seconds === 0)
            throw new Error("Giữ chỗ đã hết hạn. Quay lại chọn sân.");
        const payload: Row = {
            request_key: requestKey,
            court_id: court.id,
            starts_at: start,
            ends_at: end,
            services: selected,
            notes,
            coupon_code: coupon || null,
            pay_with_wallet: method === "wallet",
            deposit_only:
                depositOnly &&
                data!.user.role === "customer" &&
                b.deposit_percent > 0,
            ...(hold ? { hold_id: hold.id } : {}),
            ...(customer ? { user_id: Number(customer) } : {}),
        };
        const r = await api(fixed ? "bookings/series" : "bookings", "POST", {
            ...payload,
            ...(fixed ? { occurrences: weeks } : {}),
        });
        const booked = fixed ? r.data : r.data ? [r.data] : [];

        if (method === "momo") {
            if (fixed)
                throw new Error(
                    "Lịch cố định đã đặt. Thanh toán từng lịch trong Lịch của tôi.",
                );
            try {
                const p = await api("payments/momo", "POST", {
                    request_key: key(),
                    purpose: "booking",
                    booking_id: booked[0].id,
                    deposit_only: depositOnly && b.deposit_percent > 0,
                });
                location.assign(p.data.pay_url);
                return;
            } catch (e) {
                await refresh();
                toast(
                    "Lịch đã tạo. " +
                        (e as Error).message +
                        " Xem Lịch của tôi để tiếp tục thanh toán.",
                );
                done(booked[0]);
                return;
            }
        }
        await refresh();
        toast(
            "Đã xác nhận " +
                booked.length +
                " lịch. " +
                (method === "cash"
                    ? data!.user.role === "customer" && b.deposit_percent > 0
                        ? "Thanh toán đặt cọc trong " +
                          b.payment_minutes +
                          " phút để giữ lịch."
                        : "Thanh toán tại quầy trước khi nhận sân."
                    : ""),
        );
        done(booked[0]);
    }
    useEffect(() => {
        window.scrollTo(0, 0);
    }, [step]);
    return (
        <div className="product-page mx-auto max-w-6xl p-5 md:p-10">
            <div className="page-heading">
                <div>
                    <p className="eyebrow text-[#16a34a]">SẴN SÀNG LÊN SÂN</p>
                    <h1 className="font-display text-3xl font-bold">
                        Đặt sân của bạn
                    </h1>
                </div>
                <button
                    className="btn-outline"
                    onClick={() =>
                        run(async () => {
                            if (hold) await api("holds/" + hold.id, "DELETE");
                            close();
                        })
                    }
                >
                    Đóng
                </button>
            </div>
            <div className="booking-steps">
                {[
                    "Chọn lịch",
                    "Chọn sân & dịch vụ",
                    "Thanh toán & xác nhận",
                ].map((s, i) => (
                    <div
                        key={s}
                        className={
                            "booking-step " +
                            (i === step
                                ? "is-active"
                                : i < step
                                  ? "is-done"
                                  : "")
                        }
                    >
                        <span>
                            {i < step ? <Icon name="check" size={15} /> : i + 1}
                        </span>
                        {s}
                    </div>
                ))}
            </div>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <Card className="booking-form min-w-0">
                    {step === 0 && (
                        <div className="space-y-4">
                            <label className="block">
                                Chi nhánh
                                <select
                                    className="input mt-1 w-full"
                                    value={branch}
                                    onChange={(e) => {
                                        setBranch(+e.target.value);
                                        setServices({});
                                    }}
                                >
                                    {BRANCHES.filter(
                                        (b) =>
                                            data!.user.role === "customer" ||
                                            data!.user.role === "admin" ||
                                            data!.managed_branch_ids?.includes(
                                                b.id,
                                            ),
                                    ).map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.rawName}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <label>
                                    Ngày chơi
                                    <input
                                        className="input mt-1 w-full"
                                        type="date"
                                        value={date}
                                        min={day()}
                                        max={day(
                                            (b?.booking_days ?? 90) +
                                                (data!.membership?.plan
                                                    ?.early_days ?? 0),
                                        )}
                                        onChange={(e) =>
                                            setDate(e.target.value)
                                        }
                                    />
                                </label>
                                <label>
                                    Giờ bắt đầu
                                    <select
                                        className="input mt-1 w-full"
                                        value={time}
                                        onChange={(e) =>
                                            setTime(e.target.value)
                                        }
                                    >
                                        {timeOptions.map((t) => (
                                            <option key={t}>{t}</option>
                                        ))}
                                    </select>
                                </label>
                                <label>
                                    Thời lượng
                                    <select
                                        className="input mt-1 w-full"
                                        value={duration}
                                        onChange={(e) =>
                                            setDuration(+e.target.value)
                                        }
                                    >
                                        {Array.from(
                                            { length: 12 },
                                            (_, i) => (i + 1) / 2,
                                        )
                                            .filter(
                                                (v) =>
                                                    v * 60 <=
                                                    Math.min(
                                                        b?.closes_minute ??
                                                            1380,
                                                        1410,
                                                    ) -
                                                        (b?.opens_minute ??
                                                            180),
                                            )
                                            .map((v) => (
                                                <option value={v} key={v}>
                                                    {v} giờ
                                                </option>
                                            ))}
                                    </select>
                                </label>
                            </div>
                            <label className="block">
                                <input
                                    type="checkbox"
                                    checked={fixed}
                                    onChange={(e) => {
                                        setFixed(e.target.checked);
                                        setCoupon("");
                                    }}
                                />{" "}
                                Đặt cố định hàng tuần · giảm{" "}
                                {b?.fixed_discount ?? 0}% phí sân
                            </label>
                            {fixed && (
                                <label>
                                    Số tuần
                                    <input
                                        type="number"
                                        className="input ml-3"
                                        min={2}
                                        max={12}
                                        value={weeks}
                                        onChange={(e) =>
                                            setWeeks(+e.target.value)
                                        }
                                    />
                                </label>
                            )}
                            {data!.user.role !== "customer" && (
                                <CustomerPicker
                                    branch={Number(branch)}
                                    value={customer}
                                    onChange={setCustomer}
                                />
                            )}
                            <button
                                className="btn"
                                disabled={
                                    !available.length ||
                                    !!error ||
                                    (data!.user.role !== "customer" &&
                                        !customer)
                                }
                                onClick={() => setStep(1)}
                            >
                                Tiếp tục →
                            </button>
                        </div>
                    )}
                    {step === 1 && (
                        <>
                            <h2 className="mb-4 text-xl font-bold">Chọn sân</h2>
                            <div className="mb-4 rounded-xl bg-green-50 p-4">
                                <b>
                                    {date.split("-").reverse().join("/")} ·{" "}
                                    {time} – {end.slice(11, 16)} · {duration}{" "}
                                    giờ
                                </b>
                                <p className="mt-1 text-sm text-green-700">
                                    {
                                        available.filter((c) => c.available)
                                            .length
                                    }{" "}
                                    sân trống trong khung đã chọn
                                </p>
                            </div>
                            <CourtMatrix
                                schedule={schedule}
                                fixedWindow={{ time, duration }}
                                selectedCourt={court?.id}
                                onPick={(r) => {
                                    const c = available.find(
                                        (c) => c.id === r.court_id,
                                    );
                                    if (c?.available) setCourt(c);
                                    else
                                        toast(
                                            "Sân vừa có lịch. Chọn sân khác.",
                                        );
                                }}
                                onBusy={(c) =>
                                    run(async () => {
                                        await api("waitlist", "POST", {
                                            court_id: c.id,
                                            starts_at: start,
                                            ends_at: end,
                                        });
                                        toast(
                                            "Đã đăng ký nhận thông báo khi sân trống.",
                                        );
                                    })
                                }
                            />
                            <h2 className="mb-4 mt-6 text-xl font-bold">
                                Dịch vụ thêm
                            </h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {b?.services
                                    .filter(
                                        (s: Row) =>
                                            s.name !== "Huấn luyện viên",
                                    )
                                    .map((s: Row) => (
                                        <label
                                            className="flex items-center justify-between gap-3 rounded-xl bg-[#f3f6f4] p-3"
                                            key={s.id}
                                        >
                                            <span>
                                                {s.name}
                                                <small className="block text-[#6d8474]">
                                                    {money(s.price)}/{s.unit}
                                                </small>
                                            </span>
                                            <input
                                                aria-label={
                                                    "Số lượng " + s.name
                                                }
                                                type="number"
                                                min={0}
                                                max={10}
                                                className="input w-20"
                                                value={services[s.id] ?? 0}
                                                onChange={(e) =>
                                                    setServices({
                                                        ...services,
                                                        [s.id]: +e.target.value,
                                                    })
                                                }
                                            />
                                        </label>
                                    ))}
                            </div>
                            {!fixed && (
                                <label className="mt-4 block">
                                    Mã ưu đãi
                                    <input
                                        className="input ml-3"
                                        value={coupon}
                                        maxLength={30}
                                        onChange={(e) =>
                                            setCoupon(
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                </label>
                            )}
                            <label className="mt-4 block">
                                Ghi chú
                                <textarea
                                    className="input mt-1 w-full"
                                    maxLength={1000}
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                />
                            </label>
                            <div className="mt-5 flex gap-3">
                                <button
                                    className="btn-outline"
                                    onClick={() => setStep(0)}
                                >
                                    ← Quay lại
                                </button>
                                <button
                                    className="btn"
                                    disabled={
                                        !court || !quote || loading || !!error
                                    }
                                    onClick={() =>
                                        run(async () => {
                                            const r = await api(
                                                "holds",
                                                "POST",
                                                {
                                                    request_key: key(),
                                                    court_id: court!.id,
                                                    starts_at: start,
                                                    ends_at: end,
                                                },
                                            );
                                            setHold(r.data);
                                            setStep(2);
                                        })
                                    }
                                >
                                    Giữ chỗ & tiếp tục
                                </button>
                            </div>
                        </>
                    )}
                    {step === 2 && (
                        <>
                            <h2 className="text-xl font-bold">
                                Phương thức thanh toán
                            </h2>
                            <p className="my-3 text-sm text-[#6d8474]">
                                Giữ sân còn {Math.floor(seconds / 60)}:
                                {String(seconds % 60).padStart(2, "0")}. Lịch cố
                                định chỉ được tạo khi tất cả các tuần đều còn
                                trống.
                            </p>
                            {[
                                {
                                    id: "cash",
                                    label:
                                        data!.user.role === "customer" &&
                                        b.deposit_percent > 0
                                            ? "Tạo lịch và thanh toán đặt cọc sau"
                                            : "Thanh toán tại quầy",
                                },
                                {
                                    id: "wallet",
                                    label:
                                        data!.user.role === "customer"
                                            ? "Ví HUB · " +
                                              money(data!.user.wallet_balance)
                                            : "Ví HUB của khách hàng",
                                },
                                ...(data!.momo_enabled &&
                                !fixed &&
                                data!.user.role === "customer"
                                    ? [{ id: "momo", label: "Ví MoMo" }]
                                    : []),
                            ].map((m) => (
                                <label
                                    key={m.id}
                                    className="my-3 flex gap-3 rounded-xl border border-[#dce4dc] p-4"
                                >
                                    <input
                                        type="radio"
                                        name="payment"
                                        checked={method === m.id}
                                        onChange={() => setMethod(m.id)}
                                    />
                                    {m.label}
                                </label>
                            ))}
                            <div className="my-4 rounded-xl bg-amber-50 p-4 text-sm">
                                {data!.user.role === "customer" &&
                                b.deposit_percent > 0 ? (
                                    <>
                                        <b>
                                            Đặt cọc {b.deposit_percent}% (
                                            {money(
                                                Math.ceil(
                                                    ((quote?.total_amount ??
                                                        0) *
                                                        b.deposit_percent) /
                                                        100,
                                                ),
                                            )}
                                            ) trong {b.payment_minutes} phút.
                                        </b>
                                        <p className="mt-2">
                                            Lịch chưa đủ cọc sẽ tự giải phóng
                                            khi hết hạn.
                                        </p>
                                        <label className="mt-3 flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={depositOnly}
                                                onChange={(e) =>
                                                    setDepositOnly(
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            Chỉ trả phần đặt cọc khi thanh toán
                                            bằng ví hoặc MoMo
                                        </label>
                                    </>
                                ) : (
                                    "Thanh toán đủ trước khi nhận sân."
                                )}
                            </div>
                            <p className="text-sm text-[#6d8474]">
                                Lịch chưa thanh toán có thể hủy trước giờ chơi.
                                Lịch trả bằng ví được hoàn ví khi hủy trước ít
                                nhất 4 giờ. Các phương thức khác cần liên hệ
                                quầy.
                            </p>
                            <div className="mt-6 flex gap-3">
                                <button
                                    className="btn-outline"
                                    onClick={() =>
                                        run(async () => {
                                            if (hold)
                                                await api(
                                                    "holds/" + hold.id,
                                                    "DELETE",
                                                );
                                            setHold(null);
                                            setStep(1);
                                        })
                                    }
                                >
                                    ← Đổi lựa chọn
                                </button>
                                <button
                                    className="btn"
                                    disabled={loading || seconds === 0}
                                    onClick={() => run(confirm)}
                                >
                                    Xác nhận đặt sân
                                </button>
                            </div>
                        </>
                    )}
                    {error && (
                        <p
                            role="alert"
                            className="mt-4 rounded-xl bg-red-50 p-3 text-red-700"
                        >
                            {error}
                        </p>
                    )}
                </Card>
                <Card className="booking-summary h-fit">
                    <p className="eyebrow text-[#16a34a]">BUỔI CHƠI CỦA BẠN</p>
                    <h2 className="mt-3 text-xl font-bold">{b?.name}</h2>
                    <p className="my-3 text-sm text-[#6d8474]">{b?.address}</p>
                    <p>
                        {date} · {time} · {duration} giờ
                    </p>
                    <p className="mt-2">
                        {court?.name ?? "Chưa chọn sân"}{" "}
                        {fixed ? "· " + weeks + " tuần" : ""}
                    </p>
                    {quote && (
                        <div className="mt-6 space-y-3 border-t border-[#dce4dc] pt-4">
                            <div className="flex justify-between">
                                <span>Phí sân</span>
                                <b>{money(quote.court_amount)}</b>
                            </div>
                            <div className="flex justify-between">
                                <span>Dịch vụ</span>
                                <b>{money(quote.service_amount)}</b>
                            </div>
                            <div className="flex justify-between text-[#16a34a]">
                                <span>Ưu đãi</span>
                                <b>−{money(quote.discount_amount)}</b>
                            </div>
                            <div className="flex justify-between text-xl">
                                <span>Tổng {fixed ? "mỗi tuần" : ""}</span>
                                <b>{money(quote.total_amount)}</b>
                            </div>
                            {fixed && (
                                <small>
                                    Giá từng tuần được tính theo lịch và hội
                                    viên tại thời điểm xác nhận.
                                </small>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </div>
    );
}
