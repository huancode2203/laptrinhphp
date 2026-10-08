import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import { money, stamp, statusText, type Row } from "./data";
import { Card, Confirm, Editor, Empty, Modal } from "./ui";
export const eventTypes: Record<string, string> = {
    friendly: "Giao lưu",
    league: "Giải đấu",
    training: "Lớp học",
    workshop: "Workshop",
};
export function EventDetail({
    id,
    close,
    managed = false,
    changed,
}: {
    id: number;
    close: () => void;
    managed?: boolean;
    changed?: () => Promise<void>;
}) {
    const { data, run, toast } = useHub();
    const [e, setEvent] = useState<Row | null>(null),
        [form, setForm] = useState(""),
        [match, setMatch] = useState<Row | null>(null),
        [reg, setReg] = useState<Row | null>(null);
    const user = data!.user;
    async function load() {
        setEvent((await api("events/" + id)).data);
    }
    useEffect(() => {
        load().catch((err) => toast(err.message));
    }, [id]);
    async function update(fn: () => Promise<any>) {
        await fn();
        await load();
        await changed?.();
    }
    if (!e)
        return (
            <Modal title="Đang tải sự kiện" close={close}>
                Đang tải…
            </Modal>
        );
    return (
        <Modal title={e.title} close={close}>
            <span className="badge badge-green">
                {eventTypes[e.type]} · {statusText(e.status)}
            </span>
            <p className="my-4 text-[#6d8474]">
                {e.branch?.name} · {stamp(e.starts_at)} → {stamp(e.ends_at)}
            </p>
            <p className="whitespace-pre-wrap">{e.description}</p>
            <p className="my-4 font-bold">
                {money(e.fee)}/người · {e.confirmed_count}/{e.capacity} đã xác
                nhận
            </p>
            <p className="text-sm">
                Thể thức:{" "}
                {e.type === "league" ? "Loại trực tiếp đơn" : e.format} ·{" "}
                {e.skill_level}
            </p>
            {!managed && user && (
                <div className="my-5 flex flex-wrap gap-3">
                    {e.my_registration && (
                        <span className="badge badge-gray">
                            Vé: {statusText(e.my_registration.status)}
                            {e.my_registration.status === "pending"
                                ? " · Thanh toán tại quầy trong 30 phút"
                                : ""}
                        </span>
                    )}
                    {e.my_registration &&
                    ["pending", "confirmed"].includes(
                        e.my_registration.status,
                    ) ? (
                        <button
                            className="btn-outline"
                            onClick={() => setForm("withdraw")}
                        >
                            Hủy đăng ký
                        </button>
                    ) : (
                        <>
                            <button
                                className="btn"
                                onClick={() => setForm("register:wallet")}
                            >
                                Đăng ký bằng ví
                            </button>
                            <button
                                className="btn-outline"
                                onClick={() => setForm("register:cash")}
                            >
                                Đăng ký · trả tại quầy
                            </button>
                        </>
                    )}
                </div>
            )}
            {managed && (
                <div className="my-4 flex flex-wrap gap-2">
                    {e.type === "league" && !e.matches.length && (
                        <button className="btn" onClick={() => setForm("draw")}>
                            Bốc thăm giải
                        </button>
                    )}
                    {e.status === "published" && (
                        <>
                            <button
                                className="btn-outline"
                                onClick={() => setForm("complete")}
                            >
                                Hoàn tất sự kiện
                            </button>
                            <button
                                className="btn-outline text-red-600"
                                onClick={() => setForm("cancel")}
                            >
                                Hủy sự kiện
                            </button>
                        </>
                    )}
                </div>
            )}
            <h3 className="my-4 font-bold">Người tham gia</h3>
            {e.registrations.map((r: Row) => (
                <div
                    className="my-2 flex flex-wrap justify-between gap-2 rounded-xl bg-[#f3f6f4] p-3"
                    key={r.id}
                >
                    <span>
                        {r.user.name}{" "}
                        {managed ? "· " + statusText(r.status) : ""}
                    </span>
                    {managed && r.status === "pending" && (
                        <button
                            className="text-sm font-bold text-[#16a34a]"
                            onClick={() => {
                                setReg(r);
                                setForm("confirm");
                            }}
                        >
                            Xác nhận thu tiền
                        </button>
                    )}
                    {managed &&
                        r.status === "cancelled" &&
                        r.paid_amount > 0 && (
                            <button
                                className="text-sm text-red-600"
                                onClick={() => {
                                    setReg(r);
                                    setForm("refund");
                                }}
                            >
                                Hoàn tiền tại quầy
                            </button>
                        )}
                </div>
            ))}
            {e.matches.length > 0 && (
                <>
                    <h3 className="my-4 font-bold">Nhánh đấu</h3>
                    {Array.from(
                        new Set(e.matches.map((m: Row) => m.round)),
                    ).map((round) => (
                        <div key={String(round)} className="mb-4">
                            <b>Vòng {String(round)}</b>
                            {e.matches
                                .filter((m: Row) => m.round === round)
                                .map((m: Row) => (
                                    <div
                                        className="my-2 rounded-xl border border-[#dce4dc] p-3"
                                        key={m.id}
                                    >
                                        <p>
                                            {m.a?.name ?? "Chờ / miễn đấu"}{" "}
                                            {m.score_a ?? ""} —{" "}
                                            {m.score_b ?? ""}{" "}
                                            {m.b?.name ?? "Chờ / miễn đấu"}
                                        </p>
                                        {m.winner_id && (
                                            <small className="text-[#16a34a]">
                                                Thắng:{" "}
                                                {m.winner_id === m.player_a
                                                    ? m.a?.name
                                                    : m.b?.name}
                                            </small>
                                        )}
                                        {managed &&
                                            !m.winner_id &&
                                            m.player_a &&
                                            m.player_b && (
                                                <button
                                                    className="ml-3 text-sm text-[#16a34a]"
                                                    onClick={() => {
                                                        setMatch(m);
                                                        setForm("score");
                                                    }}
                                                >
                                                    Nhập tỷ số
                                                </button>
                                            )}
                                    </div>
                                ))}
                        </div>
                    ))}
                </>
            )}
            {["draw", "cancel", "complete", "withdraw"].includes(form) && (
                <Confirm
                    title="Xác nhận thao tác"
                    close={() => setForm("")}
                    action={() =>
                        update(() =>
                            api(
                                form === "withdraw"
                                    ? "events/" + id + "/register"
                                    : form === "draw"
                                      ? "manage/events/" + id + "/draw"
                                      : "manage/events/" + id + "/status",
                                form === "withdraw"
                                    ? "DELETE"
                                    : form === "draw"
                                      ? "POST"
                                      : "PATCH",
                                form === "cancel"
                                    ? { status: "cancelled" }
                                    : form === "complete"
                                      ? { status: "completed" }
                                      : undefined,
                            ),
                        )
                    }
                >
                    {form === "draw"
                        ? "Bốc thăm loại trực tiếp từ các vé đã xác nhận. Không thay đổi nhánh đấu sau khi tạo."
                        : form === "cancel"
                          ? "Hủy sự kiện và giải phóng sân; tiền ví tự hoàn, các phương thức khác chờ quầy xử lý."
                          : form === "withdraw"
                            ? "Hủy vé? Vé trả ví cần hủy trước ít nhất 4 giờ."
                            : "Chỉ hoàn tất khi sự kiện và các trận đấu đã kết thúc."}
                </Confirm>
            )}
            {form.startsWith("register:") && (
                <Confirm
                    title="Đăng ký sự kiện"
                    close={() => setForm("")}
                    action={() =>
                        update(() =>
                            api("events/" + id + "/register", "POST", {
                                method: form.split(":")[1],
                            }),
                        )
                    }
                >
                    Phí {money(e.fee)}.{" "}
                    {form.endsWith("cash")
                        ? "Vé được giữ trong 30 phút để thanh toán tại quầy."
                        : "Phí sẽ trừ từ số dư ví HUB."}
                </Confirm>
            )}
            {(form === "confirm" || form === "refund") && reg && (
                <Editor
                    title={
                        form === "confirm"
                            ? "Xác nhận đã thu tiền"
                            : "Xác nhận đã hoàn tiền"
                    }
                    close={() => setForm("")}
                    fields={[
                        {
                            name: "method",
                            label: "Phương thức tại quầy",
                            required: true,
                            options: [
                                { value: "cash", label: "Tiền mặt" },
                                { value: "bank", label: "Ngân hàng" },
                            ],
                        },
                    ]}
                    save={(d) =>
                        update(() =>
                            api(
                                "manage/registrations/" + reg.id + "/" + form,
                                "POST",
                                d,
                            ),
                        )
                    }
                />
            )}
            {form === "score" && match && (
                <Editor
                    title="Kết quả trận đấu"
                    close={() => setForm("")}
                    fields={[
                        {
                            name: "score_a",
                            label: match.a.name,
                            type: "number",
                            min: 0,
                            max: 1000,
                            required: true,
                        },
                        {
                            name: "score_b",
                            label: match.b.name,
                            type: "number",
                            min: 0,
                            max: 1000,
                            required: true,
                        },
                    ]}
                    save={(d) =>
                        update(() =>
                            api(
                                "manage/matches/" + match.id + "/score",
                                "PATCH",
                                d,
                            ),
                        )
                    }
                />
            )}
        </Modal>
    );
}
export default function EventsPage({ auth }: { auth: () => void }) {
    const { toast, data } = useHub();
    const [items, set] = useState<Row[]>([]),
        [type, setType] = useState(""),
        [selected, select] = useState(0),
        [page, setPage] = useState(1),
        [last, setLast] = useState(1);
    useEffect(() => {
        api("events?page=" + page + (type ? "&type=" + type : ""))
            .then((r) => {
                set(r.data.data);
                setLast(r.data.last_page);
            })
            .catch((e) => toast(e.message));
    }, [type, page]);
    return (
        <div className="mx-auto max-w-6xl p-5 md:p-10">
            <p className="eyebrow text-[#16a34a]">events & tournaments</p>
            <h1 className="font-display mt-2 text-4xl font-bold">
                Sự kiện & Giải đấu
            </h1>
            <div className="my-6 flex flex-wrap gap-2">
                {[["", "Tất cả"], ...Object.entries(eventTypes)].map(
                    ([id, label]) => (
                        <button
                            className={id === type ? "btn" : "btn-outline"}
                            key={id}
                            onClick={() => {
                                setType(id);
                                setPage(1);
                            }}
                        >
                            {label}
                        </button>
                    ),
                )}
            </div>
            <div className="grid gap-5 md:grid-cols-3">
                {items.map((e) => (
                    <Card key={e.id}>
                        <div className="mb-5 grid h-28 place-items-center rounded-xl bg-[#0d1f2d] text-5xl">
                            {e.type === "league"
                                ? "🏆"
                                : e.type === "training"
                                  ? "🏸"
                                  : "🤝"}
                        </div>
                        <span className="badge badge-green">
                            {eventTypes[e.type]}
                        </span>
                        <h2 className="my-3 text-xl font-bold">{e.title}</h2>
                        <p className="text-sm text-[#6d8474]">
                            {e.branch?.district} · {stamp(e.starts_at)}
                        </p>
                        <p className="my-3 font-bold">
                            {money(e.fee)} ·{" "}
                            {Math.max(0, e.capacity - e.reserved_count)} chỗ còn
                            lại
                        </p>
                        <button
                            className="btn w-full"
                            onClick={() => {
                                select(e.id);
                            }}
                        >
                            Xem chi tiết
                        </button>
                        {!data!.user && (
                            <button
                                className="mt-3 text-sm text-[#16a34a]"
                                onClick={auth}
                            >
                                Đăng nhập để đăng ký
                            </button>
                        )}
                    </Card>
                ))}
            </div>
            {!items.length && (
                <Empty text="Chưa có sự kiện trong nhóm này. Quản lý có thể tạo sự kiện mới." />
            )}
            <div className="mt-5 flex items-center gap-3">
                <button
                    className="btn-outline"
                    disabled={page === 1}
                    onClick={() => setPage(page - 1)}
                >
                    Trước
                </button>
                {page}/{last}
                <button
                    className="btn-outline"
                    disabled={page >= last}
                    onClick={() => setPage(page + 1)}
                >
                    Sau
                </button>
            </div>
            {selected > 0 && (
                <EventDetail id={selected} close={() => select(0)} />
            )}
        </div>
    );
}
