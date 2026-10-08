import { useEffect, useState } from "react";
import { api, key, useHub } from "./hub";
import { BRANCHES, stamp, localDateTime, statusText, type Row } from "./data";
import { Card, Editor, Empty, Modal } from "./ui";
export default function CommunityApp({ auth }: { auth: () => void }) {
    const { data, run, toast, refresh } = useHub();
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
    const user = data!.user;
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
            setConversations(c.data);
        }
    }
    async function loadPost(id: number) {
        const p = await api("community/posts/" + id);
        setPost(p.data);
    }
    useEffect(() => {
        load().catch((e) => toast(e.message));
    }, [search, branch, level, page, user?.id]);
    useEffect(() => {
        if (!conversation) return;
        let live = true;
        const get = () =>
            api("conversations/" + conversation.id + "/messages")
                .then((r) => {
                    if (live) setMessages(r.data);
                })
                .catch((e) => {
                    if (live) toast(e.message);
                });
        get();
        const t = setInterval(get, 7000);
        return () => {
            live = false;
            clearInterval(t);
        };
    }, [conversation?.id]);
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
        <div className="mx-auto max-w-6xl p-5 md:p-10">
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="eyebrow text-[#16a34a]">community court</p>
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
            <div className="mb-6 flex flex-wrap gap-2">
                {[
                    ["feed", "Bảng tin"],
                    ["players", "Tìm người chơi"],
                    ["chat", "Chat & đội nhóm"],
                ].map(([id, label]) => (
                    <button
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
                    <div className="grid min-h-[500px] gap-4 md:grid-cols-[280px_1fr]">
                        <Card>
                            <h2 className="mb-4 font-bold">
                                Hội thoại của tôi
                            </h2>
                            {conversations.map((c) => (
                                <button
                                    className={
                                        "mb-2 w-full rounded-xl p-3 text-left text-sm " +
                                        (conversation?.id === c.id
                                            ? "bg-[#e9fbd7]"
                                            : "bg-[#f3f6f4]")
                                    }
                                    key={c.id}
                                    onClick={() => setConversation(c)}
                                >
                                    <b>{c.title}</b>
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
                        <Card>
                            {conversation ? (
                                <>
                                    <h2 className="mb-4 font-bold">
                                        {conversation.title}
                                    </h2>
                                    <div className="mb-4 max-h-[420px] space-y-3 overflow-y-auto">
                                        {messages.map((m) => (
                                            <div
                                                className={
                                                    "rounded-xl p-3 text-sm " +
                                                    (m.user_id === user.id
                                                        ? "ml-10 bg-[#e9fbd7]"
                                                        : "mr-10 bg-[#f3f6f4]")
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
                                    </div>
                                    <form
                                        className="flex gap-2"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            run(async () => {
                                                await api(
                                                    "conversations/" +
                                                        conversation.id +
                                                        "/messages",
                                                    "POST",
                                                    {
                                                        request_key: key(),
                                                        content: text,
                                                    },
                                                );
                                                setText("");
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
                                            maxLength={2000}
                                            required
                                            value={text}
                                            onChange={(e) =>
                                                setText(e.target.value)
                                            }
                                        />
                                        <button className="btn">Gửi</button>
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
                    {post.comments?.map((c: Row) => (
                        <div
                            className="my-2 rounded-lg bg-[#f3f6f4] p-3 text-sm"
                            key={c.id}
                        >
                            <b>{c.user.name}</b>
                            <p className="mt-1 whitespace-pre-wrap">
                                {c.content}
                            </p>
                            {(c.user_id === user?.id ||
                                user?.role === "admin") && (
                                <button
                                    className="text-xs text-red-600"
                                    onClick={() =>
                                        run(() =>
                                            changed(() =>
                                                api(
                                                    "community/comments/" +
                                                        c.id,
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
                    ))}
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
                                        { content: text },
                                    ),
                                );
                                setText("");
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
