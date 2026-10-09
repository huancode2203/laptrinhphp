import { useEffect, useState } from "react";
import { Logo } from "./Brand";
import Icon, { type IconName } from "./Icon";
export default function SiteHeader({
    page,
    go,
    book,
    userName,
    manage,
    logout,
}: {
    page: string;
    go: (page: string) => void;
    book: () => void;
    userName?: string;
    manage?: boolean;
    logout: () => void;
}) {
    const [open, setOpen] = useState(false),
        [profile, setProfile] = useState(false);
    useEffect(() => {
        setOpen(false);
        setProfile(false);
    }, [page]);
    useEffect(() => {
        const close = (e: KeyboardEvent) => {
            if (e.key === "Escape") {
                setOpen(false);
                setProfile(false);
            }
        };
        window.addEventListener("keydown", close);
        return () => window.removeEventListener("keydown", close);
    }, []);
    const links: [string, string, IconName][] = [
        ["home", "Tìm sân", "court"],
        ["community", "Cộng đồng", "people"],
        ["events", "Giải đấu", "trophy"],
        ["coaching", "Học cầu lông", "racket"],
        ["content", "Hỗ trợ", "help"],
    ];
    const navigate = (target: string) => {
        setOpen(false);
        setProfile(false);
        go(target);
    };
    return (
        <header className="site-header">
            <div className="site-header-inner">
                <button aria-label="Trang chủ" onClick={() => navigate("home")}>
                    <Logo dark />
                </button>
                <nav
                    className={"site-nav " + (open ? "is-open" : "")}
                    aria-label="Điều hướng chính"
                >
                    {links.map(([target, label, icon]) => (
                        <button
                            key={target}
                            className={
                                "nav-item " +
                                (page === target ||
                                (target === "home" &&
                                    ["branch", "booking"].includes(page))
                                    ? "is-active"
                                    : "")
                            }
                            aria-current={page === target ? "page" : undefined}
                            onClick={() => navigate(target)}
                        >
                            <Icon name={icon} size={18} />
                            <span>{label}</span>
                        </button>
                    ))}
                    <div className="mobile-account">
                        <button
                            onClick={() =>
                                navigate(userName ? "account" : "auth")
                            }
                        >
                            <Icon name="user" />
                            {userName ?? "Đăng nhập"}
                        </button>
                        {manage && (
                            <button onClick={() => navigate("manage")}>
                                <Icon name="grid" />
                                Quản lý
                            </button>
                        )}
                        {userName && (
                            <button
                                onClick={() => {
                                    setOpen(false);
                                    logout();
                                }}
                            >
                                <Icon name="logout" />
                                Đăng xuất
                            </button>
                        )}
                    </div>
                </nav>
                <div className="header-actions">
                    {userName ? (
                        <div className="profile-menu">
                            <button
                                className="profile-trigger"
                                aria-label="Menu tài khoản"
                                aria-expanded={profile}
                                onClick={() => setProfile(!profile)}
                            >
                                <span className="avatar-mini">
                                    {userName.split(" ").at(-1)?.slice(0, 1)}
                                </span>
                                <span className="profile-name">{userName}</span>
                                <Icon name="chevron" size={15} />
                            </button>
                            {profile && (
                                <div className="profile-dropdown">
                                    <button onClick={() => navigate("account")}>
                                        <Icon name="user" />
                                        Tài khoản của tôi
                                    </button>
                                    {manage && (
                                        <button
                                            onClick={() => navigate("manage")}
                                        >
                                            <Icon name="grid" />
                                            Quản lý hệ thống
                                        </button>
                                    )}
                                    <button
                                        onClick={() => {
                                            setProfile(false);
                                            logout();
                                        }}
                                    >
                                        <Icon name="logout" />
                                        Đăng xuất
                                    </button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <button
                            className="login-link"
                            onClick={() => navigate("auth")}
                        >
                            Đăng nhập
                        </button>
                    )}
                    <button
                        className="header-book"
                        onClick={() => {
                            setOpen(false);
                            book();
                        }}
                    >
                        Đặt sân
                        <Icon name="northeast" size={17} />
                    </button>
                    <button
                        className="menu-trigger"
                        aria-label={open ? "Đóng menu" : "Mở menu"}
                        aria-expanded={open}
                        onClick={() => setOpen(!open)}
                    >
                        <Icon name={open ? "close" : "menu"} size={23} />
                    </button>
                </div>
            </div>
        </header>
    );
}
