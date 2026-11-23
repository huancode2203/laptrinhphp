import type { HTMLAttributes, ReactNode } from "react";
import { MembershipBackdrop } from "./MembershipCover";
import { useHub } from "./hub";
import type { Row } from "./data";

export default function MembershipBanner({
    as: Tag = "div",
    badge: suppliedBadge,
    className = "",
    children,
    ...props
}: HTMLAttributes<HTMLElement> & {
    as?: "div" | "section" | "header";
    badge?: Row | null;
    children: ReactNode;
}) {
    const { data } = useHub();
    const badge =
        suppliedBadge === undefined
            ? data?.user?.role === "customer"
                ? data.user.membership_badge
                : null
            : suppliedBadge;
    const kind =
        badge?.kind === "paid" ? "premium" : badge ? "earned" : "basic";
    return (
        <Tag
            {...props}
            className={`member-banner tone-${badge?.tone ?? "emerald"} is-${kind} ${className}`}
            data-membership-tone={badge?.tone ?? "emerald"}
            data-membership-kind={kind}
        >
            <MembershipBackdrop badge={badge} />
            {children}
        </Tag>
    );
}
