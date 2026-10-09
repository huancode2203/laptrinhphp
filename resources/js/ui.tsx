import MediaPicker from "./MediaPicker";
import Icon from "./Icon";
import { useState, type ReactNode } from "react";
import { key, useHub } from "./hub";
import type { Row } from "./data";
export function Modal({
    title,
    children,
    close,
}: {
    title: string;
    children: ReactNode;
    close: () => void;
}) {
    return (
        <div
            className="modal-bg"
            role="dialog"
            aria-modal="true"
            aria-label={title}
            onClick={close}
        >
            <div
                className="modal-box max-h-[90vh] max-w-2xl overflow-y-auto p-5 sm:p-6"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="modal-heading mb-5 flex items-center justify-between gap-3">
                    <h2 className="font-display text-2xl font-bold">{title}</h2>
                    <button
                        className="modal-close"
                        aria-label="Đóng"
                        onClick={close}
                    >
                        <Icon name="close" size={18} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
export type Field = {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
    options?: { value: string | number; label: string }[];
    min?: number | string;
    max?: number | string;
    step?: number;
    help?: string;
};
export function Editor({
    title,
    fields,
    initial = {},
    save,
    close,
    label = "Lưu",
}: {
    title: string;
    fields: Field[];
    initial?: Row;
    save: (d: Row) => Promise<void>;
    close: () => void;
    label?: string;
}) {
    const [d, set] = useState<Row>(initial),
        [error, fail] = useState(""),
        [busy, setBusy] = useState(false);
    const [transactionKey] = useState(key);
    return (
        <Modal title={title} close={close}>
            <form
                className="space-y-4"
                onSubmit={async (e) => {
                    e.preventDefault();
                    setBusy(true);
                    try {
                        await save({ ...d, request_key: transactionKey });
                        close();
                    } catch (err) {
                        fail((err as Error).message);
                    } finally {
                        setBusy(false);
                    }
                }}
            >
                {fields.map((f) => (
                    <label className="block text-sm font-semibold" key={f.name}>
                        {f.label}
                        {f.type === "images" ? (
                            <MediaPicker
                                value={d[f.name] ?? []}
                                onChange={(v) => set({ ...d, [f.name]: v })}
                            />
                        ) : f.type === "checkbox" ? (
                            <input
                                type="checkbox"
                                className="ml-3"
                                checked={!!d[f.name]}
                                onChange={(e) =>
                                    set({ ...d, [f.name]: e.target.checked })
                                }
                            />
                        ) : f.options ? (
                            <select
                                className="input mt-1 w-full"
                                required={f.required}
                                value={d[f.name] ?? ""}
                                onChange={(e) =>
                                    set({ ...d, [f.name]: e.target.value })
                                }
                            >
                                <option value="">Chọn…</option>
                                {f.options.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </select>
                        ) : f.type === "textarea" ? (
                            <textarea
                                className="input mt-1 min-h-28 w-full"
                                required={f.required}
                                maxLength={5000}
                                value={d[f.name] ?? ""}
                                onChange={(e) =>
                                    set({ ...d, [f.name]: e.target.value })
                                }
                            />
                        ) : (
                            <input
                                className="input mt-1 w-full"
                                type={f.type ?? "text"}
                                required={f.required}
                                min={f.min}
                                max={f.max}
                                step={f.step}
                                value={d[f.name] ?? ""}
                                onChange={(e) =>
                                    set({
                                        ...d,
                                        [f.name]:
                                            f.type === "number"
                                                ? Number(e.target.value)
                                                : e.target.value,
                                    })
                                }
                            />
                        )}{" "}
                        {f.help && (
                            <small className="block text-[#6d8474]">
                                {f.help}
                            </small>
                        )}
                    </label>
                ))}
                {error && (
                    <p className="rounded-xl bg-red-50 p-3 text-sm text-red-700">
                        {error}
                    </p>
                )}
                <button className="btn w-full" disabled={busy}>
                    {busy ? "Đang lưu…" : label}
                </button>
            </form>
        </Modal>
    );
}
export function Empty({ text = "Chưa có dữ liệu." }: { text?: string }) {
    return (
        <div className="empty-state">
            <span className="empty-icon">
                <Icon name="court" size={26} />
            </span>
            {text}
        </div>
    );
}
export function Card({
    children,
    className = "",
}: {
    children: ReactNode;
    className?: string;
}) {
    return <div className={"app-card p-5 " + className}>{children}</div>;
}
export function Table({
    headers,
    children,
}: {
    headers: string[];
    children: ReactNode;
}) {
    return (
        <div className="data-table-wrap overflow-x-auto border bg-white">
            <table className="w-full text-left text-sm">
                <thead className="bg-[#edf3ed] text-[#6d8474]">
                    <tr>
                        {headers.map((h) => (
                            <th className="whitespace-nowrap px-4 py-3" key={h}>
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-[#edf3ed]">{children}</tbody>
            </table>
        </div>
    );
}
export const cell = "px-4 py-3";
export function Confirm({
    title,
    children,
    action,
    close,
}: {
    title: string;
    children: ReactNode;
    action: (requestKey: string) => Promise<void>;
    close: () => void;
}) {
    const { run, loading } = useHub();
    const [transactionKey] = useState(key);
    return (
        <Modal title={title} close={close}>
            <div className="mb-5">{children}</div>
            <button
                disabled={loading}
                className="btn"
                onClick={() =>
                    run(async () => {
                        await action(transactionKey);
                        close();
                    })
                }
            >
                Xác nhận
            </button>
        </Modal>
    );
}
