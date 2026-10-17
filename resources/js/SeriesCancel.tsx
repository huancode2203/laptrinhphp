import { useEffect, useState } from "react";
import { api, key, useHub } from "./hub";
import { money, stamp, type Row } from "./data";
import { Modal } from "./ui";

export default function SeriesCancel({
    booking,
    close,
    after,
}: {
    booking: Row;
    close: () => void;
    after: () => Promise<void>;
}) {
    const { data, run, loading, toast } = useHub();
    const [requestKey] = useState(key),
        [preview, setPreview] = useState<Row | null>(null),
        [error, setError] = useState(""),
        [method, setMethod] = useState(""),
        [confirmed, setConfirmed] = useState(false);
    useEffect(() => {
        let live = true;
        api("bookings/" + booking.id + "/series-cancel-quote", "POST", {})
            .then((response) => {
                if (live) setPreview(response.data);
            })
            .catch((e) => {
                if (live) setError(e.message);
            });
        return () => {
            live = false;
        };
    }, [booking.id]);
    const customer = data!.user.role === "customer";
    return (
        <Modal title="Hủy các buổi còn lại trong chuỗi" close={close}>
            <p className="mb-5 text-sm text-slate-500">
                Chỉ xử lý các buổi chưa bắt đầu. Các buổi đã chơi hoặc đã hủy
                được giữ nguyên.
            </p>
            {error && (
                <p role="alert" className="field-error">
                    {error}
                </p>
            )}
            {!preview && !error && (
                <p role="status">Đang kiểm tra các buổi trong chuỗi…</p>
            )}
            {preview && (
                <div className="space-y-4">
                    <div className="rounded-2xl bg-slate-50 p-4">
                        <b>{preview.sessions.length} buổi sẽ hủy</b>
                        {preview.sessions.map((session: Row) => (
                            <p className="mt-2 text-sm" key={session.id}>
                                {stamp(session.starts_at)} · {session.reference}
                            </p>
                        ))}
                    </div>
                    <p className="flex justify-between font-bold">
                        <span>Tiền đã thu cần hoàn</span>
                        <span>{money(preview.refund_amount)}</span>
                    </p>
                    {preview.refund_amount > 0 &&
                        (customer ? (
                            <p className="text-sm text-emerald-700">
                                Khoản đủ điều kiện được hoàn vào Ví HUB khi hủy
                                thành công.
                            </p>
                        ) : (
                            <>
                                <label className="block text-sm">
                                    Cách xử lý khoản hoàn
                                    <select
                                        className="input mt-2 w-full"
                                        value={method}
                                        onChange={(e) => {
                                            setMethod(e.target.value);
                                            setConfirmed(false);
                                        }}
                                    >
                                        <option value="">
                                            Đưa vào danh sách chờ hoàn tại quầy
                                        </option>
                                        <option value="wallet">
                                            Hoàn vào Ví HUB của khách
                                        </option>
                                        <option value="cash">
                                            Đã hoàn tiền mặt
                                        </option>
                                        <option value="bank">
                                            Đã chuyển khoản hoàn tiền
                                        </option>
                                    </select>
                                </label>
                                {["cash", "bank"].includes(method) && (
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={confirmed}
                                            onChange={(e) =>
                                                setConfirmed(e.target.checked)
                                            }
                                        />
                                        Tôi đã hoàn đủ khoản tiền trên cho
                                        khách. Hệ thống chỉ ghi nhận khoản hoàn,
                                        không tự chuyển tiền.
                                    </label>
                                )}
                            </>
                        ))}
                    <button
                        className="btn w-full"
                        disabled={
                            loading ||
                            (["cash", "bank"].includes(method) && !confirmed)
                        }
                        onClick={() =>
                            run(async () => {
                                await api(
                                    "bookings/" + booking.id + "/series-cancel",
                                    "POST",
                                    {
                                        request_key: requestKey,
                                        expected_refund: preview.refund_amount,
                                        refund_method: customer
                                            ? "wallet"
                                            : method || null,
                                        refund_confirmed: confirmed,
                                    },
                                );
                                await after();
                                toast(
                                    "Đã hủy các buổi đã chọn và cập nhật khoản hoàn.",
                                );
                                close();
                            })
                        }
                    >
                        Xác nhận hủy {preview.sessions.length} buổi
                    </button>
                </div>
            )}
        </Modal>
    );
}
