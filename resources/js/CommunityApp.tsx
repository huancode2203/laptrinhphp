import MediaPicker from "./MediaPicker";
import Icon from "./Icon";
import { useEffect, useRef, useState } from "react";
import { api, key, useHub } from "./hub";
import {
    BRANCHES,
    money,
    stamp,
    localDateTime,
    statusText,
    type Row,
} from "./data";
import { Card, Editor, Empty, Modal } from "./ui";
export default function CommunityApp({
    auth,
    book,
}: {
    auth: () => void;
    book: (range: Row) => void;
}) {
    const { data, run, toast, refresh, loading } = useHub();
    const [tab, setTab] = useState("feed"),
        [posts, setPosts] = useState<Row[]>([]),
        [players, setPlayers] = useState<Row[]>([]),
        [conversations, setConversations] = useState<Row[]>([]),
        [conversation, setConversation] = useState<Row | null>(null),
        [messages, setMessages] = useState<Row[]>([]),
        [post, setPost] = useState<Row | null>(null),
        [form, setForm] = useState(""),
        [search, setSearch] = useState(""),
        [level, setLevel] = useState(""),
        [branch, setBranch] = useState(""),
        [text, setText] = useState(""),
        [page, setPage] = useState(1),
        [lastPage, setLastPage] = useState(1);
    const messageEnd = useRef<HTMLDivElement>(null);
    useEffect(() => {
        const list = messageEnd.current?.parentElement;
        if (list) list.scrollTo({ top: list.scrollHeight });
    }, [messages.length]);
    const user = data!.user;
    const [attachments, setAttachments] = useState<number[]>([]),
        [members, setMembers] = useState<Row[]>([]),
        [files, setFiles] = useState<Row[]>([]),
        [reply, setReply] = useState<Row | null>(null);
    async function load() {
        const p = await api(
            "community/posts?" +
                new URLSearchParams({
                    page: String(page),
                    ...(search ? { search } : {}),
                    ...(branch ? { branch_id: branch } : {}),
                }),
        );
        setPosts(p.data.data);
        setLastPage(p.data.last_page);
        const q = await api(
            "community/players?" +
                new URLSearchParams({
                    ...(search ? { search } : {}),
                    ...(level ? { skill_level: level } : {}),
                }),
        );
        setPlayers(q.data);
        if (user) {
            const c = await api("conversations");
            const all = [
                {
                    id: "hub-ai",
                    title: "HUB AI",
                    type: "ai",
                    messages: [
                        {
                            content:
                                "Tìm sân phù hợp với lịch và khu vực của bạn",
                        },
                    ],
                },
                ...c.data,
            ];
            setConversations(all);
            if (location.hash.startsWith("#community/chat/")) {
                const value = location.hash.split("/")[2];
                const id = value === "hub-ai" ? value : Number(value);
                setConversation(all.find((v: Row) => v.id === id) ?? null);
                setTab("chat");
            }
        }
    }
    async function loadPost(id: number) {
        const p = await api("community/posts/" + id);
        setPost(p.data);
    }
    useEffect(() => {
        if (location.hash.startsWith("#community/post/"))
            loadPost(Number(location.hash.split("/")[2])).catch((e) =>
                toast(e.message),
            );
    }, []);
    useEffect(() => {
        load().catch((e) => toast(e.message));
    }, [search, branch, level, page, user?.id]);
    useEffect(() => {
        if (!conversation) return;
        setMessages([]);
        setText("");
        setAttachments([]);
        setMembers([]);
        setFiles([]);
        if (conversation.id === "hub-ai") {
            let live = true;
            api("ai/chat")
                .then((r) => {
                    if (live) setMessages(r.data);
                })
                .catch((e) => toast(e.message));
            return () => {
                live = false;
            };
        }

        let live = true;
        const get = () =>
            api("conversations/" + conversation.id + "/messages")
                .then((r) => {
                    if (live) {
                        setMessages(r.data);
                        setMembers(r.members ?? []);
                        setFiles(r.attachments ?? []);
                    }
                })
                .catch((e) => {
                    if (live) toast(e.message);
                });
        get();
        let version = "";
        const stream = new EventSource(
            "/api/v1/conversations/" + conversation.id + "/stream",
        );
        stream.addEventListener("update", (e) => {
            const next = JSON.parse((e as MessageEvent).data).version;
            if (next !== version) {
                version = next;
                get();
            }
        });
        return () => {
            live = false;
            stream.close();
        };
    }, [conversation?.id]);
    function renderComments(parent: number | null, depth = 0): React.ReactNode {
        return post?.comments
            ?.filter((c: Row) => (c.parent_id ?? null) === parent)
            .map((c: Row) => (
                <div
                    key={c.id}
                    className={
                        "my-2 rounded-xl p-3 text-sm " +
                        (depth
                            ? "border-l-2 border-[#b2d4bd] bg-green-50/50"
                            : "bg-[#f3f6f4]")
                    }
                    style={{ marginLeft: Math.min(depth, 4) * 12 }}
                >
                    <b>{c.user.name}</b>
                    {parent && (
                        <p className="mt-1 text-xs font-semibold text-[#168449]">
                            ↳ Trả lời{" "}
                            {
                                post?.comments?.find(
                                    (v: Row) => v.id === parent,
                                )?.user?.name
                            }
                        </p>
                    )}
                    <p className="mt-2 whitespace-pre-wrap">{c.content}</p>
                    <div className="mt-2 flex gap-3">
                        <button
                            className="text-xs font-bold text-[#168449]"
                            onClick={() => setReply(c)}
                        >
                            Trả lời
                        </button>
                        {(c.user_id === user?.id || user?.role === "admin") && (
                            <button
                                className="text-xs text-red-600"
                                onClick={() =>
                                    run(() =>
                                        changed(() =>
                                            api(
                                                "community/comments/" + c.id,
                                                "DELETE",
                                            ),
                                        ),
                                    )
                                }
                            >
                                Xóa
                            </button>
                        )}
                    </div>
                    {depth < 20 && renderComments(c.id, depth + 1)}
                </div>
            ));
    }
    function action(fn: () => Promise<void>) {
        if (!user) {
            auth();
            return;
        }
        run(fn);
    }
    async function changed(fn: () => Promise<any>) {
        await fn();
        await load();
        if (post) await loadPost(post.id);
    }
    const fields = [
        { name: "title", label: "Tiêu đề", required: true },
        {
            name: "branch_id",
            label: "Chi nhánh",
            required: true,
            options: BRANCHES.map((b) => ({ value: b.id, label: b.name })),
        },
        {
            name: "type",
            label: "Loại bài",
            required: true,
            options: [
                "Tìm người đánh",
                "Tìm đối thủ",
                "Tuyển thành viên",
                "Chia sẻ",
                "Hỏi đáp",
                "Giao lưu",
            ].map((value) => ({ value, label: value })),
        },
        {
            name: "skill_level",
            label: "Trình độ",
            required: true,
            options: ["Sơ cấp", "Trung cấp", "Cao cấp", "Mọi trình độ"].map(
                (value) => ({ value, label: value }),
            ),
        },
        {
            name: "starts_at",
            label: "Thời gian chơi",
            type: "datetime-local",
            required: true,
        },
        {
            name: "capacity",
            label: "Số người cần tìm",
            type: "number",
            min: 1,
            max: 100,
            required: true,
        },
        { name: "images", label: "Ảnh đính kèm", type: "images" },
        {
            name: "content",
            label: "Nội dung",
            type: "textarea",
            required: true,
        },
        {
            name: "status",
            label: "Trạng thái",
            options: ["open", "closed"].map((value) => ({
                value,
                label: value,
            })),
        },
    ];
    return (
        <div className="product-page mx-auto max-w-6xl p-5 md:p-10">
            <div className="page-heading">
                <div>
                    <p className="eyebrow text-[#16a34a]">CỘNG ĐỒNG CẦU LÔNG</p>
                    <h1 className="font-display text-4xl font-bold">
                        Kết nối đồng đội
                    </h1>
                    <p className="mt-2 text-[#6d8474]">
                        Tìm người chơi, tạo đội và trò chuyện cùng cộng đồng.
                    </p>
                </div>
                <button
                    className="btn"
                    onClick={() => (user ? setForm("post") : auth())}
                >
                    + Tạo bài đăng
                </button>
            </div>
            <div className="page-tabs">
                {[
                    ["feed", "Bảng tin"],
                    ["players", "Tìm người chơi"],
                    ["chat", "Chat & đội nhóm"],
                ].map(([id, label]) => (
                    <button
                        aria-pressed={tab === id}
                        className={tab === id ? "btn" : "btn-outline"}
                        key={id}
                        onClick={() => {
                            setTab(id);
                            setSearch("");
                        }}
                    >
                        {label}
                    </button>
                ))}
            </div>
            {tab !== "chat" && (
                <div className="mb-5 flex flex-wrap gap-3">
                    <input
                        aria-label="Tìm kiếm cộng đồng"
                        className="input"
                        placeholder="Tìm kiếm…"
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setPage(1);
                        }}
                    />
                    {tab === "feed" ? (
                        <select
                            className="input"
                            value={branch}
                            onChange={(e) => {
                                setBranch(e.target.value);
                                setPage(1);
                            }}
                        >
                            <option value="">Tất cả chi nhánh</option>
                            {BRANCHES.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    ) : (
                        <select
                            className="input"
                            value={level}
                            onChange={(e) => setLevel(e.target.value)}
                        >
                            <option value="">Tất cả trình độ</option>
                            {["Sơ cấp", "Trung cấp", "Cao cấp"].map((s) => (
                                <option key={s}>{s}</option>
                            ))}
                        </select>
                    )}
                </div>
            )}
            {tab === "feed" && (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        {posts.map((p) => (
                            <Card key={p.id}>
                                {p.moderation_status !== "approved" && (
                                    <p className="mb-3 rounded-xl bg-amber-50 p-3 text-sm">
                                        {p.moderation_status === "pending"
                                            ? "Bài của bạn đang chờ duyệt."
                                            : "Bài chưa được duyệt."}
                                    </p>
                                )}
                                {p.images?.map((id: number) => (
                                    <img
                                        key={id}
                                        src={"/api/v1/media/" + id}
                                        alt="Ảnh bài viết"
                                        className="mb-3 max-h-52 w-full rounded-xl object-cover"
                                    />
                                ))}
                                <div className="flex justify-between text-xs text-[#6d8474]">
                                    <span>
                                        {p.user.name} · {p.skill_level}
                                    </span>
                                    <span className="badge badge-green">
                                        {p.type}
                                    </span>
                                </div>
                                <h2 className="my-4 text-xl font-bold">
                                    {p.title}
                                </h2>
                                <p className="line-clamp-3 whitespace-pre-wrap text-sm">
                                    {p.content}
                                </p>
                                <p className="mt-3 text-sm text-[#6d8474]">
                                    {p.branch.name} · {stamp(p.starts_at)}
                                </p>
                                <div className="mt-4 flex justify-between border-t border-[#dce4dc] pt-4">
                                    <button
                                        className="text-[#16a34a]"
                                        onClick={() =>
                                            action(() =>
                                                changed(() =>
                                                    api(
                                                        "community/posts/" +
                                                            p.id +
                                                            "/like",
                                                        "PUT",
                                                        { liked: !p.liked },
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        ♥ {p.likes_count}
                                    </button>
                                    <button
                                        className="text-sm font-bold"
                                        onClick={() =>
                                            action(() =>
                                                changed(() =>
                                                    api(
                                                        "community/posts/" +
                                                            p.id +
                                                            "/saved",
                                                        "PUT",
                                                        { active: !p.saved },
                                                    ),
                                                ),
                                            )
                                        }
                                    >
                                        {p.saved ? "Đã lưu" : "Lưu bài"}
                                    </button>
                                    <span className="text-sm">
                                        {p.comments_count} bình luận
                                    </span>
                                    <button
                                        className="font-bold text-[#16a34a]"
                                        onClick={() =>
                                            run(() => loadPost(p.id))
                                        }
                                    >
                                        Chi tiết →
                                    </button>
                                </div>
                            </Card>
                        ))}
                    </div>
                    {!posts.length && (
                        <Empty text="Chưa có bài đăng phù hợp. Hãy tạo bài để tìm đồng đội." />
                    )}
                    <div className="mt-4 flex items-center gap-4">
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
            {tab === "players" && (
                <div className="grid gap-4 sm:grid-cols-3">
                    {players.map((p) => (
                        <Card key={p.id}>
                            <div className="mb-3 grid size-12 place-items-center rounded-full bg-[#e9fbd7] text-xl font-bold">
                                {p.name.charAt(0)}
                            </div>
                            <h2 className="font-bold">{p.name}</h2>
                            <p className="my-3 text-sm text-[#6d8474]">
                                {p.skill_level}
                            </p>
                            {p.id !== user?.id && (
                                <button
                                    className="btn-outline"
                                    onClick={() =>
                                        action(async () => {
                                            const c = await api(
                                                "conversations/direct",
                                                "POST",
                                                { user_id: p.id },
                                            );
                                            setConversation(c.data);
                                            setTab("chat");
                                            await load();
                                        })
                                    }
                                >
                                    Nhắn tin
                                </button>
                            )}
                        </Card>
                    ))}
                    {!players.length && (
                        <Empty text="Chưa có hồ sơ công khai phù hợp." />
                    )}
                </div>
            )}
            {tab === "chat" &&
                (user ? (
                    <div className="chat-layout grid min-h-[500px] md:grid-cols-[270px_minmax(0,1fr)]">
                        <Card className="chat-sidebar">
                            <h2 className="mb-4 text-sm font-bold">
                                Hội thoại của tôi
                            </h2>
                            {conversations.map((c) => (
                                <button
                                    className={
                                        "conversation-item " +
                                        (conversation?.id === c.id
                                            ? "is-active"
                                            : "")
                                    }
                                    key={c.id}
                                    onClick={() => {
                                        setConversation(c);
                                        location.hash =
                                            "community/chat/" + c.id;
                                    }}
                                >
                                    <b>
                                        {c.type === "ai" ? "✦ HUB AI" : c.title}
                                    </b>
                                    {c.type === "ai" && (
                                        <span className="float-right text-xs text-green-700">
                                            Đã ghim
                                        </span>
                                    )}
                                    <p className="mt-1 truncate text-[#6d8474]">
                                        {c.messages?.[0]?.content ??
                                            "Bắt đầu trò chuyện"}
                                    </p>
                                </button>
                            ))}
                            {!conversations.length && (
                                <Empty text="Tìm người chơi hoặc tham gia đội để mở chat." />
                            )}
                        </Card>
                        <Card className="chat-pane">
                            {conversation ? (
                                <>
                                    <div className="chat-heading">
                                        <span>
                                            <Icon
                                                name={
                                                    conversation.type === "ai"
                                                        ? "spark"
                                                        : "chat"
                                                }
                                                size={22}
                                            />
                                        </span>
                                        <div>
                                            <h2>{conversation.title}</h2>
                                            <p>
                                                {conversation.type === "ai"
                                                    ? "Cùng bạn tìm sân cho trận cầu tiếp theo"
                                                    : "Hẹn lịch, trò chuyện, cùng lên sân"}
                                            </p>
                                        </div>
                                    </div>
                                    {conversation.id === "hub-ai" &&
                                        !messages.length && (
                                            <div className="mb-5 rounded-xl bg-green-50 p-5">
                                                <p className="font-bold">
                                                    Bạn muốn lên sân lúc nào?
                                                </p>
                                                <p className="mt-2 text-sm">
                                                    Cho tôi ngày, giờ chơi và
                                                    khu vực. Tôi sẽ tìm sân
                                                    trống và giá cho bạn.
                                                </p>
                                                <button
                                                    className="btn-outline mt-3 text-sm"
                                                    onClick={() =>
                                                        setText(
                                                            "Ngày mai tôi muốn đánh cầu từ 13h tới 15h ở Bình Thạnh",
                                                        )
                                                    }
                                                >
                                                    Ngày mai, 13:00–15:00, Bình
                                                    Thạnh
                                                </button>
                                            </div>
                                        )}
                                    <p className="mb-3 text-xs text-slate-500">
                                        {members
                                            .filter((m) => m.id !== user.id)
                                            .map(
                                                (m) =>
                                                    m.name +
                                                    (m.pivot?.typing_until &&
                                                    new Date(
                                                        m.pivot.typing_until,
                                                    ).getTime() > Date.now()
                                                        ? " đang nhập…"
                                                        : m.last_seen_at &&
                                                            Date.now() -
                                                                new Date(
                                                                    m.last_seen_at,
                                                                ).getTime() <
                                                                120000
                                                          ? " · đang hoạt động"
                                                          : ""),
                                            )
                                            .join(" · ")}
                                    </p>
                                    <div
                                        role="log"
                                        aria-label="Tin nhắn trong hội thoại"
                                        tabIndex={0}
                                        className="chat-messages mb-4 max-h-[420px] space-y-3 overflow-y-auto"
                                    >
                                        {messages.map((m) => (
                                            <div
                                                className={
                                                    "chat-bubble " +
                                                    (m.user_id === user.id
                                                        ? "is-own"
                                                        : "")
                                                }
                                                key={m.id}
                                            >
                                                <small className="text-[#6d8474]">
                                                    {m.user?.name} ·{" "}
                                                    {stamp(m.created_at)}
                                                </small>
                                                <p className="mt-1 whitespace-pre-wrap break-words">
                                                    {m.content}
                                                </p>
                                                {m.result?.courts
                                                    ?.slice(0, 6)
                                                    .map((c: Row) => (
                                                        <div
                                                            key={c.court_id}
                                                            className="chat-result"
                                                        >
                                                            <b>
                                                                {c.branch_name}{" "}
                                                                · {c.court_name}
                                                            </b>
                                                            <p className="mt-1 text-xs text-slate-500">
                                                                {c.address}
                                                            </p>
                                                            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                                                                <b className="text-green-700">
                                                                    {money(
                                                                        c.total_amount,
                                                                    )}
                                                                </b>
                                                                <button
                                                                    className="btn text-xs"
                                                                    onClick={() =>
                                                                        book({
                                                                            branch_id:
                                                                                c.branch_id,
                                                                            court_id:
                                                                                c.court_id,
                                                                            date: c.starts_at.slice(
                                                                                0,
                                                                                10,
                                                                            ),
                                                                            time: c.starts_at.slice(
                                                                                11,
                                                                                16,
                                                                            ),
                                                                            duration:
                                                                                (new Date(
                                                                                    c.ends_at,
                                                                                ).getTime() -
                                                                                    new Date(
                                                                                        c.starts_at,
                                                                                    ).getTime()) /
                                                                                3600000,
                                                                        })
                                                                    }
                                                                >
                                                                    Chọn sân này
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ))}
                                                {m.result?.checked_at && (
                                                    <p className="mt-2 text-xs text-slate-500">
                                                        Cập nhật{" "}
                                                        {stamp(
                                                            m.result.checked_at,
                                                        )}{" "}
                                                        · Lịch trống được kiểm
                                                        tra lại khi đặt.
                                                    </p>
                                                )}
                                                {m.attachments?.map(
                                                    (id: number) => {
                                                        const f = files.find(
                                                            (f) => f.id === id,
                                                        );
                                                        return (
                                                            <a
                                                                key={id}
                                                                className="mt-2 block font-bold text-[#168449]"
                                                                href={
                                                                    "/api/v1/media/" +
                                                                    id
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                {f?.mime.startsWith(
                                                                    "image/",
                                                                ) ? (
                                                                    <img
                                                                        className="max-h-48 rounded-xl"
                                                                        alt={
                                                                            f.name
                                                                        }
                                                                        src={
                                                                            "/api/v1/media/" +
                                                                            id
                                                                        }
                                                                    />
                                                                ) : (
                                                                    (f?.name ??
                                                                    "Tệp đính kèm")
                                                                )}
                                                            </a>
                                                        );
                                                    },
                                                )}
                                                {m.user_id === user.id && (
                                                    <p className="mt-2 text-xs text-slate-500">
                                                        {members.some(
                                                            (p) =>
                                                                p.id !==
                                                                    user.id &&
                                                                p.pivot
                                                                    ?.read_at &&
                                                                new Date(
                                                                    p.pivot
                                                                        .read_at,
                                                                ) >=
                                                                    new Date(
                                                                        m.created_at,
                                                                    ),
                                                        )
                                                            ? "Đã xem"
                                                            : "Đã gửi"}
                                                    </p>
                                                )}
                                                {m.user_id === user.id &&
                                                    !m.deleted && (
                                                        <button
                                                            className="mt-2 text-xs text-[#6d8474]"
                                                            onClick={() =>
                                                                run(
                                                                    async () => {
                                                                        await api(
                                                                            "messages/" +
                                                                                m.id,
                                                                            "DELETE",
                                                                        );
                                                                        setMessages(
                                                                            (
                                                                                await api(
                                                                                    "conversations/" +
                                                                                        conversation.id +
                                                                                        "/messages",
                                                                                )
                                                                            )
                                                                                .data,
                                                                        );
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Thu hồi
                                                        </button>
                                                    )}
                                            </div>
                                        ))}
                                        <div ref={messageEnd} />
                                    </div>
                                    {conversation.id !== "hub-ai" && (
                                        <MediaPicker
                                            files
                                            value={attachments}
                                            onChange={setAttachments}
                                        />
                                    )}
                                    <form
                                        className="chat-composer flex gap-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            run(async () => {
                                                if (
                                                    conversation.id === "hub-ai"
                                                ) {
                                                    const r = await api(
                                                        "ai/chat",
                                                        "POST",
                                                        {
                                                            request_key: key(),
                                                            message: text,
                                                        },
                                                    );
                                                    setMessages((old) => [
                                                        ...old,
                                                        ...r.data,
                                                    ]);
                                                    setText("");
                                                    return;
                                                }
                                                await api(
                                                    "conversations/" +
                                                        conversation.id +
                                                        "/messages",
                                                    "POST",
                                                    {
                                                        request_key: key(),
                                                        content: text,
                                                        attachments,
                                                    },
                                                );
                                                setText("");
                                                setReply(null);
                                                setAttachments([]);
                                                setMessages(
                                                    (
                                                        await api(
                                                            "conversations/" +
                                                                conversation.id +
                                                                "/messages",
                                                        )
                                                    ).data,
                                                );
                                            });
                                        }}
                                    >
                                        <input
                                            aria-label="Tin nhắn"
                                            className="input min-w-0 flex-1"
                                            maxLength={
                                                conversation.id === "hub-ai"
                                                    ? 1000
                                                    : 2000
                                            }
                                            required
                                            value={text}
                                            onChange={(e) => {
                                                setText(e.target.value);
                                                if (
                                                    conversation &&
                                                    conversation.id !== "hub-ai"
                                                )
                                                    api(
                                                        "conversations/" +
                                                            conversation.id +
                                                            "/typing",
                                                        "POST",
                                                    ).catch(() => {});
                                            }}
                                        />
                                        <button
                                            className="btn"
                                            disabled={loading}
                                        >
                                            {loading ? "Đang gửi…" : "Gửi"}
                                        </button>
                                    </form>
                                </>
                            ) : (
                                <Empty text="Chọn một hội thoại." />
                            )}
                        </Card>
                    </div>
                ) : (
                    <Card>
                        <p>Đăng nhập để đọc và gửi tin nhắn.</p>
                        <button className="btn mt-4" onClick={auth}>
                            Đăng nhập
                        </button>
                    </Card>
                ))}
            {post && (
                <Modal title={post.title} close={() => setPost(null)}>
                    <p className="mb-3 text-sm text-[#6d8474]">
                        {post.user?.name} · {stamp(post.starts_at)} ·{" "}
                        {statusText(post.status)}
                    </p>
                    <p className="whitespace-pre-wrap">{post.content}</p>
                    {post.images?.map((id: number) => (
                        <img
                            key={id}
                            className="mt-3 max-h-72 rounded-xl"
                            src={"/api/v1/media/" + id}
                            alt="Ảnh trong bài"
                        />
                    ))}
                    <div className="my-4 flex flex-wrap gap-2">
                        <button
                            className="btn-outline"
                            onClick={async () => {
                                const url =
                                    location.origin +
                                    "/#community/post/" +
                                    post.id;
                                try {
                                    if (navigator.share)
                                        await navigator.share({
                                            title: post.title,
                                            url,
                                        });
                                    else {
                                        await navigator.clipboard.writeText(
                                            url,
                                        );
                                        toast("Đã sao chép liên kết.");
                                    }
                                } catch {}
                            }}
                        >
                            Chia sẻ
                        </button>
                        {user && post.user_id !== user.id && (
                            <button
                                className="btn-outline"
                                onClick={() =>
                                    action(async () => {
                                        await api(
                                            "users/" + post.user_id + "/block",
                                            "PUT",
                                            { active: true },
                                        );
                                        setPost(null);
                                        await load();
                                        toast("Đã chặn tài khoản.");
                                    })
                                }
                            >
                                Chặn tác giả
                            </button>
                        )}
                        {post.user_id === user?.id && (
                            <button
                                className="btn-outline"
                                onClick={() =>
                                    action(async () => {
                                        await api(
                                            "community/posts/" + post.id,
                                            "DELETE",
                                        );
                                        setPost(null);
                                        await load();
                                    })
                                }
                            >
                                Gỡ bài viết
                            </button>
                        )}
                    </div>
                    <div className="my-4 flex flex-wrap gap-2">
                        {post.user_id === user?.id ? (
                            <button
                                className="btn-outline"
                                onClick={() => setForm("edit")}
                            >
                                Sửa / đóng bài
                            </button>
                        ) : (
                            <>
                                <button
                                    className="btn"
                                    onClick={() =>
                                        action(() =>
                                            changed(() =>
                                                api(
                                                    "community/posts/" +
                                                        post.id +
                                                        "/join",
                                                    post.my_join ===
                                                        "accepted" ||
                                                        post.my_join ===
                                                            "pending"
                                                        ? "DELETE"
                                                        : "POST",
                                                ),
                                            ),
                                        )
                                    }
                                >
                                    {post.my_join === "accepted" ||
                                    post.my_join === "pending"
                                        ? "Rời đội"
                                        : "Xin tham gia"}
                                </button>
                                <button
                                    className="btn-outline"
                                    onClick={() =>
                                        user ? setForm("report") : auth()
                                    }
                                >
                                    Báo cáo
                                </button>
                            </>
                        )}
                    </div>
                    <h3 className="my-3 font-bold">Thành viên / yêu cầu</h3>
                    {post.joins?.map((j: Row) => (
                        <div
                            className="my-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-[#f3f6f4] p-3"
                            key={j.id}
                        >
                            <span>
                                {j.user.name} · {statusText(j.status)}
                            </span>
                            {post.user_id === user?.id &&
                                j.status === "pending" && (
                                    <div className="flex gap-2">
                                        {["accepted", "rejected"].map((s) => (
                                            <button
                                                className="btn-outline text-xs"
                                                key={s}
                                                onClick={() =>
                                                    run(() =>
                                                        changed(() =>
                                                            api(
                                                                "community/posts/" +
                                                                    post.id +
                                                                    "/joins/" +
                                                                    j.id,
                                                                "PATCH",
                                                                { status: s },
                                                            ),
                                                        ),
                                                    )
                                                }
                                            >
                                                {s === "accepted"
                                                    ? "Duyệt"
                                                    : "Từ chối"}
                                            </button>
                                        ))}
                                    </div>
                                )}
                        </div>
                    ))}
                    <h3 className="my-3 font-bold">Bình luận</h3>
                    {renderComments(null)}
                    {reply && (
                        <p className="mt-3 text-sm">
                            Đang trả lời {reply.user.name}{" "}
                            <button
                                className="font-bold text-[#168449]"
                                onClick={() => setReply(null)}
                            >
                                Hủy
                            </button>
                        </p>
                    )}
                    <form
                        className="mt-4 flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            action(async () => {
                                await changed(() =>
                                    api(
                                        "community/posts/" +
                                            post.id +
                                            "/comments",
                                        "POST",
                                        {
                                            content: text,
                                            parent_id: reply?.id ?? null,
                                        },
                                    ),
                                );
                                setText("");
                                setReply(null);
                            });
                        }}
                    >
                        <input
                            aria-label="Bình luận"
                            className="input min-w-0 flex-1"
                            required
                            maxLength={2000}
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                        />
                        <button className="btn">Bình luận</button>
                    </form>
                </Modal>
            )}
            {(form === "post" || form === "edit") && (
                <Editor
                    title={form === "post" ? "Tạo bài đăng" : "Sửa bài đăng"}
                    close={() => setForm("")}
                    fields={fields}
                    initial={
                        form === "edit"
                            ? {
                                  ...post,
                                  starts_at: localDateTime(post!.starts_at),
                              }
                            : {
                                  branch_id: BRANCHES[0]?.id,
                                  type: "Tìm người đánh",
                                  skill_level: "Trung cấp",
                                  capacity: 2,
                                  status: "open",
                              }
                    }
                    save={async (d) => {
                        await changed(() =>
                            api(
                                "community/posts" +
                                    (form === "edit" ? "/" + post!.id : ""),
                                form === "edit" ? "PUT" : "POST",
                                { ...d, branch_id: Number(d.branch_id) },
                            ),
                        );
                        await refresh();
                    }}
                />
            )}
            {form === "report" && post && (
                <Editor
                    title="Báo cáo nội dung"
                    close={() => setForm("")}
                    fields={[
                        {
                            name: "reason",
                            label: "Lý do",
                            type: "textarea",
                            required: true,
                        },
                    ]}
                    save={async (d) => {
                        await api(
                            "community/posts/" + post.id + "/report",
                            "POST",
                            d,
                        );
                        toast("Đã gửi báo cáo.");
                    }}
                />
            )}
        </div>
    );
}
