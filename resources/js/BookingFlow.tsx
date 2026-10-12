import Icon from "./Icon";
import CourtMatrix from "./CourtMatrix";
import CustomerPicker from "./CustomerPicker";
import { useEffect, useState } from "react";
import { api, key, useHub } from "./hub";
import { BRANCHES, day, localDateTime, money, type Row } from "./data";
import {
    clearBookingDraft,
    readBookingDraft,
    saveBookingDraft,
} from "./bookingDraft";
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
    const [draft] = useState(() =>
        Object.values(initial).some((v) => v !== undefined) && !initial.resume
            ? initial
            : readBookingDraft(data!.user.id),
    );
    const [restoring, setRestoring] = useState(true);
    const [restoreError, setRestoreError] = useState(""),
        [restoreAttempt, setRestoreAttempt] = useState(0);
    const [changingBranch, setChangingBranch] = useState(false),
        [nextBranch, setNextBranch] = useState<number | string>("");
    const allowedBranches = BRANCHES.filter(
        (b) =>
            ["customer", "admin"].includes(data!.user.role) ||
            data!.managed_branch_ids?.includes(b.id),
    );
    const [branch, setBranch] = useState(
            allowedBranches.some((b) => b.id === draft.branch_id)
                ? draft.branch_id
                : allowedBranches[0]?.id,
        ),
        [date, setDate] = useState(draft.date ?? day(1)),
        [time, setTime] = useState(draft.time ?? "18:00"),
        [duration, setDuration] = useState(draft.duration ?? 2),
        [court, setCourt] = useState<Row | null>(null),
        [available, setAvailable] = useState<Row[]>([]),
        [schedule, setSchedule] = useState<Row | null>(null),
        [services, setServices] = useState<Row>(draft.services ?? {}),
        [coupon, setCoupon] = useState(draft.coupon ?? ""),
        [notes, setNotes] = useState(draft.notes ?? ""),
        [quote, setQuote] = useState<Row | null>(null),
        [fixed, setFixed] = useState(!!draft.fixed),
        [weeks, setWeeks] = useState<number | string>(draft.weeks ?? 4),
        [hold, setHold] = useState<Row | null>(null),
        [seconds, setSeconds] = useState(0),
        [step, setStep] = useState(0),
        [requestKey] = useState(draft.request_key ?? key()),
        [method, setMethod] = useState("cash"),
        [depositOnly, setDepositOnly] = useState(true),
        [customer, setCustomer] = useState(draft.user_id ?? ""),
        [error, setError] = useState("");
    const b = data!.branches.find((b: Row) => b.id === Number(branch));
    useEffect(() => {
        if (fixed || data!.user.role !== "customer")
            setMethod((value) => (value === "momo" ? "cash" : value));
    }, [fixed, data!.user.role]);
    useEffect(() => {
        let live = true;
        api("holds/active")
            .then(({ data: active }) => {
                if (!live) return;
                if (active) {
                    const begin = localDateTime(active.starts_at);
                    setBranch(active.court.branch_id);
                    setDate(begin.slice(0, 10));
                    setTime(begin.slice(11, 16));
                    setDuration(
                        (Date.parse(active.ends_at) -
                            Date.parse(active.starts_at)) /
                            3600000,
                    );
                    if (draft.branch_id !== active.court.branch_id) {
                        setServices({});
                        setCoupon("");
                        setCustomer("");
                        setFixed(false);
                    }
                    setHold(active);
                    setStep(2);
                }
                setRestoring(false);
            })
            .catch((e) => {
                if (live) {
                    setRestoreError(e.message);
                    setRestoring(false);
                }
            });
        return () => {
            live = false;
        };
    }, [restoreAttempt]);
    useEffect(() => {
        if (!restoring && !restoreError)
            saveBookingDraft(data!.user.id, {
                branch_id: branch,
                date,
                time,
                duration,
                court_id: court?.id,
                services,
                coupon,
                notes,
                fixed,
                weeks,
                user_id: customer,
                request_key: requestKey,
            });
    }, [
        restoring,
        branch,
        date,
        time,
        duration,
        court?.id,
        services,
        coupon,
        notes,
        fixed,
        weeks,
        customer,
    ]);
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
        .map(([id, quantity]) => ({
            id: Number(id),
            quantity: Number(quantity),
        }));
    const invalidServices = Object.values(services).some(
        (v) =>
            v !== "" &&
            (!Number.isInteger(Number(v)) || Number(v) < 0 || Number(v) > 10),
    );
    const invalidWeeks =
        fixed &&
        (!Number.isInteger(Number(weeks)) ||
            Number(weeks) < 2 ||
            Number(weeks) > 12);
    useEffect(() => {
        if (restoring || restoreError || !branch) return;
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
                    const chosen = hold?.court_id ?? draft.court_id;
                    if (chosen)
                        setCourt(
                            r.data.find(
                                (c: Row) => c.id === chosen && c.available,
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
    }, [branch, start, end, restoring, restoreError, hold?.id]);
    useEffect(() => {
        if (restoring || !branch) return;
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
    }, [branch, date, restoring]);
    useEffect(() => {
        if (!court || invalidServices || invalidWeeks) {
            setQuote(null);
            return;
        }
        setQuote(null);
        let active = true;
        const timer = setTimeout(
            () =>
                api("courts/" + court.id + "/quote", "POST", {
                    starts_at: start,
                    ends_at: end,
                    services: selected,
                    coupon_code: coupon || null,
                    fixed,
                    ...(fixed ? { occurrences: Number(weeks) } : {}),
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
    }, [
        court,
        start,
        end,
        JSON.stringify(selected),
        coupon,
        fixed,
        weeks,
        customer,
        invalidServices,
        invalidWeeks,
    ]);
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
        if (invalidServices || invalidWeeks)
            throw new Error(
                "Kiểm tra số lượng dịch vụ và số tuần trước khi đặt sân.",
            );
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
            ...(fixed ? { occurrences: Number(weeks) } : {}),
        });
        const booked = fixed ? r.data : r.data ? [r.data] : [];
        clearBookingDraft(data!.user.id);

        if (method === "momo") {
            if (fixed) {
                await refresh().catch(() => {});
                toast(
                    "Lịch cố định đã đặt. Thanh toán từng lịch trong Lịch của tôi.",
                );
                done(booked[0]);
                return;
            }
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
                await refresh().catch(() => {});
                toast(
                    "Lịch đã tạo. " +
                        (e as Error).message +
                        " Xem Lịch của tôi để tiếp tục thanh toán.",
                );
                done(booked[0]);
                return;
            }
        }
        await refresh().catch(() => {});
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
    const bookingTypeFields = (
        <div className="booking-type-panel">
            <div className="form-section-title">
                <Icon name="calendar" size={18} />
                <span>Nhịp chơi của bạn</span>
            </div>
            <div className="booking-type-options">
                <button
                    type="button"
                    aria-pressed={!fixed}
                    onClick={() => {
                        setFixed(false);
                        setCoupon("");
                    }}
                >
                    <b>Một buổi</b>
                    <small>Chọn lịch linh hoạt</small>
                </button>
                <button
                    type="button"
                    aria-pressed={fixed}
                    onClick={() => {
                        setFixed(true);
                        setCoupon("");
                    }}
                >
                    <b>Cố định hàng tuần</b>
                    <small>
                        Giảm {b?.fixed_discount ?? 0}% phí sân mỗi buổi
                    </small>
                </button>
            </div>
            {fixed && (
                <label className="form-field mt-4">
                    <span>
                        Số tuần <span className="required-mark">*</span>
                    </span>
                    <input
                        aria-label="Số tuần"
                        type="number"
                        className="input"
                        min={2}
                        max={12}
                        step={1}
                        value={weeks}
                        onChange={(e) => setWeeks(e.target.value)}
                        aria-invalid={invalidWeeks}
                    />
                    <small>
                        Mỗi tuần một buổi, cùng sân và khung giờ. Dịch vụ được
                        tính cho mỗi buổi.
                    </small>
                </label>
            )}
            {invalidWeeks && (
                <p role="alert" className="field-error">
                    Chọn số tuần từ 2 đến 12, không nhập số lẻ.
                </p>
            )}
        </div>
    );
    const depositAmount = quote?.deposit_amount ?? 0;
    const walletAmount =
        depositOnly && b?.deposit_percent > 0
            ? depositAmount
            : (quote?.total_amount ?? 0);
    const walletShortage =
        method === "wallet" && data!.user.role === "customer"
            ? Math.max(0, walletAmount - data!.user.wallet_balance)
            : 0;
    if (restoring)
        return (
            <div className="product-page p-10">
                Đang khôi phục lựa chọn của bạn…
            </div>
        );
    if (restoreError)
        return (
            <div className="product-page mx-auto max-w-xl p-10">
                <p role="alert">{restoreError}</p>
                <button
                    className="btn mt-4"
                    onClick={() => {
                        setRestoreError("");
                        setRestoring(true);
                        setRestoreAttempt((v) => v + 1);
                    }}
                >
                    Thử khôi phục lại
                </button>
            </div>
        );
    if (!b)
        return (
            <Empty text="Bạn chưa có cơ sở được phép đặt sân. Liên hệ quản lý để được phân công." />
        );
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
                            clearBookingDraft(data!.user.id);
                            await refresh().catch(() => {});
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
                                    disabled={!!draft.branch_id}
                                    onChange={(e) => {
                                        setBranch(+e.target.value);
                                        setServices({});
                                        setCoupon("");
                                        setCustomer("");
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
                                {!!draft.branch_id && (
                                    <button
                                        type="button"
                                        className="btn-outline mt-2"
                                        onClick={() => {
                                            setNextBranch(branch);
                                            setChangingBranch(true);
                                        }}
                                    >
                                        Đổi cơ sở
                                    </button>
                                )}
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
                            {bookingTypeFields}
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
                                    invalidWeeks ||
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
                            {bookingTypeFields}
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
                                                value={services[s.id] ?? ""}
                                                placeholder="0"
                                                onChange={(e) =>
                                                    setServices({
                                                        ...services,
                                                        [s.id]: e.target.value,
                                                    })
                                                }
                                            />
                                        </label>
                                    ))}
                            </div>
                            {invalidServices && (
                                <p
                                    role="alert"
                                    className="mt-3 text-sm text-red-700"
                                >
                                    Số lượng dịch vụ phải là số nguyên từ 0 đến
                                    10.
                                </p>
                            )}
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
                                        !court ||
                                        !quote ||
                                        loading ||
                                        !!error ||
                                        invalidServices
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
                            {data!.user.role !== "customer" && (
                                <CustomerPicker
                                    branch={Number(branch)}
                                    value={customer}
                                    onChange={setCustomer}
                                />
                            )}
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
                                            {quote
                                                ? money(depositAmount)
                                                : "Đang tính…"}
                                            ) trong {b.payment_minutes} phút.
                                        </b>
                                        <p className="mt-2">
                                            Lịch chưa đủ cọc sẽ tự giải phóng
                                            khi hết hạn.
                                            {fixed && (
                                                <>
                                                    {" "}
                                                    Đây là tổng tiền cọc cho cả{" "}
                                                    {weeks} buổi. Thanh toán
                                                    bằng ví sẽ thu cọc từng buổi
                                                    trong cùng một lần xác nhận.
                                                </>
                                            )}
                                        </p>
                                        {(method === "wallet" ||
                                            method === "momo") && (
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
                                                {fixed
                                                    ? "Thanh toán tiền cọc cho toàn bộ lịch bằng ví"
                                                    : "Chỉ thanh toán tiền cọc"}
                                            </label>
                                        )}
                                    </>
                                ) : (
                                    "Thanh toán đủ trước khi nhận sân."
                                )}
                            </div>
                            {walletShortage > 0 && (
                                <p role="alert" className="field-error">
                                    Ví còn thiếu {money(walletShortage)} để{" "}
                                    {depositOnly ? "đặt cọc" : "thanh toán"}{" "}
                                    {fixed
                                        ? "toàn bộ lịch cố định"
                                        : "buổi chơi này"}
                                    . Vui lòng nạp thêm hoặc chọn phương thức
                                    khác.
                                </p>
                            )}
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
                                    disabled={
                                        (data!.user.role !== "customer" &&
                                            !customer) ||
                                        loading ||
                                        seconds === 0 ||
                                        !quote ||
                                        !!error ||
                                        invalidServices ||
                                        invalidWeeks ||
                                        walletShortage > 0
                                    }
                                    onClick={() => run(confirm)}
                                >
                                    {fixed
                                        ? "Xác nhận " + weeks + " buổi"
                                        : "Xác nhận đặt sân"}
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
                        {court?.name ?? hold?.court?.name ?? "Chưa chọn sân"}{" "}
                        {fixed ? "· " + weeks + " tuần" : ""}
                    </p>
                    {!quote && court && (
                        <p
                            role="status"
                            className="mt-5 text-sm text-[#718375]"
                        >
                            Đang kiểm tra lịch và tính tổng tiền…
                        </p>
                    )}
                    {quote && (
                        <div className="mt-6 space-y-3 border-t border-[#dce4dc] pt-4">
                            <div className="flex justify-between">
                                <span>
                                    Phí sân{" "}
                                    {fixed ? "· " + weeks + " buổi" : ""}
                                </span>
                                <b>{money(quote.court_amount)}</b>
                            </div>
                            <div className="flex justify-between">
                                <span>
                                    Dịch vụ{" "}
                                    {fixed ? "· " + weeks + " buổi" : ""}
                                </span>
                                <b>{money(quote.service_amount)}</b>
                            </div>
                            {fixed && (
                                <div className="flex justify-between text-[#168449]">
                                    <span>
                                        Giảm lịch cố định{" "}
                                        {quote.fixed_discount_percent}%
                                    </span>
                                    <b>−{money(quote.fixed_discount_amount)}</b>
                                </div>
                            )}
                            {(quote.other_discount_amount > 0 || !fixed) && (
                                <div className="flex justify-between text-[#168449]">
                                    <span>
                                        {fixed ? "Ưu đãi hội viên" : "Ưu đãi"}
                                    </span>
                                    <b>−{money(quote.other_discount_amount)}</b>
                                </div>
                            )}
                            <div className="flex justify-between text-xl">
                                <span>
                                    {fixed
                                        ? "Tổng " + weeks + " buổi"
                                        : "Tổng tiền"}
                                </span>
                                <b>{money(quote.total_amount)}</b>
                            </div>
                            {data!.user.role === "customer" &&
                                b.deposit_percent > 0 && (
                                    <>
                                        <div className="flex justify-between">
                                            <span>
                                                Đặt cọc {b.deposit_percent}%
                                            </span>
                                            <b>{money(depositAmount)}</b>
                                        </div>
                                        <div className="flex justify-between text-sm text-[#6d8474]">
                                            <span>Còn lại sau cọc</span>
                                            <span>
                                                {money(
                                                    quote.total_amount -
                                                        depositAmount,
                                                )}
                                            </span>
                                        </div>
                                    </>
                                )}
                            {fixed && quote.sessions && (
                                <details className="series-cost-details">
                                    <summary>
                                        Xem {weeks} buổi và giá từng tuần
                                    </summary>
                                    <p className="my-3 text-xs text-[#6d8474]">
                                        Giá đã kiểm tra theo ngày chơi. Ưu đãi
                                        chỉ áp dụng phí sân, không giảm giá dịch
                                        vụ.
                                    </p>
                                    {quote.sessions.map(
                                        (session: Row, i: number) => (
                                            <div key={session.starts_at}>
                                                <span>
                                                    <b>Buổi {i + 1}</b>
                                                    <small>
                                                        {localDateTime(
                                                            session.starts_at,
                                                        )
                                                            .slice(0, 10)
                                                            .split("-")
                                                            .reverse()
                                                            .join("/")}
                                                    </small>
                                                </span>
                                                <span>
                                                    <b>
                                                        {money(
                                                            session.total_amount,
                                                        )}
                                                    </b>
                                                    <small>
                                                        Cọc{" "}
                                                        {money(
                                                            session.deposit_amount,
                                                        )}
                                                    </small>
                                                </span>
                                            </div>
                                        ),
                                    )}
                                </details>
                            )}
                        </div>
                    )}
                </Card>
            </div>
            {changingBranch && (
                <Modal
                    title="Đổi cơ sở đặt sân"
                    close={() => setChangingBranch(false)}
                >
                    <p className="mb-4 text-sm">
                        Chọn cơ sở khác sẽ xóa sân, dịch vụ và ưu đãi đang chọn.
                        Bạn sẽ chọn lại lịch phù hợp với cơ sở mới.
                    </p>
                    <select
                        aria-label="Cơ sở mới"
                        className="input w-full"
                        value={nextBranch}
                        onChange={(e) => setNextBranch(e.target.value)}
                    >
                        {allowedBranches.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.rawName}
                            </option>
                        ))}
                    </select>
                    <button
                        className="btn mt-4 w-full"
                        disabled={
                            loading ||
                            !nextBranch ||
                            Number(nextBranch) === branch
                        }
                        onClick={() =>
                            run(async () => {
                                if (hold)
                                    await api("holds/" + hold.id, "DELETE");
                                setHold(null);
                                setCourt(null);
                                setQuote(null);
                                setServices({});
                                setCoupon("");
                                setNotes("");
                                setCustomer("");
                                setFixed(false);
                                setMethod("cash");
                                setDepositOnly(true);
                                setBranch(Number(nextBranch));
                                setStep(0);
                                setChangingBranch(false);
                            })
                        }
                    >
                        Xác nhận đổi cơ sở
                    </button>
                </Modal>
            )}
        </div>
    );
}
