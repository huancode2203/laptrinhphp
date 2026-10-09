import Icon from "./Icon";
export function Logo({ dark = false }: { dark?: boolean }) {
    return (
        <div className={"brand " + (dark ? "brand-dark" : "brand-light")}>
            <span className="brand-symbol">
                <Icon name="racket" size={25} />
            </span>
            <span className="brand-wordmark">
                badminton
                <span>
                    hub<span className="brand-dot">.</span>
                </span>
            </span>
        </div>
    );
}
