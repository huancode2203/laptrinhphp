import { initializeApp, getApps } from "firebase/app";
import {
    getAuth,
    GoogleAuthProvider,
    inMemoryPersistence,
    setPersistence,
    signInWithPopup,
    signOut,
} from "firebase/auth";
import { api } from "./hub";
import type { Row } from "./data";

export async function googleLogin(config: Row) {
    const appName = `hub-google-${config.projectId}`;
    const app =
        getApps().find((a) => a.name === appName) ??
        initializeApp(config, appName);
    const auth = getAuth(app);
    await setPersistence(auth, inMemoryPersistence);
    const provider = new GoogleAuthProvider();
    provider.setCustomParameters({ prompt: "select_account" });
    try {
        const result = await signInWithPopup(auth, provider);
        return await api("auth/firebase", "POST", {
            id_token: await result.user.getIdToken(true),
        });
    } catch (error) {
        const code = (error as { code?: string }).code;
        const messages: Record<string, string> = {
            "auth/popup-closed-by-user": "Bạn đã đóng cửa sổ đăng nhập Google.",
            "auth/cancelled-popup-request":
                "Hãy hoàn tất cửa sổ Google đang mở.",
            "auth/popup-blocked":
                "Trình duyệt đang chặn cửa sổ Google. Hãy cho phép cửa sổ bật lên và thử lại.",
            "auth/unauthorized-domain":
                "Đăng nhập Google chưa được bật cho tên miền này. Vui lòng liên hệ hỗ trợ.",
            "auth/operation-not-allowed":
                "Google hiện chưa được bật. Vui lòng dùng email để đăng nhập.",
            "auth/network-request-failed":
                "Chưa kết nối được Google. Hãy kiểm tra kết nối và thử lại.",
            "auth/invalid-api-key":
                "Đăng nhập Google chưa được cấu hình đúng. Vui lòng liên hệ hỗ trợ.",
        };
        throw new Error(
            code
                ? (messages[code] ??
                      "Chưa đăng nhập được Google. Vui lòng thử lại.")
                : (error as Error).message,
        );
    } finally {
        await signOut(auth).catch(() => {});
    }
}
