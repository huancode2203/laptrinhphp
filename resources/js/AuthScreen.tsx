import { useState } from "react";
import GoogleButton from "./GoogleButton";
import Icon, { type IconName } from "./Icon";
import { Logo } from "./Brand";
import { ApiError, api, useHub } from "./hub";
import { formErrors } from "./ui";

export default function AuthScreen({
    done,
}: {
    done: (role?: string) => void;
}) {
    const { refresh, data, loading } = useHub();
    const [mode, setMode] = useState("login"),
        [values, setValues] = useState<Record<string, string>>({}),
        [error, setError] = useState(data?.auth_notice ?? ""),
        [fields, setFields] = useState<Record<string, string[]>>({}),
        [notice, setNotice] = useState(""),
        [busy, setBusy] = useState(false),
        [showPassword, setShowPassword] = useState(false),
        [token, setToken] = useState(""),
        [demoCode, setDemoCode] = useState("");
    const accountMode = mode === "login" || mode === "register";
    const locked = busy || loading;
    function switchMode(next: string) {
        window.scrollTo(0, 0);
        setMode(next);
        setError("");
        setFields({});
        setNotice("");
        setShowPassword(false);
        setValues((previous) => ({
            ...previous,
            password: "",
            password_confirmation: "",
            code: "",
        }));
    }
    function field(
        name: string,
        label: string,
        options: {
            type?: string;
            icon?: IconName;
            placeholder?: string;
            optional?: boolean;
            hint?: string;
        } = {},
    ) {
        const password = options.type === "password";
        const errors =
            fields[name] ??
            (name === "password_confirmation"
                ? fields.password?.filter((message) =>
                      message.includes("giống nhau"),
                  )
                : undefined);
        return (
            <label
                className={
                    "form-field " + (name === "name" ? "form-field-wide" : "")
                }
            >
                <span>
                    {label}
                    {options.optional ? (
                        <small className="optional-mark">Tuỳ chọn</small>
                    ) : (
                        <span className="required-mark">*</span>
                    )}
                </span>
                <div className="auth-input-wrap">
                    <Icon name={options.icon ?? "user"} size={18} />
                    <input
                        className="input"
                        name={name}
                        aria-label={label}
                        type={
                            password && showPassword
                                ? "text"
                                : (options.type ?? "text")
                        }
                        required={!options.optional}
                        placeholder={options.placeholder}
                        value={values[name] ?? ""}
                        autoComplete={
                            name === "password"
                                ? mode === "login"
                                    ? "current-password"
                                    : "new-password"
                                : name === "password_confirmation"
                                  ? "new-password"
                                  : name === "code"
                                    ? "one-time-code"
                                    : name === "phone"
                                      ? "tel"
                                      : name === "name"
                                        ? "name"
                                        : mode === "login"
                                          ? "username"
                                          : "email"
                        }
                        minLength={password && mode !== "login" ? 8 : undefined}
                        maxLength={
                            password
                                ? 72
                                : name === "code"
                                  ? 6
                                  : name === "name"
                                    ? 100
                                    : name === "phone"
                                      ? 30
                                      : 255
                        }
                        inputMode={
                            name === "code"
                                ? "numeric"
                                : name === "phone"
                                  ? "tel"
                                  : undefined
                        }
                        pattern={name === "code" ? "[0-9]{6}" : undefined}
                        aria-invalid={!!errors?.length}
                        aria-describedby={
                            errors?.length
                                ? "auth-error-" + name
                                : options.hint
                                  ? "auth-hint-" + name
                                  : undefined
                        }
                        onChange={(event) => {
                            const value = event.target.value;
                            setValues((previous) => ({
                                ...previous,
                                [name]: value,
                            }));
                            setFields((previous) => {
                                const next = { ...previous };
                                delete next[name];
                                if (password) delete next.password;
                                return next;
                            });
                            setError("");
                        }}
                    />
                    {password && (
                        <button
                            type="button"
                            className="password-toggle"
                            aria-label={
                                showPassword ? "Ẩn mật khẩu" : "Hiện mật khẩu"
                            }
                            onClick={() => setShowPassword(!showPassword)}
                        >
                            <Icon
                                name={showPassword ? "eyeOff" : "eye"}
                                size={18}
                            />
                        </button>
                    )}
                </div>
                {errors?.length ? (
                    <small id={"auth-error-" + name} className="field-error">
                        {errors.join(" ")}
                    </small>
                ) : (
                    options.hint && (
                        <small id={"auth-hint-" + name}>{options.hint}</small>
                    )
                )}
            </label>
        );
    }
    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (locked) return;
        const payload = Object.fromEntries(
            new FormData(event.currentTarget),
        ) as Record<string, string>;
        setValues((previous) => ({ ...previous, ...payload }));
        const invalid = formErrors(event.currentTarget);
        if (Object.keys(invalid).length) {
            setFields(invalid);
            setError("Kiểm tra các ô được đánh dấu bên dưới rồi thử lại.");
            return;
        }
        setBusy(true);
        setError("");
        setFields({});
        setNotice("");
        try {
            if (
                (mode === "register" || mode === "reset") &&
                payload.password !== payload.password_confirmation
            ) {
                setFields({
                    password_confirmation: [
                        "Hai mật khẩu chưa giống nhau. Vui lòng kiểm tra lại.",
                    ],
                });
                return;
            }
            if (accountMode) {
                const response = await api(mode, "POST", payload);
                await refresh();
                done(response.data?.role);
            } else if (mode === "send") {
                const response = await api("recovery/send", "POST", {
                    email: payload.email,
                });
                setDemoCode(response.demo_code ?? "");
                setMode("verify");
                setNotice(response.message);
            } else if (mode === "verify") {
                const response = await api("recovery/verify", "POST", {
                    email: values.email,
                    code: payload.code,
                });
                setToken(response.data.reset_token);
                setMode("reset");
            } else {
                await api("recovery/reset", "POST", {
                    email: values.email,
                    reset_token: token,
                    password: payload.password,
                    password_confirmation: payload.password_confirmation,
                });
                switchMode("login");
                setNotice(
                    "Mật khẩu đã được cập nhật. Bạn có thể đăng nhập bằng mật khẩu mới.",
                );
            }
        } catch (failure) {
            const messages = failure instanceof ApiError ? failure.fields : {};
            setFields(messages);
            setError(
                Object.keys(messages).length
                    ? "Kiểm tra các ô được đánh dấu bên dưới rồi thử lại."
                    : (failure as Error).message,
            );
        } finally {
            setBusy(false);
        }
    }
    return (
        <main
            className={
                "auth-page " + (mode === "register" ? "auth-register" : "")
            }
        >
            <aside className="auth-story">
                <Logo />
                <div className="auth-story-copy">
                    <span className="auth-story-tag">
                        <Icon name="racket" size={16} />
                        HẸN NHAU TRÊN SÂN
                    </span>
                    <h1>
                        Mỗi buổi chơi.
                        <br />
                        <em>Một kết nối mới.</em>
                    </h1>
                    <p>
                        Chọn sân hợp gu, giữ lịch quen và tìm những người chơi
                        cùng nhịp với bạn.
                    </p>
                    <div className="auth-story-benefits">
                        <span>
                            <Icon name="calendar" size={17} />
                            Lịch chơi trong tầm tay
                        </span>
                        <span>
                            <Icon name="people" size={17} />
                            Kết nối cộng đồng
                        </span>
                    </div>
                </div>
                <div className="auth-photo-caption">
                    <span>RA SÂN & BỨT PHÁ</span>
                    <Icon name="northeast" size={24} />
                </div>
            </aside>
            <section className="auth-form-panel">
                <div className="auth-form-card">
                    {accountMode ? (
                        <div
                            className="auth-tabs"
                            role="tablist"
                            aria-label="Tài khoản"
                        >
                            <button
                                role="tab"
                                aria-selected={mode === "login"}
                                disabled={locked}
                                onClick={() => switchMode("login")}
                            >
                                Đăng nhập
                            </button>
                            <button
                                role="tab"
                                aria-selected={mode === "register"}
                                disabled={locked}
                                onClick={() => switchMode("register")}
                            >
                                Đăng ký
                            </button>
                        </div>
                    ) : (
                        <button
                            className="auth-back"
                            disabled={locked}
                            onClick={() => switchMode("login")}
                        >
                            ← Quay lại đăng nhập
                        </button>
                    )}
                    <span className="auth-card-kicker">BADMINTON HUB</span>
                    <h2>
                        {
                            {
                                login: "Chào mừng trở lại",
                                register: "Sẵn sàng ra sân?",
                                send: "Lấy lại mật khẩu",
                                verify: "Kiểm tra hộp thư",
                                reset: "Đặt mật khẩu mới",
                            }[mode]
                        }
                    </h2>
                    <p className="auth-card-description">
                        {
                            {
                                login: "Đăng nhập để tiếp tục lịch chơi và kết nối đồng đội.",
                                register:
                                    "Tạo tài khoản để đặt sân, lưu lịch và tham gia cộng đồng.",
                                send: "Nhập email của tài khoản. Chúng tôi sẽ gửi mã để bạn xác nhận.",
                                verify:
                                    "Nhập mã 6 chữ số gửi tới " +
                                    values.email +
                                    ".",
                                reset: "Chọn mật khẩu mới để bảo vệ tài khoản của bạn.",
                            }[mode]
                        }
                    </p>
                    {accountMode && data?.google_enabled && (
                        <>
                            <GoogleButton
                                disabled={busy}
                                after={async (role) => {
                                    await refresh();
                                    done(role);
                                }}
                            />
                            <div className="auth-divider">
                                <span>hoặc dùng email</span>
                            </div>
                        </>
                    )}
                    {mode === "login" && data?.demo?.google && (
                        <button
                            className="btn-outline mb-4 w-full"
                            disabled={locked}
                            onClick={async () => {
                                setBusy(true);
                                try {
                                    const response = await api(
                                        "demo/google",
                                        "POST",
                                    );
                                    await refresh();
                                    done(response.data?.role);
                                } catch (failure) {
                                    setError((failure as Error).message);
                                } finally {
                                    setBusy(false);
                                }
                            }}
                        >
                            Thử đăng nhập Google · Demo
                        </button>
                    )}
                    <form
                        className="hub-form auth-form"
                        onSubmit={submit}
                        noValidate
                    >
                        {notice && (
                            <div role="status" className="form-notice">
                                <Icon name="check" size={18} />
                                <span>{notice}</span>
                            </div>
                        )}
                        {mode === "register" && (
                            <div className="form-grid">
                                {field("name", "Họ và tên", {
                                    icon: "user",
                                    placeholder: "Nguyễn Minh Anh",
                                })}
                                {field("phone", "Số điện thoại", {
                                    type: "tel",
                                    icon: "phone",
                                    placeholder: "090 123 4567",
                                    optional: true,
                                })}
                                {field("email", "Email", {
                                    type: "email",
                                    icon: "mail",
                                    placeholder: "minhanh@example.com",
                                })}
                            </div>
                        )}
                        {(mode === "login" || mode === "send") &&
                            field(
                                "email",
                                mode === "login"
                                    ? "Email hoặc số điện thoại"
                                    : "Email",
                                {
                                    type: mode === "login" ? "text" : "email",
                                    icon: "mail",
                                    placeholder:
                                        mode === "login"
                                            ? "Email hoặc số đã xác thực"
                                            : "Email của tài khoản",
                                },
                            )}
                        {(accountMode || mode === "reset") &&
                            field("password", "Mật khẩu", {
                                type: "password",
                                icon: "lock",
                                placeholder:
                                    mode === "login"
                                        ? "Nhập mật khẩu"
                                        : "Tạo mật khẩu",
                                hint:
                                    mode !== "login"
                                        ? "Ít nhất 8 ký tự, nên kết hợp chữ và số."
                                        : undefined,
                            })}
                        {(mode === "register" || mode === "reset") &&
                            field(
                                "password_confirmation",
                                "Nhập lại mật khẩu",
                                {
                                    type: "password",
                                    icon: "lock",
                                    placeholder: "Nhập lại mật khẩu vừa tạo",
                                },
                            )}
                        {mode === "verify" && (
                            <>
                                {demoCode && (
                                    <div
                                        role="status"
                                        className="form-demo-code"
                                    >
                                        Mã thử nghiệm: <b>{demoCode}</b>
                                        <small>
                                            Mã này dùng cho bản demo; chưa có
                                            email được gửi.
                                        </small>
                                    </div>
                                )}
                                {field("code", "Mã xác nhận", {
                                    icon: "shield",
                                    placeholder: "6 chữ số",
                                })}
                                <button
                                    type="button"
                                    className="auth-text-button"
                                    disabled={locked}
                                    onClick={() => switchMode("send")}
                                >
                                    Đổi email hoặc gửi lại mã
                                </button>
                            </>
                        )}
                        {mode === "login" && (
                            <button
                                type="button"
                                className="auth-forgot"
                                disabled={locked || !data?.email_enabled}
                                onClick={() => switchMode("send")}
                            >
                                Quên mật khẩu?
                            </button>
                        )}
                        {error && (
                            <div role="alert" className="form-alert">
                                <Icon name="help" size={18} />
                                <span>{error}</span>
                            </div>
                        )}
                        <button className="btn auth-submit" disabled={locked}>
                            {locked
                                ? "Đang xử lý…"
                                : {
                                      login: "Đăng nhập",
                                      register: "Tạo tài khoản",
                                      send: "Gửi mã xác nhận",
                                      verify: "Xác nhận mã",
                                      reset: "Lưu mật khẩu mới",
                                  }[mode]}
                            <Icon name="arrow" size={18} />
                        </button>
                    </form>
                    <p className="auth-bottom-note">
                        <Icon name="shield" size={15} />
                        Thông tin cá nhân được bảo vệ trong tài khoản của bạn.
                    </p>
                </div>
            </section>
        </main>
    );
}
