import MediaPicker from "./MediaPicker";
import Icon from "./Icon";
import {
    Children,
    useEffect,
    useId,
    useRef,
    useState,
    type ReactNode,
} from "react";
import { ApiError, key, useHub } from "./hub";
import type { Row } from "./data";
export function Modal({
    title,
    children,
    close,
    className = "",
}: {
    title: string;
    children: ReactNode;
    close: () => void;
    className?: string;
}) {
    const dialog = useRef<HTMLDivElement>(null);
    const closeAction = useRef(close);
    useEffect(() => {
        closeAction.current = close;
    }, [close]);
    useEffect(() => {
        const previous =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        dialog.current?.focus();
        const keyboard = (event: KeyboardEvent) => {
            const current = dialog.current;
            if (
                !current ||
                Array.from(document.querySelectorAll(".modal-bg")).at(-1) !==
                    current
            )
                return;
            if (event.key === "Escape") {
                event.preventDefault();
                closeAction.current();
            }
            if (event.key !== "Tab") return;
            const controls = Array.from(
                current.querySelectorAll<HTMLElement>(
                    'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex="0"]',
                ),
            ).filter((control) => control.offsetParent !== null);
            const first = controls[0],
                last = controls.at(-1);
            if (!first) {
                event.preventDefault();
                return;
            }
            if (
                event.shiftKey &&
                (document.activeElement === first ||
                    document.activeElement === current)
            ) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        document.addEventListener("keydown", keyboard);
        return () => {
            document.removeEventListener("keydown", keyboard);
            if (previous?.isConnected) previous.focus();
        };
    }, []);
    return (
        <div
            className="modal-bg"
            ref={dialog}
            tabIndex={-1}
            role="dialog"
            aria-modal="true"
            aria-label={title}
            onClick={close}
        >
            <div
                className={
                    "modal-box max-h-[90vh] max-w-2xl overflow-y-auto p-5 sm:p-6 " +
                    className
                }
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
    maxLength?: number;
    placeholder?: string;
    section?: string;
    wide?: boolean;
};
type FormControl = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
function validationMessage(input: FormControl) {
    const label = (
        input.getAttribute("aria-label") ||
        input.labels?.[0]?.querySelector(".field-label")?.textContent ||
        input.labels?.[0]?.firstChild?.textContent ||
        "Thông tin này"
    )
        .replace(/[*:]/g, "")
        .trim();
    const validity = input.validity;
    let message =
        "Vui lòng kiểm tra lại " + label.toLocaleLowerCase("vi") + ".";
    if (validity.valueMissing)
        message =
            input.name === "password_confirmation"
                ? "Vui lòng nhập lại mật khẩu để xác nhận."
                : "Vui lòng " +
                  (input instanceof HTMLSelectElement ? "chọn " : "nhập ") +
                  label.toLocaleLowerCase("vi") +
                  " trước khi tiếp tục.";
    else if (validity.typeMismatch)
        message =
            input instanceof HTMLInputElement && input.type === "email"
                ? "Email chưa đúng định dạng. Ví dụ: minhanh@example.com."
                : "Thông tin này chưa đúng định dạng. Vui lòng kiểm tra lại.";
    else if (validity.patternMismatch)
        message =
            input instanceof HTMLInputElement && input.name === "code"
                ? "Mã xác nhận cần đủ 6 chữ số."
                : label + " chưa đúng định dạng. Vui lòng kiểm tra lại.";
    else if (validity.tooShort && input instanceof HTMLInputElement)
        message = label + " cần ít nhất " + input.minLength + " ký tự.";
    else if (validity.rangeUnderflow && input instanceof HTMLInputElement)
        message = label + " cần từ " + formatLimit(input.min) + " trở lên.";
    else if (validity.rangeOverflow && input instanceof HTMLInputElement)
        message =
            label + " không được vượt quá " + formatLimit(input.max) + ".";
    else if (validity.stepMismatch && input instanceof HTMLInputElement)
        message =
            input.step === "1" || !input.step
                ? label + " phải là số nguyên."
                : label + " cần nhập theo bước " + input.step + ".";

    return message;
}
export function formErrors(form: HTMLFormElement): Record<string, string[]> {
    const errors: Record<string, string[]> = {};
    for (const input of Array.from(form.elements)) {
        if (
            !(
                input instanceof HTMLInputElement ||
                input instanceof HTMLSelectElement ||
                input instanceof HTMLTextAreaElement
            ) ||
            !input.name ||
            input.disabled
        )
            continue;
        input.setCustomValidity("");
        if (!input.validity.valid)
            errors[input.name] = [validationMessage(input)];
        else if (
            input instanceof HTMLInputElement &&
            input.value &&
            input.minLength > 0 &&
            input.value.length < input.minLength
        )
            errors[input.name] = [
                (input.getAttribute("aria-label") || "Thông tin này") +
                    " cần ít nhất " +
                    input.minLength +
                    " ký tự.",
            ];
    }
    const first = form.elements.namedItem(Object.keys(errors)[0] ?? "");
    if (first instanceof HTMLElement) first.focus();
    return errors;
}
export function FriendlyForms() {
    useEffect(() => {
        const invalid = (event: Event) => {
            const input = event.target;
            if (
                input instanceof HTMLInputElement ||
                input instanceof HTMLSelectElement ||
                input instanceof HTMLTextAreaElement
            )
                input.setCustomValidity(validationMessage(input));
        };
        const clear = (event: Event) => {
            const input = event.target;
            if (
                input instanceof HTMLInputElement ||
                input instanceof HTMLSelectElement ||
                input instanceof HTMLTextAreaElement
            )
                input.setCustomValidity("");
        };
        document.addEventListener("invalid", invalid, true);
        document.addEventListener("input", clear, true);
        document.addEventListener("change", clear, true);
        return () => {
            document.removeEventListener("invalid", invalid, true);
            document.removeEventListener("input", clear, true);
            document.removeEventListener("change", clear, true);
        };
    }, []);
    return null;
}
function formatLimit(value: string) {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}:\d{2}))?$/);
    return match
        ? match[3] +
              "/" +
              match[2] +
              "/" +
              match[1] +
              (match[4] ? " lúc " + match[4] : "")
        : value;
}
export function Editor({
    title,
    fields,
    initial = {},
    save,
    close,
    label = "Lưu thay đổi",
    description,
}: {
    title: string;
    fields: Field[];
    initial?: Row;
    save: (d: Row) => Promise<void>;
    close: () => void;
    label?: string;
    description?: string;
}) {
    const [d, set] = useState<Row>(initial),
        [error, fail] = useState(""),
        [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [transactionKey] = useState(key);
    const formId = useId();
    const sections = [
        ...new Set(fields.map((field) => field.section ?? "Thông tin")),
    ];
    function change(name: string, value: any) {
        set((previous) => ({ ...previous, [name]: value }));
        setErrors((previous) => {
            const next = { ...previous };
            delete next[name];
            return next;
        });
        fail("");
    }
    function renderField(f: Field) {
        const id = formId + "-" + f.name;
        const messages = Object.entries(errors)
            .filter(
                ([name]) => name === f.name || name.startsWith(f.name + "."),
            )
            .flatMap(([, values]) => values);
        const wide =
            f.wide ||
            fields.length === 1 ||
            ["textarea", "images"].includes(f.type ?? "") ||
            [
                "title",
                "name",
                "address",
                "description",
                "notes",
                "reason",
                "reply",
            ].includes(f.name);
        const shared = {
            id,
            name: f.name,
            autoComplete: f.type === "password" ? "new-password" : "off",
            "aria-label": f.label,
            "aria-invalid": !!messages.length,
            "aria-describedby": messages.length
                ? id + "-error"
                : f.help
                  ? id + "-hint"
                  : undefined,
        };
        return (
            <div
                className={
                    "form-field " +
                    (wide ? "form-field-wide" : "") +
                    (f.type === "checkbox" ? "form-switch-field" : "")
                }
                key={f.name}
            >
                <label className="field-label" htmlFor={id}>
                    {f.label}
                    {f.required && <span className="required-mark">*</span>}
                    {!f.required && f.type === "images" && (
                        <span className="optional-mark">Tuỳ chọn</span>
                    )}
                </label>
                {f.type === "images" ? (
                    <MediaPicker
                        value={d[f.name] ?? []}
                        onChange={(value) => change(f.name, value)}
                    />
                ) : f.type === "checkbox" ? (
                    <label className="form-switch">
                        <input
                            {...shared}
                            type="checkbox"
                            checked={!!d[f.name]}
                            onChange={(event) =>
                                change(f.name, event.target.checked)
                            }
                        />
                        <span aria-hidden="true" />
                        <b>{d[f.name] ? "Đang bật" : "Đang tắt"}</b>
                    </label>
                ) : f.options ? (
                    <select
                        {...shared}
                        className="input"
                        required={f.required}
                        value={d[f.name] ?? ""}
                        onChange={(event) => change(f.name, event.target.value)}
                    >
                        <option value="">
                            Chọn {f.label.toLocaleLowerCase("vi")}…
                        </option>
                        {f.options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                ) : f.type === "textarea" ? (
                    <>
                        <textarea
                            {...shared}
                            className="input"
                            required={f.required}
                            maxLength={f.maxLength ?? 5000}
                            placeholder={
                                f.placeholder ??
                                "Nhập " + f.label.toLocaleLowerCase("vi") + "…"
                            }
                            value={d[f.name] ?? ""}
                            onChange={(event) =>
                                change(f.name, event.target.value)
                            }
                        />
                        <small className="field-counter">
                            {String(d[f.name] ?? "").length}/
                            {f.maxLength ?? 5000}
                        </small>
                    </>
                ) : (
                    <input
                        {...shared}
                        className="input"
                        type={f.type ?? "text"}
                        required={f.required}
                        min={f.min}
                        max={f.max}
                        step={f.step ?? (f.type === "number" ? 1 : undefined)}
                        maxLength={
                            f.maxLength ??
                            (f.type === "text" || !f.type ? 255 : undefined)
                        }
                        placeholder={
                            f.placeholder ??
                            (f.type === "number"
                                ? "0"
                                : f.type === "date" ||
                                    f.type === "datetime-local"
                                  ? undefined
                                  : "Nhập " +
                                    f.label.toLocaleLowerCase("vi") +
                                    "…")
                        }
                        value={d[f.name] ?? ""}
                        onChange={(event) => change(f.name, event.target.value)}
                        onBlur={() => {
                            if (
                                f.type === "number" &&
                                d[f.name] !== "" &&
                                d[f.name] != null &&
                                Number.isFinite(Number(d[f.name]))
                            )
                                change(f.name, String(Number(d[f.name])));
                        }}
                    />
                )}
                {messages.length > 0 && (
                    <small id={id + "-error"} className="field-error">
                        {[...new Set(messages)].join(" ")}
                    </small>
                )}
                {f.help && <small id={id + "-hint"}>{f.help}</small>}
            </div>
        );
    }
    return (
        <Modal
            title={title}
            close={() => {
                if (!busy) close();
            }}
            className={
                "form-dialog " +
                (fields.length <= 2 ? "form-dialog-compact" : "")
            }
        >
            <p className="form-intro">
                {description ??
                    "Điền thông tin bên dưới. Các trường có dấu * là bắt buộc."}
            </p>
            <form
                className="hub-form editor-form"
                noValidate
                onSubmit={async (event) => {
                    event.preventDefault();
                    if (busy) return;
                    const displayed: Row = {
                        ...d,
                        ...Object.fromEntries(
                            new FormData(event.currentTarget),
                        ),
                    };
                    for (const field of fields.filter(
                        (field) => field.type === "checkbox",
                    )) {
                        const input = event.currentTarget.elements.namedItem(
                            field.name,
                        );
                        if (input instanceof HTMLInputElement)
                            displayed[field.name] = input.checked;
                    }
                    set(displayed);
                    const invalid = formErrors(event.currentTarget);
                    if (Object.keys(invalid).length) {
                        setErrors(invalid);
                        fail(
                            "Kiểm tra các ô được đánh dấu bên dưới rồi thử lại.",
                        );
                        return;
                    }
                    setBusy(true);
                    fail("");
                    setErrors({});
                    try {
                        const normalized: Row = {
                            ...displayed,
                            request_key: transactionKey,
                        };
                        for (const f of fields.filter(
                            (field) => field.type === "number",
                        )) {
                            if (
                                displayed[f.name] === "" ||
                                displayed[f.name] == null
                            ) {
                                normalized[f.name] = null;
                                continue;
                            }
                            const value = Number(displayed[f.name]);
                            if (
                                !Number.isFinite(value) ||
                                (!(f.step !== undefined && f.step < 1) &&
                                    !Number.isSafeInteger(value))
                            ) {
                                setErrors({
                                    [f.name]: [
                                        f.label + " phải là số nguyên hợp lệ.",
                                    ],
                                });
                                throw new Error(
                                    "Kiểm tra ô được đánh dấu rồi thử lại.",
                                );
                            }
                            normalized[f.name] = value;
                        }
                        await save(normalized);
                        close();
                    } catch (failure) {
                        const messages =
                            failure instanceof ApiError ? failure.fields : {};
                        if (Object.keys(messages).length) setErrors(messages);
                        fail(
                            Object.keys(messages).length
                                ? "Kiểm tra các ô được đánh dấu bên dưới rồi thử lại."
                                : (failure as Error).message,
                        );
                    } finally {
                        setBusy(false);
                    }
                }}
            >
                {sections.map((section, index) => (
                    <fieldset className="form-section" key={section}>
                        <legend className="form-section-title">
                            <span className="form-section-number">
                                {String(index + 1).padStart(2, "0")}
                            </span>
                            {section}
                        </legend>
                        <div className="form-grid">
                            {fields
                                .filter(
                                    (field) =>
                                        (field.section ?? "Thông tin") ===
                                        section,
                                )
                                .map(renderField)}
                        </div>
                    </fieldset>
                ))}
                {error && (
                    <div role="alert" className="form-alert">
                        <Icon name="help" size={18} />
                        <span>{error}</span>
                    </div>
                )}
                <div className="form-actions">
                    <span className="form-action-hint">
                        <Icon name="shield" size={15} />
                        Kiểm tra thông tin trước khi lưu
                    </span>
                    <div>
                        <button
                            type="button"
                            className="btn-outline"
                            disabled={busy}
                            onClick={close}
                        >
                            Hủy
                        </button>
                        <button className="btn" disabled={busy}>
                            {busy ? "Đang lưu…" : label}
                            <Icon name="check" size={17} />
                        </button>
                    </div>
                </div>
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
    pageSize = 10,
}: {
    headers: string[];
    children: ReactNode;
    pageSize?: number | null;
}) {
    const rows = Children.toArray(children);
    const [page, setPage] = useState(1);
    const size = pageSize ?? Math.max(1, rows.length);
    const pages = Math.max(1, Math.ceil(rows.length / size));
    const current = Math.min(page, pages);
    useEffect(() => setPage(1), [rows.length]);
    return (
        <div>
            <div className="data-table-wrap overflow-x-auto border bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="bg-[#edf3ed] text-[#6d8474]">
                        <tr>
                            {headers.map((h) => (
                                <th
                                    className="whitespace-nowrap px-4 py-3"
                                    key={h}
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#edf3ed]">
                        {rows.slice((current - 1) * size, current * size)}
                    </tbody>
                </table>
            </div>
            {pages > 1 && (
                <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span>
                        {rows.length} dòng · Trang {current}/{pages}
                    </span>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            className="btn-outline"
                            disabled={current <= 1}
                            onClick={() => setPage(current - 1)}
                        >
                            Trang trước
                        </button>
                        <button
                            type="button"
                            className="btn-outline"
                            disabled={current >= pages}
                            onClick={() => setPage(current + 1)}
                        >
                            Trang sau
                        </button>
                    </div>
                </div>
            )}
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
