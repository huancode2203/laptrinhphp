import { useId } from "react";
import type { Row } from "./data";

export function membershipTone(plan?: Row | null): string {
    if (plan?.badge_tone || plan?.tone) return plan.badge_tone ?? plan.tone;
    const name = String(plan?.name ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replaceAll("đ", "d");
    if (name.includes("vip")) return "vip";
    if (/gold|vang/.test(name)) return "gold";
    if (/silver|bac/.test(name)) return "silver";
    if (/bronze|dong/.test(name)) return "bronze";
    return "emerald";
}

export function ShuttleMark({
    tone = "emerald",
    large = false,
    sparkle = false,
}: {
    tone?: string;
    large?: boolean;
    sparkle?: boolean;
}) {
    const id = useId();
    return (
        <svg
            className={
                "shuttle-mark tone-" +
                tone +
                (large ? " is-large" : "") +
                (sparkle ? " is-sparkly" : "")
            }
            viewBox="0 0 64 64"
            aria-hidden="true"
            focusable="false"
        >
            <defs>
                <linearGradient id={id} x1="0" y1="0" x2="1" y2="1">
                    {tone === "vip" && sparkle ? (
                        <>
                            <stop stopColor="#fff8da" />
                            <stop offset=".28" stopColor="#d9edf5" />
                            <stop offset=".43" stopColor="#87d8df" />
                            <stop offset=".5" stopColor="#cbb5e4" />
                            <stop offset=".57" stopColor="#eed3b1" />
                            <stop offset=".75" stopColor="#ffffff" />
                            <stop offset="1" stopColor="#c0cbd7" />
                        </>
                    ) : (
                        <>
                            <stop stopColor="var(--medal-light)" />
                            <stop offset=".46" stopColor="var(--medal-main)" />
                            <stop offset="1" stopColor="var(--medal-dark)" />
                        </>
                    )}
                </linearGradient>
                <linearGradient id={id + "-shine"}>
                    <stop stopColor="white" stopOpacity="0" />
                    <stop offset=".45" stopColor="white" stopOpacity=".15" />
                    <stop offset=".5" stopColor="white" stopOpacity=".95" />
                    <stop offset=".65" stopColor="white" stopOpacity="0" />
                </linearGradient>
                <clipPath id={id + "-clip"}>
                    <path d="M25 43 8 13l6-5 15 33ZM28 41 19 5l8-2 5 37ZM32 40 31 2l8 1-3 38ZM36 41 44 5l8 4-12 34ZM40 43 55 13l5 6-17 27Zm-16-1 18 2-1 7c-1 9-18 9-18-1Z" />
                </clipPath>
            </defs>
            <g
                transform="rotate(24 32 32)"
                fill={"url(#" + id + ")"}
                stroke="var(--medal-dark)"
                strokeWidth="1.3"
                strokeLinejoin="round"
            >
                <path d="M25 43 8 13l6-5 15 33ZM28 41 19 5l8-2 5 37ZM32 40 31 2l8 1-3 38ZM36 41 44 5l8 4-12 34ZM40 43 55 13l5 6-17 27Z" />
                <path d="m24 42 18 2-1 7c-1 9-18 9-18-1Z" strokeWidth="2" />
                <path d="m24 42 18 2M25 47l16 2" fill="none" />
                {sparkle && (
                    <g clipPath={"url(#" + id + "-clip)"} stroke="none">
                        <rect
                            className="shuttle-gloss"
                            x="-64"
                            y="0"
                            width="64"
                            height="64"
                            fill={"url(#" + id + "-shine)"}
                        />
                    </g>
                )}
            </g>
            {sparkle && (
                <g
                    className="shuttle-sparkles"
                    fill="white"
                    stroke="var(--medal-main)"
                    strokeWidth=".6"
                >
                    <path d="m12 4 1.8 5.2L19 11l-5.2 1.8L12 18l-1.8-5.2L5 11l5.2-1.8Z" />
                    <path d="m52 40 1.2 3.8L57 45l-3.8 1.2L52 50l-1.2-3.8L47 45l3.8-1.2Z" />
                </g>
            )}
        </svg>
    );
}

export default function UserName({
    user,
    fallback = "Người chơi",
    className = "",
}: {
    user?: Row | null;
    fallback?: string;
    className?: string;
}) {
    const badge = user?.membership_badge;
    return (
        <span className={"member-name " + className}>
            <span>{user?.name ?? fallback}</span>
            {badge && (
                <span
                    className={
                        "membership-badge tone-" +
                        badge.tone +
                        (badge.kind === "paid" ? " is-premium" : " is-earned")
                    }
                    role="img"
                    aria-label={"Hội viên " + badge.label}
                    title={
                        badge.label +
                        (badge.kind === "loyalty"
                            ? " · Hạng tích điểm"
                            : " · Hội viên")
                    }
                >
                    <ShuttleMark
                        tone={badge.tone}
                        sparkle={badge.kind === "paid"}
                    />
                </span>
            )}
        </span>
    );
}
