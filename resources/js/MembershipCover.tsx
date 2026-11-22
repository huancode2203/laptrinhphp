import type { CSSProperties } from "react";
import Icon from "./Icon";
import { ShuttleMark } from "./MembershipBadge";
import type { Row } from "./data";

const stars = Array.from({ length: 18 }, (_, index) => ({
    left: 5 + ((index * 43) % 91),
    top: 10 + ((index * 29) % 76),
    size: index % 4 === 0 ? 7 : index % 3 === 0 ? 4 : 2,
    delay: -(index * 0.73),
}));

export function MembershipBackdrop({ badge }: { badge?: Row | null }) {
    const paid = badge?.kind === "paid";
    return (
        <div className="member-cover-backdrop" aria-hidden="true">
            <div className="court-lines" />
            {paid && (
                <>
                    {badge.tone === "vip" && <div className="member-prism" />}
                    <div className="member-aurora member-aurora-one" />
                    <div className="member-aurora member-aurora-two" />
                    <div className="member-cover-grid" />
                    <div className="member-cover-sweep" />
                    <div className="member-cover-stars">
                        {stars.map((star, index) => (
                            <i
                                key={index}
                                style={
                                    {
                                        left: star.left + "%",
                                        top: star.top + "%",
                                        "--star-size": star.size + "px",
                                        "--star-delay": star.delay + "s",
                                    } as CSSProperties
                                }
                            />
                        ))}
                    </div>
                    <span className="member-cover-ghost">{badge.label}</span>
                </>
            )}
        </div>
    );
}

export default function MembershipCover({ badge }: { badge?: Row | null }) {
    const paid = badge?.kind === "paid";
    const label = badge?.label ?? "Hẹn nhau trên sân";
    return (
        <div className="profile-cover">
            <MembershipBackdrop badge={badge} />
            <div className="member-cover-copy">
                <span className="member-cover-kicker">
                    <i />
                    {paid
                        ? "BADMINTON HUB · HỘI VIÊN"
                        : "BADMINTON HUB · NGƯỜI CHƠI"}
                </span>
                <h3>{label}</h3>
                <p>
                    {paid
                        ? "Dấu ấn riêng. Mỗi lần lên sân."
                        : "Chơi hết mình. Kết nối đồng đội."}
                </p>
            </div>
            {paid ? (
                <div className="member-cover-emblem" aria-hidden="true">
                    <div className="member-orbit member-orbit-outer" />
                    <div className="member-orbit member-orbit-inner" />
                    <span className="member-emblem-label">HUB MEMBER</span>
                    <ShuttleMark tone={badge.tone} large sparkle />
                    <span className="member-emblem-caption">
                        PLAY WITH YOUR OWN STYLE
                    </span>
                </div>
            ) : (
                <Icon name="racket" size={58} />
            )}
        </div>
    );
}
