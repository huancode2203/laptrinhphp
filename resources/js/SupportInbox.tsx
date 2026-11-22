import { useEffect, useState } from "react";
import { api, useHub } from "./hub";
import UserName from "./MembershipBadge";
import { useChatDelivery } from "./chatDelivery";
import { stamp, type Row } from "./data";
import { appPath } from "./urls";
import { Card, Empty } from "./ui";
import MediaPicker from "./MediaPicker";

export default function SupportInbox({ branch }: { branch: number }) {
    const { data } = useHub();
    const [rooms, setRooms] = useState<Row[]>([]);
    const [selected, setSelected] = useState<number | null>(null);
    const [messages, setMessages] = useState<Row[]>([]);
    const [text, setText] = useState("");
    const [attachments, setAttachments] = useState<number[]>([]);
    const [error, setError] = useState("");
    const [uploading, setUploading] = useState(false);
    const delivery = useChatDelivery(data!.user, selected, (rows) =>
        setMessages((old) => [
            ...old.filter(
                (message) => !rows.some((row) => row.id === message.id),
            ),
            ...rows,
        ]),
    );
    const busy = uploading || delivery.sending;
    const visibleMessages = [
        ...messages,
        ...delivery.outbox.filter(
            (draft) =>
                !messages.some(
                    (message) => message.request_key === draft.request_key,
                ),
        ),
    ];
    useEffect(() => {
        let live = true;
        setSelected(null);
        setRooms([]);
        setMessages([]);
        setError("");
        const load = async () => {
            try {
                const response = await api(
                    "conversations?support_branch_id=" + branch,
                );
                if (live) setRooms(response.data);
            } catch (failure) {
                if (live) setError((failure as Error).message);
            }
        };
        void load();
        const timer = window.setInterval(load, 10000);
        return () => {
            live = false;
            clearInterval(timer);
        };
    }, [branch]);
    useEffect(() => {
        let live = true;
        setMessages([]);
        setText("");
        setAttachments([]);
        if (!selected) return;
        const load = async () => {
            try {
                const response = await api(
                    "conversations/" + selected + "/messages",
                );
                if (live) setMessages(response.data);
            } catch (failure) {
                if (live) setError((failure as Error).message);
            }
        };
        void load();
        const timer = window.setInterval(load, 5000);
        return () => {
            live = false;
            clearInterval(timer);
        };
    }, [selected]);
    const room = rooms.find((room) => room.id === selected);
    return (
        <Card className="mb-6">
            <h2 className="mb-2 text-xl font-bold">Tin nhắn hỗ trợ đặt sân</h2>
            <p className="mb-4 text-sm text-slate-600">
                Trả lời khách về lịch sân của chi nhánh đang chọn. Tin mới được
                cập nhật tự động.
            </p>
            {error && (
                <p role="alert" className="mb-3 text-sm text-red-700">
                    {error}
                </p>
            )}
            <div className="grid gap-4 lg:grid-cols-[240px_1fr]">
                <div className="max-h-96 space-y-2 overflow-y-auto">
                    {rooms.map((room) => (
                        <button
                            type="button"
                            disabled={busy}
                            key={room.id}
                            className={
                                "w-full rounded-xl border p-3 text-left " +
                                (selected === room.id
                                    ? "border-green-700 bg-green-50"
                                    : "border-slate-200")
                            }
                            onClick={() => setSelected(room.id)}
                        >
                            <b>{room.title}</b>
                            <p className="mt-1 truncate text-sm text-slate-600">
                                {room.messages?.[0]?.content ||
                                    "Chọn để xem hội thoại"}
                            </p>
                        </button>
                    ))}
                    {!rooms.length && (
                        <Empty text="Chưa có hội thoại hỗ trợ đặt sân." />
                    )}
                </div>
                {room ? (
                    <div>
                        <h3 className="mb-3 font-bold">{room.title}</h3>
                        <div className="mb-4 max-h-80 space-y-3 overflow-y-auto rounded-xl bg-slate-50 p-3">
                            {visibleMessages.map((message) => (
                                <div
                                    key={message.id}
                                    className={
                                        "rounded-xl border p-3 " +
                                        (message.user_id === data!.user.id
                                            ? "border-green-100 bg-green-50"
                                            : "border-slate-200 bg-white")
                                    }
                                >
                                    <p className="mb-1 text-xs text-slate-500">
                                        <UserName user={message.user} /> ·{" "}
                                        {stamp(message.created_at)}
                                    </p>
                                    <p className="whitespace-pre-wrap">
                                        {message.content}
                                    </p>
                                    {message.attachments?.map((id: number) => (
                                        <a
                                            key={id}
                                            className="mt-2 block text-sm font-bold text-green-800"
                                            href={appPath(
                                                "/api/v1/media/" + id,
                                            )}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Xem tệp đính kèm ↗
                                        </a>
                                    ))}
                                    {message.delivery === "sending" && (
                                        <p
                                            className="mt-2 text-xs"
                                            role="status"
                                        >
                                            Đang gửi…
                                        </p>
                                    )}
                                    {message.delivery === "failed" && (
                                        <div
                                            className="chat-send-error"
                                            role="alert"
                                        >
                                            <p>{message.error}</p>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    delivery.retry(message)
                                                }
                                            >
                                                Gửi lại
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {!messages.length && (
                                <p className="text-sm">
                                    Khách đã mở hội thoại. Bạn có thể gửi lời
                                    chào để hỗ trợ.
                                </p>
                            )}
                        </div>
                        <fieldset disabled={busy}>
                            <MediaPicker
                                key={selected}
                                value={attachments}
                                onChange={setAttachments}
                                onBusyChange={setUploading}
                                files
                            />
                            <form
                                className="mt-3 flex gap-2"
                                onSubmit={async (event) => {
                                    event.preventDefault();
                                    if (
                                        busy ||
                                        (!text.trim() && !attachments.length)
                                    )
                                        return;
                                    setError("");
                                    delivery.send(text, attachments);
                                    setText("");
                                    setAttachments([]);
                                }}
                            >
                                <input
                                    aria-label="Tin nhắn hỗ trợ khách"
                                    className="input min-w-0 flex-1"
                                    value={text}
                                    maxLength={2000}
                                    required={!attachments.length}
                                    disabled={busy}
                                    onChange={(event) => {
                                        setText(event.target.value);
                                    }}
                                />
                                <button
                                    type="submit"
                                    className="btn"
                                    disabled={busy}
                                >
                                    {busy ? "Đang gửi…" : "Gửi phản hồi"}
                                </button>
                            </form>
                        </fieldset>
                    </div>
                ) : (
                    <Empty text="Chọn hội thoại để đọc và trả lời khách." />
                )}
            </div>
        </Card>
    );
}
