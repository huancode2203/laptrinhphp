import { useEffect, useRef, useState } from "react";
import { BrowserQRCodeReader, type IScannerControls } from "@zxing/browser";
export default function QRScanner({
    found,
}: {
    found: (token: string) => void;
}) {
    const video = useRef<HTMLVideoElement>(null),
        controls = useRef<IScannerControls | null>(null),
        [active, setActive] = useState(false),
        [error, setError] = useState("");
    useEffect(() => {
        let alive = true;
        const reader = new BrowserQRCodeReader();
        if (active && video.current)
            reader
                .decodeFromConstraints(
                    { audio: false, video: { facingMode: "environment" } },
                    video.current,
                    (result) => {
                        if (result && alive) {
                            found(result.getText());
                            setActive(false);
                        }
                    },
                )
                .then((c) => {
                    if (alive) controls.current = c;
                    else c.stop();
                })
                .catch(() => {
                    if (alive) {
                        setError(
                            "Chưa mở được camera. Kiểm tra quyền camera hoặc nhập mã từ khách.",
                        );
                        setActive(false);
                    }
                });
        return () => {
            alive = false;
            controls.current?.stop();
            controls.current = null;
        };
    }, [active]);
    return (
        <div>
            <button
                className="btn-outline"
                onClick={() => {
                    setError("");
                    setActive(!active);
                }}
            >
                {active ? "Dừng camera" : "Quét QR bằng camera"}
            </button>
            {error && (
                <p role="alert" className="my-3 text-sm text-red-700">
                    {error}
                </p>
            )}
            {active && (
                <video
                    ref={video}
                    className="my-3 max-h-72 w-full rounded-xl bg-black"
                    muted
                    playsInline
                />
            )}
        </div>
    );
}
