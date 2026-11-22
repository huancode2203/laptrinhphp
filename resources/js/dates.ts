const timeZone = "Asia/Ho_Chi_Minh";

function businessDate(value: string | number | Date): Date {
    const normalized =
        typeof value === "string" &&
        /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/.test(value)
            ? value.replace(" ", "T") + "+07:00"
            : value;
    return new Date(normalized);
}

export function formatDate(
    value: string | number | Date | null | undefined,
): string {
    if (value == null || value === "") return "—";
    if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split("-");
        return `${day}-${month}-${year}`;
    }
    const date = businessDate(value);
    if (Number.isNaN(date.getTime())) return "—";
    const parts = new Intl.DateTimeFormat("en-GB", {
        timeZone,
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).formatToParts(date);
    const part = (name: string) =>
        parts.find((item) => item.type === name)?.value ?? "";
    return `${part("day")}-${part("month")}-${part("year")}`;
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return "—";
    const date = businessDate(value);
    if (Number.isNaN(date.getTime())) return "—";
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return formatDate(value);
    const time = new Intl.DateTimeFormat("en-GB", {
        timeZone,
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hourCycle: "h23",
    }).format(date);
    return `${formatDate(value)} ${time}`;
}

export function formatDateInput(value: string, type = "date"): string {
    if (!value)
        return type === "month"
            ? "mm-yyyy"
            : type === "datetime-local"
              ? "dd-mm-yyyy hh:mm"
              : "dd-mm-yyyy";
    if (type === "month") {
        const [year, month] = value.split("-");
        return `${month}-${year}`;
    }
    const [date, time] = value.split("T");
    return (
        formatDate(date) +
        (type === "datetime-local" && time ? " " + time.slice(0, 5) : "")
    );
}

export function formatDateText(text: string): string {
    return text.replace(
        /\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/g,
        (_, day: string, month: string, year: string) =>
            `${day.padStart(2, "0")}-${month.padStart(2, "0")}-${year}`,
    );
}
