import type { InputHTMLAttributes } from "react";
import Icon from "./Icon";
import { formatDateInput } from "./dates";

export function DateInput({
    className = "",
    style,
    type = "date",
    value,
    disabled,
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    if (!["date", "datetime-local", "month"].includes(type))
        return (
            <input
                {...props}
                type={type}
                className={className}
                style={style}
                value={value}
                disabled={disabled}
            />
        );
    return (
        <span
            className={
                "date-control " + className + (disabled ? " is-disabled" : "")
            }
            style={style}
        >
            <input
                {...props}
                type={type}
                value={value}
                disabled={disabled}
                className="date-control-native"
            />
            <span className="date-control-value" aria-hidden="true">
                {formatDateInput(String(value ?? ""), type)}
            </span>
            <Icon name="calendar" size={17} className="date-control-icon" />
        </span>
    );
}
