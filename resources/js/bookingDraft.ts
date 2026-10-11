import type { Row } from "./data";
const draftKey = (user: number) => "hub:booking:" + user;
export function readBookingDraft(user: number): Row {
    try {
        const saved = JSON.parse(
            sessionStorage.getItem(draftKey(user)) ?? "null",
        );
        return saved?.version === 1 && Date.now() - saved.saved_at < 86400000
            ? saved
            : {};
    } catch {
        return {};
    }
}
export function saveBookingDraft(user: number, draft: Row) {
    try {
        sessionStorage.setItem(
            draftKey(user),
            JSON.stringify({ ...draft, version: 1, saved_at: Date.now() }),
        );
    } catch {}
}
export function clearBookingDraft(user: number) {
    try {
        sessionStorage.removeItem(draftKey(user));
    } catch {}
}
