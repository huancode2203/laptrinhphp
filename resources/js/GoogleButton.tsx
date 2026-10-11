import { useEffect } from "react";
import { useHub } from "./hub";

export default function GoogleButton({
    linked = false,
    after,
}: {
    linked?: boolean;
    after: () => Promise<void> | void;
}) {
    const { data, run, toast, loading } = useHub();
    useEffect(() => {
        if (data?.firebase) import("./firebaseGoogle");
    }, [data?.firebase?.projectId]);
    const label = linked ? "Liên kết Google" : "Tiếp tục với Google";
    const mark = (
        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
            <path
                fill="#4285F4"
                d="M22 12.2c0-.7-.1-1.4-.2-2.1H12v4h5.6a4.8 4.8 0 0 1-2.1 3.1v2.6H19c2-1.8 3-4.4 3-7.6Z"
            />
            <path
                fill="#34A853"
                d="M12 22c2.8 0 5.1-.9 6.9-2.5l-3.4-2.6c-.9.6-2.1 1-3.5 1-2.7 0-5-1.9-5.8-4.4H2.7v2.7A10 10 0 0 0 12 22Z"
            />
            <path
                fill="#FBBC05"
                d="M6.2 13.5a6 6 0 0 1 0-3.1V7.7H2.7a10 10 0 0 0 0 8.5l3.5-2.7Z"
            />
            <path
                fill="#EA4335"
                d="M12 6.1c1.5 0 2.8.5 3.9 1.5l2.9-2.9A10 10 0 0 0 2.7 7.7l3.5 2.7A6.1 6.1 0 0 1 12 6.1Z"
            />
        </svg>
    );
    if (!data?.google_enabled) return null;
    if (!data.firebase)
        return (
            <a className="google-button" href="/auth/google">
                {mark}
                {label}
            </a>
        );
    return (
        <button
            className="google-button"
            disabled={loading}
            onClick={() =>
                run(async () => {
                    const { googleLogin } = await import("./firebaseGoogle");
                    const response = await googleLogin(data.firebase);
                    toast(response.message);
                    await after();
                })
            }
        >
            {mark}
            {label}
        </button>
    );
}
