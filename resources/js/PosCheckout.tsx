import { useState } from "react";
import CustomerPicker from "./CustomerPicker";
import { api, useHub } from "./hub";
import { money, stamp, type Row } from "./data";
import { Modal } from "./ui";
export default function PosCheckout({
    branch,
    bookings,
    total,
    close,
    save,
}: {
    branch: number;
    bookings: Row[];
    total: number;
    close: () => void;
    save: (d: Row) => Promise<void>;
}) {
    const { run, loading } = useHub();
    const [method, setMethod] = useState("cash"),
        [customer, setCustomer] = useState<number | string>(""),
        [booking, setBooking] = useState<Row | null>(null),
        [reference, setReference] = useState("");
    return (
        <Modal title="Thanh toán giỏ hàng" close={close}>
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    run(async () => {
                        await save({
                            method,
                            ...(method === "booking"
                                ? { booking_id: booking!.id }
                                : {
                                      ...(customer
                                          ? { user_id: Number(customer) }
                                          : {}),
                                  }),
                        });
                        close();
                    });
                }}
            >
                <div className="rounded-xl bg-green-50 p-4">
                    <span>Tổng giỏ hàng</span>
                    <b className="float-right">{money(total)}</b>
                </div>
                <label className="block text-sm font-bold">
                    Cách thanh toán
                    <select
                        aria-label="Cách thanh toán POS"
                        className="input mt-2 w-full"
                        value={method}
                        onChange={(e) => setMethod(e.target.value)}
                    >
                        <option value="cash">Tiền mặt</option>
                        <option value="bank">Chuyển khoản</option>
                        <option value="wallet">Ví khách hàng</option>
                        <option value="booking">Gộp vào hóa đơn sân</option>
                    </select>
                </label>
                {method === "booking" ? (
                    <>
                        <label className="block text-sm">
                            Chọn lịch hôm nay
                            <select
                                aria-label="Lịch gộp hóa đơn"
                                className="input mt-2 w-full"
                                value={booking?.id ?? ""}
                                onChange={(e) =>
                                    setBooking(
                                        bookings.find(
                                            (b) =>
                                                b.id === Number(e.target.value),
                                        ) ?? null,
                                    )
                                }
                            >
                                <option value="">Chọn lịch đang mở</option>
                                {bookings
                                    .filter((b) =>
                                        ["confirmed", "checked_in"].includes(
                                            b.status,
                                        ),
                                    )
                                    .map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.reference} · {b.customer?.name} ·{" "}
                                            {b.court?.name}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <div className="flex gap-2">
                            <input
                                aria-label="Mã lịch cần gộp"
                                className="input min-w-0 flex-1"
                                placeholder="Hoặc nhập mã lịch khác"
                                value={reference}
                                onChange={(e) => setReference(e.target.value)}
                            />
                            <button
                                type="button"
                                className="btn-outline"
                                disabled={!reference.trim()}
                                onClick={() =>
                                    run(async () => {
                                        const r = await api(
                                            "manage/booking-search?" +
                                                new URLSearchParams({
                                                    branch_id: String(branch),
                                                    reference: reference.trim(),
                                                }),
                                        );
                                        setBooking(r.data);
                                    })
                                }
                            >
                                Tìm lịch
                            </button>
                        </div>
                        {booking && (
                            <div className="rounded-xl bg-[#f3f6f4] p-4 text-sm">
                                <b>
                                    {booking.reference} ·{" "}
                                    {booking.customer?.name}
                                </b>
                                <p className="mt-2">
                                    {stamp(booking.starts_at)}
                                </p>
                                <p className="mt-2">
                                    Còn phải thu sau khi gộp:{" "}
                                    <b>
                                        {money(
                                            booking.remaining_amount + total,
                                        )}
                                    </b>
                                </p>
                            </div>
                        )}
                        <p className="text-sm text-slate-500">
                            Hàng được trừ kho và thêm vào hóa đơn. Thu chung với
                            tiền sân trong chi tiết lịch.
                        </p>
                    </>
                ) : (
                    <CustomerPicker
                        branch={branch}
                        value={customer}
                        onChange={setCustomer}
                    />
                )}
                <button
                    className="btn w-full"
                    disabled={
                        loading ||
                        (method === "wallet" && !customer) ||
                        (method === "booking" &&
                            (!booking ||
                                !["confirmed", "checked_in"].includes(
                                    booking.status,
                                )))
                    }
                >
                    {method === "booking"
                        ? "Thêm vào hóa đơn sân"
                        : "Xác nhận đã thanh toán"}
                </button>
            </form>
        </Modal>
    );
}
