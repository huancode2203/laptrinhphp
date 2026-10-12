import { useState } from "react";
import Icon, { type IconName } from "./Icon";
import GoogleButton from "./GoogleButton";
import MediaPicker from "./MediaPicker";
import { api, useHub } from "./hub";
import { Modal } from "./ui";
import type { Row } from "./data";

export default function Profile({
    account,
    edit,
    password,
    verifyEmail,
    changed,
}: {
    account: Row | null;
    edit: () => void;
    password: () => void;
    verifyEmail: () => void;
    changed: (fn: () => Promise<any>) => Promise<void>;
}) {
    const { data, run, toast, loading } = useHub();
    const user = data!.user;
    const [avatarOpen, setAvatarOpen] = useState(false),
        [avatar, setAvatar] = useState<number[]>([]);
    const [phone, setPhone] = useState(user.phone ?? ""),
        [code, setCode] = useState("");
    const [phoneOpen, setPhoneOpen] = useState(false),
        [sent, setSent] = useState(false),
        [demoCode, setDemoCode] = useState("");
    const stats = account?.profile_stats;
    const initial = user.name
        .split(" ")
        .slice(-2)
        .map((v: string) => v[0])
        .join("");
    const member = account?.membership?.plan?.name ?? "Basic";
    const roles: Record<string, string> = {
        admin: "Quản trị viên",
        manager: "Quản lý sân",
        staff: "Nhân viên sân",
        cashier: "Thu ngân",
        customer: "Người chơi",
    };
    return (
        <div className="profile-layout">
            <section className="profile-hero">
                <div className="profile-cover">
                    <div className="court-lines" />
                    <span>YOUR NEXT RALLY STARTS HERE</span>
                    <Icon name="racket" size={58} />
                </div>
                <div className="profile-identity">
                    <button
                        className="profile-avatar"
                        aria-label="Đổi ảnh đại diện"
                        onClick={() => {
                            setAvatar([]);
                            setAvatarOpen(true);
                        }}
                    >
                        {user.avatar_path ? (
                            <img src={user.avatar_path} alt={user.name} />
                        ) : (
                            <span>{initial}</span>
                        )}
                        <i>
                            <Icon name="edit" size={14} />
                        </i>
                    </button>
                    <div className="player-profile-name">
                        <p className="eyebrow">
                            {roles[user.role] ?? "Người chơi"}
                        </p>
                        <h2>{user.name}</h2>
                        <p>
                            <Icon name="pin" size={14} /> TP. Hồ Chí Minh{" "}
                            <span>·</span> {user.skill_level}
                        </p>
                    </div>
                    <button className="btn-outline profile-edit" onClick={edit}>
                        <Icon name="edit" size={16} /> Chỉnh sửa hồ sơ
                    </button>
                </div>
                <div className="profile-badges">
                    <span>
                        <Icon name="trophy" size={15} /> Hội viên {member}
                    </span>
                    <span>
                        <Icon name="people" size={15} />{" "}
                        {user.public_profile
                            ? "Kết nối cộng đồng"
                            : "Hồ sơ riêng tư"}
                    </span>
                    {user.google_connected && (
                        <span>
                            <Icon name="shield" size={15} /> Đã liên kết Google
                        </span>
                    )}
                </div>
                <div className="profile-stats">
                    {[
                        ["Buổi chơi hoàn tất", stats?.sessions, "racket"],
                        ["Giờ trên sân", stats?.hours, "clock"],
                        ["Sân yêu thích", stats?.favorites, "heart"],
                    ].map(([label, value, icon]) => (
                        <div key={String(label)}>
                            <Icon name={icon as IconName} size={21} />
                            <strong>{value ?? "—"}</strong>
                            <span>{label}</span>
                        </div>
                    ))}
                </div>
            </section>
            <div className="profile-columns">
                <section className="profile-panel">
                    <div className="profile-panel-heading">
                        <span className="profile-panel-icon">
                            <Icon name="user" size={22} />
                        </span>
                        <div>
                            <h3>Thông tin cá nhân</h3>
                            <p>Để đồng đội dễ nhận ra bạn.</p>
                        </div>
                        <button className="profile-text-button" onClick={edit}>
                            Chỉnh sửa
                        </button>
                    </div>
                    <dl className="profile-info">
                        <div>
                            <dt>Họ và tên</dt>
                            <dd>{user.name}</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd>
                                {user.email}
                                <small
                                    className={
                                        user.email_verified_at ? "verified" : ""
                                    }
                                >
                                    {user.email_verified_at
                                        ? "Đã xác thực"
                                        : "Chưa xác thực"}
                                </small>
                            </dd>
                        </div>
                        <div>
                            <dt>Điện thoại</dt>
                            <dd>
                                {user.phone || "Chưa cập nhật"}
                                {user.phone && (
                                    <small
                                        className={
                                            user.phone_verified_at
                                                ? "verified"
                                                : ""
                                        }
                                    >
                                        {user.phone_verified_at
                                            ? "Đã xác thực"
                                            : "Chưa xác thực"}
                                    </small>
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt>Trình độ cầu lông</dt>
                            <dd>{user.skill_level}</dd>
                        </div>
                    </dl>
                    <div className="profile-privacy">
                        <Icon name="people" size={22} />
                        <div>
                            <h4>Kết nối với người chơi</h4>
                            <p>
                                {user.public_profile
                                    ? "Người chơi khác có thể tìm và nhắn tin cho bạn."
                                    : "Bật hồ sơ công khai để tìm đồng đội cùng trình độ."}
                            </p>
                        </div>
                        <button
                            className={
                                "profile-toggle " +
                                (user.public_profile ? "on" : "")
                            }
                            role="switch"
                            aria-checked={user.public_profile}
                            aria-label="Công khai hồ sơ trong cộng đồng"
                            disabled={loading}
                            onClick={() =>
                                run(() =>
                                    changed(() =>
                                        api("me", "PATCH", {
                                            name: user.name,
                                            email: user.email,
                                            phone: user.phone,
                                            public_profile:
                                                !user.public_profile,
                                        }),
                                    ),
                                )
                            }
                        >
                            <span />
                        </button>
                    </div>
                </section>
                <section className="profile-panel">
                    <div className="profile-panel-heading">
                        <span className="profile-panel-icon">
                            <Icon name="shield" size={22} />
                        </span>
                        <div>
                            <h3>Đăng nhập & bảo mật</h3>
                            <p>Giữ tài khoản trong tầm kiểm soát.</p>
                        </div>
                    </div>
                    <div className="profile-security-row">
                        <span>
                            <Icon name="lock" size={20} />
                        </span>
                        <div>
                            <h4>Mật khẩu</h4>
                            <p>Đổi mật khẩu định kỳ để bảo vệ tài khoản.</p>
                        </div>
                        <button
                            className="profile-text-button"
                            onClick={password}
                        >
                            Thay đổi
                        </button>
                    </div>
                    <div className="profile-security-row">
                        <span>
                            <Icon name="mail" size={20} />
                        </span>
                        <div>
                            <h4>Xác thực email</h4>
                            <p>
                                {user.email_verified_at
                                    ? "Email của bạn đã được xác thực."
                                    : "Dùng để nhận thông báo và khôi phục tài khoản."}
                            </p>
                        </div>
                        {!user.email_verified_at && data!.email_enabled && (
                            <button
                                className="profile-text-button"
                                onClick={verifyEmail}
                            >
                                {data!.demo?.email
                                    ? "Thử OTP · Demo"
                                    : "Xác thực"}
                            </button>
                        )}
                        {user.email_verified_at && (
                            <span className="profile-check">✓</span>
                        )}
                    </div>
                    <div className="profile-security-row">
                        <span>
                            <Icon name="phone" size={20} />
                        </span>
                        <div>
                            <h4>Xác thực điện thoại</h4>
                            <p>
                                {user.phone_verified_at
                                    ? "Số điện thoại đã được xác thực."
                                    : "Thuận tiện liên hệ khi lịch sân thay đổi."}
                            </p>
                        </div>
                        {!user.phone_verified_at && data!.sms_enabled && (
                            <button
                                className="profile-text-button"
                                onClick={() => setPhoneOpen(true)}
                            >
                                {data!.demo?.sms
                                    ? "Thử OTP · Demo"
                                    : "Xác thực"}
                            </button>
                        )}
                        {user.phone_verified_at && (
                            <span className="profile-check">✓</span>
                        )}
                    </div>
                    <div className="profile-google">
                        <div>
                            <h4>Google</h4>
                            <p>
                                {user.google_connected
                                    ? "Đã liên kết. Bạn có thể đăng nhập bằng Google."
                                    : "Đăng nhập nhanh bằng tài khoản Google của bạn."}
                            </p>
                        </div>
                        {user.google_connected ? (
                            <span className="badge badge-green">
                                Đã liên kết
                            </span>
                        ) : data!.google_enabled ? (
                            <GoogleButton
                                linked
                                after={() => changed(async () => {})}
                            />
                        ) : (
                            <span className="profile-pending">
                                Chưa khả dụng
                            </span>
                        )}
                    </div>
                </section>
            </div>
            <aside className="profile-rally">
                <span>
                    <Icon name="racket" size={30} />
                </span>
                <div>
                    <h3>Sẵn sàng cho buổi chơi tiếp theo?</h3>
                    <p>Chọn sân, rủ đồng đội và cùng nhau lên sân.</p>
                </div>
                <a href="#booking" className="btn">
                    Tìm sân ngay <Icon name="arrow" size={17} />
                </a>
            </aside>
            {avatarOpen && (
                <Modal
                    title="Ảnh đại diện của bạn"
                    close={() => setAvatarOpen(false)}
                >
                    <p className="mb-4 text-sm text-slate-500">
                        Chọn ảnh rõ mặt để đồng đội dễ nhận ra bạn.
                    </p>
                    <MediaPicker max={1} value={avatar} onChange={setAvatar} />
                    <div className="mt-5 flex flex-wrap gap-3">
                        <button
                            className="btn"
                            disabled={!avatar.length || loading}
                            onClick={() =>
                                run(async () => {
                                    await changed(() =>
                                        api("me", "PATCH", {
                                            name: user.name,
                                            email: user.email,
                                            phone: user.phone,
                                            avatar_id: avatar[0],
                                        }),
                                    );
                                    setAvatarOpen(false);
                                    toast("Đã cập nhật ảnh đại diện.");
                                })
                            }
                        >
                            Lưu ảnh đại diện
                        </button>
                        {user.avatar_path && (
                            <button
                                className="btn-outline"
                                disabled={loading}
                                onClick={() =>
                                    run(async () => {
                                        await changed(() =>
                                            api("me", "PATCH", {
                                                name: user.name,
                                                email: user.email,
                                                phone: user.phone,
                                                avatar_id: null,
                                            }),
                                        );
                                        setAvatarOpen(false);
                                    })
                                }
                            >
                                Xóa ảnh
                            </button>
                        )}
                    </div>
                </Modal>
            )}
            {phoneOpen && (
                <Modal
                    title="Xác thực điện thoại"
                    className="form-dialog form-dialog-compact"
                    close={() => {
                        setPhoneOpen(false);
                        setCode("");
                    }}
                >
                    <form
                        className="hub-form space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            run(async () => {
                                if (!sent) {
                                    const r = await api(
                                        "me/phone/send",
                                        "POST",
                                        { phone },
                                    );
                                    setSent(true);
                                    setDemoCode(r.demo_code ?? "");
                                    toast(r.message);
                                } else {
                                    await changed(async () => {
                                        const r = await api(
                                            "me/phone/verify",
                                            "POST",
                                            { code },
                                        );
                                        toast(r.message);
                                    });
                                    setPhoneOpen(false);
                                    setSent(false);
                                    setCode("");
                                }
                            });
                        }}
                    >
                        <label className="block text-sm font-bold">
                            Số điện thoại
                            <input
                                className="input mt-2 w-full"
                                type="tel"
                                value={phone}
                                required
                                disabled={sent}
                                onChange={(e) => setPhone(e.target.value)}
                            />
                        </label>
                        {demoCode && (
                            <p className="rounded-xl bg-amber-50 p-3 text-sm">
                                Mã demo: <b>{demoCode}</b> · Không gửi SMS thật.
                            </p>
                        )}
                        {sent && (
                            <label className="block text-sm font-bold">
                                Mã 6 chữ số
                                <input
                                    className="input mt-2 w-full"
                                    inputMode="numeric"
                                    pattern="[0-9]{6}"
                                    maxLength={6}
                                    required
                                    value={code}
                                    onChange={(e) => setCode(e.target.value)}
                                />
                            </label>
                        )}
                        <button className="btn" disabled={loading}>
                            {sent
                                ? "Xác nhận mã"
                                : data!.demo?.sms
                                  ? "Thử mã điện thoại · Demo"
                                  : "Gửi mã xác thực"}
                        </button>
                        {sent && (
                            <button
                                className="btn-outline ml-3"
                                type="button"
                                onClick={() => {
                                    setSent(false);
                                    setCode("");
                                    setDemoCode("");
                                }}
                            >
                                Đổi số điện thoại
                            </button>
                        )}
                    </form>
                </Modal>
            )}
        </div>
    );
}
