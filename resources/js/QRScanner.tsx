import { useEffect, useRef, useState } from "react";
import { BrowserQRCodeReader, type IScannerControls } from "@zxing/browser";

export default function QRScanner({
    found,
}: {
    found: (token: string) => void | Promise<void>;
}) {
    const video = useRef<HTMLVideoElement>(null);
    const controls = useRef<IScannerControls | null>(null);
    const handler = useRef(found);
    handler.current = found;
    const [active, setActive] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const mounted = useRef(true);
    const scanning = useRef(false);

    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
            controls.current?.stop();
        };
    }, []);

    async function accept(token: string) {
        if (scanning.current) return;
        scanning.current = true;
        controls.current?.stop();
        setActive(false);
        setBusy(true);
        setError("");
        try {
            await handler.current(token);
        } catch (failure) {
            if (mounted.current) setError((failure as Error).message);
        } finally {
            scanning.current = false;
            if (mounted.current) setBusy(false);
        }
    }

    useEffect(() => {
        let alive = true;
        const reader = new BrowserQRCodeReader();
        if (active && video.current) {
            reader
                .decodeFromConstraints(
                    { audio: false, video: { facingMode: "environment" } },
                    video.current,
                    (result) => {
                        if (result && alive) void accept(result.getText());
                    },
                )
                .then((scanner) => {
                    if (alive) controls.current = scanner;
                    else scanner.stop();
                })
                .catch(() => {
                    if (alive) {
                        setError(
                            "Không mở được camera. Bạn có thể tải ảnh QR lên hoặc cấp quyền camera trên trình duyệt.",
                        );
                        setActive(false);
                    }
                });
        }
        return () => {
            alive = false;
            controls.current?.stop();
            controls.current = null;
        };
    }, [active]);

    return (
        <div className="space-y-4">
            <p className="text-sm text-slate-600">
                Quét mã QR trong lịch đặt của khách, hoặc tải ảnh mã QR từ thiết
                bị.
            </p>
            <div className="flex flex-wrap gap-3">
                <button
                    type="button"
                    disabled={busy}
                    className="btn-outline"
                    onClick={() => {
                        setError("");
                        if (!active && !navigator.mediaDevices?.getUserMedia) {
                            setError(
                                "Camera cần kết nối HTTPS hoặc localhost. Hãy tải ảnh QR lên để tiếp tục.",
                            );
                            return;
                        }
                        setActive(!active);
                    }}
                >
                    {active ? "Dừng camera" : "Quét bằng camera"}
                </button>
                <label className="btn-outline cursor-pointer">
                    Tải ảnh QR
                    <input
                        aria-label="Tải ảnh mã QR"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="sr-only"
                        disabled={busy}
                        onChange={async (event) => {
                            const file = event.currentTarget.files?.[0];
                            event.currentTarget.value = "";
                            if (!file) return;
                            setActive(false);
                            setError("");
                            if (
                                ![
                                    "image/png",
                                    "image/jpeg",
                                    "image/webp",
                                ].includes(file.type) ||
                                file.size > 10 * 1024 * 1024
                            ) {
                                setError(
                                    "Chọn ảnh PNG, JPG hoặc WebP có dung lượng tối đa 10 MB.",
                                );
                                return;
                            }
                            const url = URL.createObjectURL(file);
                            setBusy(true);
                            try {
                                const result =
                                    await new BrowserQRCodeReader().decodeFromImageUrl(
                                        url,
                                    );
                                if (mounted.current)
                                    await accept(result.getText());
                            } catch {
                                if (mounted.current)
                                    setError(
                                        "Không đọc được mã QR trong ảnh. Chọn ảnh rõ nét chứa trọn mã QR của lịch đặt.",
                                    );
                            } finally {
                                URL.revokeObjectURL(url);
                                if (mounted.current) setBusy(false);
                            }
                        }}
                    />
                </label>
            </div>
            {busy && (
                <p role="status" className="text-sm">
                    Đang kiểm tra mã QR…
                </p>
            )}
            {error && (
                <p role="alert" className="text-sm text-red-700">
                    {error}
                </p>
            )}
            {active && (
                <video
                    ref={video}
                    className="max-h-72 w-full rounded-xl bg-black"
                    muted
                    playsInline
                />
            )}
        </div>
    );
}
