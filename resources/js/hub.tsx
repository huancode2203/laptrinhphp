import {
    createContext,
    useContext,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from "react";
import { COMMUNITY_POSTS, hydrate, type Row } from "./data";
export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
        public fields: Record<string, string[]> = {},
    ) {
        super(message);
    }
}
async function responseBody(r: Response) {
    let j: any = {};
    try {
        j = await r.json();
    } catch {
        if (r.ok && r.status !== 204)
            throw new ApiError(
                "Dữ liệu chưa tải đầy đủ. Vui lòng thử lại thao tác.",
                r.status,
            );
    }
    if (r.status === 401) {
        window.dispatchEvent(new CustomEvent("hub:auth-expired"));
        throw new ApiError(
            "Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.",
            401,
        );
    }
    if (!r.ok)
        throw new ApiError(
            r.status >= 500
                ? "Hệ thống đang gặp sự cố. Lựa chọn của bạn vẫn được giữ; vui lòng thử lại."
                : r.status === 419
                  ? "Phiên bảo mật đã thay đổi. Vui lòng thử lại thao tác."
                  : Object.values(j.errors ?? {})
                        .flat()
                        .join(" ") ||
                    (r.status === 429
                        ? "Bạn đã thử nhiều lần liên tiếp. Vui lòng chờ " +
                          (Number(r.headers.get("Retry-After")) || 60) +
                          " giây rồi thử lại."
                        : !j.message ||
                            /^(Forbidden|Not Found|Unauthorized|Unauthenticated\.?|Too Many Requests|Unprocessable Content|Unprocessable Entity|Bad Request|Conflict|Method Not Allowed|The given data was invalid\.?|This action is unauthorized\.?)$/i.test(
                                j.message,
                            )
                          ? (
                                {
                                    403: "Bạn chưa có quyền thực hiện thao tác này. Vui lòng kiểm tra tài khoản hoặc liên hệ quản lý.",
                                    404: "Nội dung này không còn tồn tại hoặc bạn không được phép xem.",
                                    422: "Thông tin chưa phù hợp. Kiểm tra các ô nhập và thử lại.",
                                    409: "Dữ liệu vừa thay đổi. Vui lòng tải lại trước khi tiếp tục.",
                                } as Record<number, string>
                            )[r.status]
                          : j.message) ||
                    "Chưa thực hiện được yêu cầu. Vui lòng thử lại.",
            r.status,
            j.errors ?? {},
        );
    return j;
}
async function request(url: string, options: RequestInit) {
    try {
        return await fetch(url, options);
    } catch {
        throw new ApiError(
            "Không kết nối được hệ thống. Kiểm tra kết nối mạng và thử lại.",
            0,
        );
    }
}
export async function api(
    path: string,
    method = "GET",
    body?: Row,
): Promise<any> {
    let csrf = "";
    if (method !== "GET") {
        const c = await request("/api/v1/csrf", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });
        csrf = (await responseBody(c)).csrf_token;
    }
    const r = await request("/api/v1/" + path, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf,
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    return responseBody(r);
}
export async function upload(file: File) {
    const c = await request("/api/v1/csrf", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    const token = (await responseBody(c)).csrf_token;
    const body = new FormData();
    body.append("file", file);
    const r = await request("/api/v1/media", {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": token },
        body,
    });
    return responseBody(r);
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
    const refreshVersion = useRef(0),
        running = useRef(false);
    async function refresh() {
        const version = ++refreshVersion.current;
        const [b, p] = await Promise.allSettled([
            api("hub"),
            api("community/posts"),
        ]);
        if (b.status === "rejected") throw b.reason;
        if (version !== refreshVersion.current) return;
        hydrate(
            b.value.data,
            p.status === "fulfilled"
                ? (p.value.data.data ?? p.value.data)
                : COMMUNITY_POSTS,
        );
        setData(b.value.data);
        if (b.value.data.auth_notice) toast(b.value.data.auth_notice);
        setFailure("");
    }
    function toast(s: string) {
        setMessage(s);
    }
    async function run(fn: () => Promise<void>) {
        if (running.current) return;
        running.current = true;
        setLoading(true);
        try {
            await fn();
        } catch (e) {
            toast((e as Error).message);
        } finally {
            running.current = false;
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
