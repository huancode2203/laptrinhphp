import { useState } from "react";
import { upload, useHub } from "./hub";
export default function MediaPicker({
    value = [],
    onChange,
    files = false,
    max = 4,
}: {
    value?: number[];
    onChange: (v: number[]) => void;
    files?: boolean;
    max?: number;
}) {
    const [busy, setBusy] = useState(false);
    const { toast } = useHub();
    return (
        <div className="mt-2">
            <div className="flex flex-wrap gap-2">
                {value.map((id) => (
                    <div className="relative" key={id}>
                        {files ? (
                            <a
                                className="btn-outline block"
                                href={"/api/v1/media/" + id}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Tệp #{id}
                            </a>
                        ) : (
                            <img
                                alt="Ảnh đã chọn"
                                className="h-20 w-20 rounded-xl object-cover"
                                src={"/api/v1/media/" + id}
                            />
                        )}
                        <button
                            type="button"
                            aria-label="Bỏ tệp"
                            className="absolute -right-1 -top-1 rounded-full bg-white px-2 shadow"
                            onClick={() =>
                                onChange(value.filter((v) => v !== id))
                            }
                        >
                            ×
                        </button>
                    </div>
                ))}
            </div>
            {value.length < max && (
                <label className="mt-3 inline-flex cursor-pointer rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm">
                    <span>
                        {busy
                            ? "Đang tải…"
                            : files
                              ? "+ Đính kèm ảnh / PDF"
                              : "+ Thêm ảnh"}
                    </span>
                    <input
                        className="sr-only"
                        type="file"
                        disabled={busy}
                        accept={
                            files
                                ? "image/jpeg,image/png,image/webp,application/pdf"
                                : "image/jpeg,image/png,image/webp"
                        }
                        onChange={async (e) => {
                            const f = e.target.files?.[0];
                            if (!f) return;
                            setBusy(true);
                            try {
                                const r = await upload(f);
                                onChange([...value, r.data.id]);
                            } catch (error) {
                                toast((error as Error).message);
                            } finally {
                                setBusy(false);
                                e.target.value = "";
                            }
                        }}
                    />
                </label>
            )}
        </div>
    );
}
