import type { Row } from "./data";

export function formatMoneyInput(value: unknown): string {
    const raw = String(value ?? "");
    if (!/^\d+$/.test(raw)) return raw;
    return raw.replace(/^0+(?=\d)/, "").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

export function parseMoneyInput(value: unknown): number | null {
    const text = String(value ?? "").trim();
    if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+|\d{1,3}(?:,\d{3})+)$/.test(text))
        return null;
    const amount = Number(text.replace(/[.,]/g, ""));
    return Number.isSafeInteger(amount) ? amount : null;
}

export function isMoneyField(
    field: { name: string; type?: string },
    data: Row,
): boolean {
    if (field.type === "money") return true;
    if (field.type !== "number") return false;
    if (field.name === "value") return data.kind === "fixed";
    return /^(amount|price|unit_price|unit_cost|cost_price|hourly_rate|peak_rate|weekend_rate|minimum_amount|max_discount|base_salary|basic_salary|base_amount|allowance|allowances|deduction|opening_cash|counted_cash|cash_amount|paid_amount|received_amount|expected_amount|court_amount|service_amount|salary|bonus|deductions|cost|fee)$/.test(
        field.name,
    );
}
