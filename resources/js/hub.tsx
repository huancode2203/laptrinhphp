import {
    createContext,
    useContext,
    useEffect,
    useState,
    type ReactNode,
} from "react";
import { hydrate, type Row } from "./data";
let csrf = "";
export async function api(
    path: string,
    method = "GET",
    body?: Row,
): Promise<any> {
    if (method !== "GET") {
        const c = await fetch("/api/v1/csrf", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });
        csrf = (await c.json()).csrf_token;
    }
    const r = await fetch("/api/v1/" + path, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf,
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    let j: any = {};
    try {
        j = await r.json();
    } catch {}
    if (r.status === 401) {
        window.dispatchEvent(new CustomEvent("hub:auth-expired"));
        throw new Error("Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.");
    }
    if (!r.ok)
        throw new Error(
            Object.values(j.errors ?? {})
                .flat()
                .join(" ") ||
                j.message ||
                "Không thực hiện được yêu cầu (" + r.status + ").",
        );
    return j;
}
export async function upload(file: File) {
    const c = await fetch("/api/v1/csrf", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    const token = (await c.json()).csrf_token;
    const body = new FormData();
    body.append("file", file);
    const r = await fetch("/api/v1/media", {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": token },
        body,
    });
    const j = await r.json();
    if (r.status === 401) {
        window.dispatchEvent(new CustomEvent("hub:auth-expired"));
        throw new Error("Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.");
    }
    if (!r.ok)
        throw new Error(
            Object.values(j.errors ?? {})
                .flat()
                .join(" ") ||
                j.message ||
                "Chưa tải được tệp.",
        );
    return j;
}
export const key = () => crypto.randomUUID();
type Hub = {
    data: Row | null;
    refresh: () => Promise<void>;
    toast: (s: string) => void;
    run: (fn: () => Promise<void>) => Promise<void>;
    loading: boolean;
};
const Context = createContext<Hub>(null!);
export const useHub = () => useContext(Context);
export function HubProvider({ children }: { children: ReactNode }) {
    const [data, setData] = useState<Row | null>(null),
        [message, setMessage] = useState(""),
        [loading, setLoading] = useState(false),
        [failure, setFailure] = useState("");
    async function refresh() {
        const [b, p] = await Promise.all([api("hub"), api("community/posts")]);
        hydrate(b.data, p.data.data ?? p.data);
        setData(b.data);
        if (b.data.auth_notice) toast(b.data.auth_notice);
        setFailure("");
    }
    function toast(s: string) {
        setMessage(s);
    }
    async function run(fn: () => Promise<void>) {
        if (loading) return;
        setLoading(true);
        try {
            await fn();
        } catch (e) {
            toast((e as Error).message);
        } finally {
            setLoading(false);
        }
    }
    useEffect(() => {
        refresh().catch((e) => setFailure(e.message));
    }, []);
    useEffect(() => {
        if (!message) return;
        const timer = setTimeout(() => setMessage(""), 6500);
        return () => clearTimeout(timer);
    }, [message]);
    if (!data)
        return (
            <div className="grid min-h-screen place-items-center bg-[#0d1f2d] text-white">
                <div>
                    {failure ? (
                        <>
                            <p>{failure}</p>
                            <button
                                className="btn mt-4"
                                onClick={() =>
                                    refresh().catch((e) =>
                                        setFailure(e.message),
                                    )
                                }
                            >
                                Thử lại
                            </button>
                        </>
                    ) : (
                        "Đang tải Badminton Hub…"
                    )}
                </div>
            </div>
        );
    return (
        <Context.Provider value={{ data, refresh, toast, run, loading }}>
            {children}
            {message && (
                <div
                    role="status"
                    className="fixed bottom-5 left-1/2 z-[100] w-[min(90vw,600px)] -translate-x-1/2 rounded-xl bg-[#0d1f2d] p-4 text-white shadow-xl"
                    onClick={() => setMessage("")}
                >
                    {message}
                </div>
            )}
            {loading && (
                <div className="fixed right-4 top-4 z-[110] rounded-full bg-[#b7ff36] px-4 py-2 text-sm font-bold">
                    Đang xử lý…
                </div>
            )}
        </Context.Provider>
    );
}
