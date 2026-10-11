import { useState } from "react";
import { Logo } from "./Brand";
import { api, useHub } from "./hub";
export default function AuthScreen({ done }: { done: () => void }) {
    const { refresh, data } = useHub();
    const [mode, setMode] = useState("login"),
        [d, set] = useState<Record<string, string>>({}),
        [error, setError] = useState(data?.auth_notice ?? ""),
        [busy, setBusy] = useState(false),
        [token, setToken] = useState(""),
        [demoCode, setDemoCode] = useState("");
    function field(name: string, label: string, type = "text") {
        return (
            <label className="block text-sm font-semibold">
                {label}
                <input
                    className="input mt-2 w-full"
                    type={type}
                    required
                    value={d[name] ?? ""}
                    minLength={name.includes("password") ? 8 : undefined}
                    onChange={(e) => set({ ...d, [name]: e.target.value })}
                />
            </label>
        );
    }
    return (
        <div className="grid min-h-[calc(100vh-80px)] lg:grid-cols-2">
            <div className="auth-art relative hidden overflow-hidden p-14 text-white lg:flex lg:flex-col lg:justify-between">
                <div className="court-lines" />
                <Logo />
                <div className="relative">
                    <p className="eyebrow text-[#b7ff36]">
                        made for every rally
                    </p>
                    <h1 className="font-display mt-4 text-6xl font-black">
                        Ra sân.
                        <br />
                        Kết nối.
                        <br />
                        <span className="text-[#b7ff36]">Bứt phá.</span>
                    </h1>
                    <p className="mt-6 text-white/60">
                        Đặt sân, tìm đồng đội và quản lý lịch chơi trong một tài
                        khoản.
                    </p>
                </div>
                <p className="relative text-sm text-white/40">
                    BADMINTON HUB · TP. HỒ CHÍ MINH
                </p>
            </div>
            <div className="grid place-items-center bg-white p-6">
                <div className="w-full max-w-md">
                    <h2 className="font-display text-3xl font-bold">
                        {
                            {
                                login: "Chào mừng trở lại",
                                register: "Tạo tài khoản",
                                send: "Quên mật khẩu",
                                verify: "Xác nhận email",
                                reset: "Mật khẩu mới",
                            }[mode]
                        }
                    </h2>
                    <p className="mb-6 mt-2 text-[#6d8474]">
                        Tài khoản dành cho cộng đồng cầu lông
                    </p>
                    {mode === "login" && data?.google_enabled && (
                        <a
                            className="btn-outline mb-4 block text-center"
                            href="/auth/google"
                        >
                            Tiếp tục với Google
                        </a>
                    )}
                    {mode === "login" && data?.demo?.google && (
                        <button
                            className="btn-outline mb-4 w-full"
                            disabled={busy}
                            onClick={async () => {
                                setBusy(true);
                                try {
                                    await api("demo/google", "POST");
                                    await refresh();
                                    done();
                                } catch (e) {
                                    setError((e as Error).message);
                                } finally {
                                    setBusy(false);
                                }
                            }}
                        >
                            Thử đăng nhập Google · Demo
                        </button>
                    )}
                    <form
                        className="space-y-4"
                        onSubmit={async (e) => {
                            e.preventDefault();
                            setBusy(true);
                            setError("");
                            try {
                                if (mode === "login" || mode === "register") {
                                    await api(mode, "POST", d);
                                    await refresh();
                                    done();
                                } else if (mode === "send") {
                                    const r = await api(
                                        "recovery/send",
                                        "POST",
                                        { email: d.email },
                                    );
                                    setError(r.message);
                                    setDemoCode(r.demo_code ?? "");
                                    setMode("verify");
                                } else if (mode === "verify") {
                                    const r = await api(
                                        "recovery/verify",
                                        "POST",
                                        { email: d.email, code: d.code },
                                    );
                                    setToken(r.data.reset_token);
                                    setMode("reset");
                                    setError("");
                                } else {
                                    await api("recovery/reset", "POST", {
                                        email: d.email,
                                        reset_token: token,
                                        password: d.password,
                                        password_confirmation:
                                            d.password_confirmation,
                                    });
                                    setMode("login");
                                    setError(
                                        "Đã đổi mật khẩu, vui lòng đăng nhập.",
                                    );
                                }
                            } catch (err) {
                                setError((err as Error).message);
                            } finally {
                                setBusy(false);
                            }
                        }}
                    >
                        {mode === "register" && (
                            <>
                                {field("name", "Họ và tên")}
                                {field("phone", "Số điện thoại", "tel")}
                            </>
                        )}
                        {(mode === "login" ||
                            mode === "register" ||
                            mode === "send") &&
                            field(
                                "email",
                                mode === "login"
                                    ? "Email hoặc điện thoại đã xác thực"
                                    : "Email",
                                mode === "login" ? "text" : "email",
                            )}
                        {(mode === "login" ||
                            mode === "register" ||
                            mode === "reset") &&
                            field("password", "Mật khẩu", "password")}
                        {(mode === "register" || mode === "reset") &&
                            field(
                                "password_confirmation",
                                "Nhập lại mật khẩu",
                                "password",
                            )}
                        {mode === "verify" && (
                            <>
                                <p className="text-sm">
                                    {demoCode ? (
                                        "Nhập mã demo hiển thị bên dưới."
                                    ) : (
                                        <>
                                            Nhập mã 6 chữ số đã gửi tới{" "}
                                            {d.email}.
                                        </>
                                    )}
                                </p>
                                {demoCode && (
                                    <p
                                        role="status"
                                        className="rounded-xl bg-amber-50 p-4 text-sm"
                                    >
                                        Mã demo:{" "}
                                        <b className="text-xl tracking-widest">
                                            {demoCode}
                                        </b>{" "}
                                        · Không gửi email thật.
                                    </p>
                                )}
                                {field("code", "Mã xác nhận")}
                            </>
                        )}
                        {error && (
                            <p
                                role="alert"
                                className="rounded-xl bg-[#f3f6f4] p-3 text-sm"
                            >
                                {error}
                            </p>
                        )}
                        <button className="btn w-full" disabled={busy}>
                            {busy
                                ? "Đang xử lý…"
                                : mode === "login"
                                  ? "Đăng nhập"
                                  : mode === "register"
                                    ? "Đăng ký"
                                    : mode === "send"
                                      ? "Gửi mã xác nhận"
                                      : mode === "verify"
                                        ? "Xác nhận mã"
                                        : "Đổi mật khẩu"}
                        </button>
                    </form>
                    <div className="mt-6 flex flex-wrap justify-between gap-3 text-sm text-[#16a34a]">
                        <button
                            onClick={() => {
                                setMode(
                                    mode === "register" ? "login" : "register",
                                );
                                setError("");
                            }}
                        >
                            {mode === "register"
                                ? "Đã có tài khoản? Đăng nhập"
                                : "Chưa có tài khoản? Đăng ký"}
                        </button>
                        <button
                            disabled={!data?.email_enabled}
                            title={
                                !data?.email_enabled
                                    ? "Vui lòng liên hệ hỗ trợ để khôi phục tài khoản."
                                    : undefined
                            }
                            onClick={() => {
                                setMode(mode === "send" ? "login" : "send");
                                setError("");
                            }}
                        >
                            Quên mật khẩu?
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
