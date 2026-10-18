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
    const { run, loading, toast } = useHub();
    const [requestKey] = useState(key),
        [preview, setPreview] = useState<Row | null>(null),
        [error, setError] = useState("");
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
                    {preview.refund_amount > 0 && (
                        <p
                            role="alert"
                            className="rounded-xl bg-amber-50 p-4 text-sm"
                        >
                            Chuỗi có buổi đã thanh toán nên không thể hủy trực
                            tiếp. Vui lòng đổi lịch hoặc liên hệ chi nhánh.
                        </p>
                    )}
                    <button
                        className="btn w-full"
                        disabled={loading || preview.refund_amount > 0}
                        onClick={() =>
                            run(async () => {
                                await api(
                                    "bookings/" + booking.id + "/series-cancel",
                                    "POST",
                                    {
                                        request_key: requestKey,
                                        expected_refund: preview.refund_amount,
                                    },
                                );
                                await after();
                                toast(
                                    "Đã hủy các buổi chưa thanh toán đã chọn.",
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
