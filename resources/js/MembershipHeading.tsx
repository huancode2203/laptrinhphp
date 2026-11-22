import type { ReactNode } from "react";
import Icon, { type IconName } from "./Icon";
import { ShuttleMark } from "./MembershipBadge";
import { MembershipBackdrop } from "./MembershipCover";
import { useHub } from "./hub";

export default function MembershipHeading({
    eyebrow,
    title,
    description,
    icon,
    action,
}: {
    eyebrow: string;
    title: ReactNode;
    description: string;
    icon?: IconName;
    action?: ReactNode;
}) {
    const { data } = useHub();
    const badge = data?.user?.membership_badge;
    const paid = badge?.kind === "paid";
    return (
        <div
            className={
                "page-heading member-page-heading tone-" +
                (badge?.tone ?? "emerald") +
                (paid ? " is-premium" : badge ? " is-earned" : " is-basic")
            }
        >
            <MembershipBackdrop badge={badge} />
            <div className="member-heading-copy">
                <p className="eyebrow">{eyebrow}</p>
                <h1 className="font-display">{title}</h1>
                <p>{description}</p>
            </div>
            <div className="member-heading-aside">
                {badge && (
                    <span className="member-heading-tier">
                        <ShuttleMark tone={badge.tone} sparkle={paid} />
                        <span>
                            <small>{paid ? "HỘI VIÊN" : "HẠNG TÍCH LŨY"}</small>
                            <b>{badge.label}</b>
                        </span>
                    </span>
                )}
                {action ??
                    (icon && (
                        <span className="member-heading-symbol">
                            <Icon name={icon} size={34} />
                        </span>
                    ))}
            </div>
        </div>
    );
}
