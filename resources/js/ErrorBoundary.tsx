import { Component, type ReactNode } from "react";
export default class ErrorBoundary extends Component<
    { children: ReactNode },
    { failed: boolean }
> {
    state = { failed: false };
    static getDerivedStateFromError() {
        return { failed: true };
    }
    render() {
        if (this.state.failed)
            return (
                <main className="grid min-h-screen place-items-center p-6">
                    <div className="app-card max-w-lg p-8">
                        <h1 className="text-xl font-bold">
                            Màn hình chưa tải được
                        </h1>
                        <p className="my-4 text-sm text-[#6d8474]">
                            Tải lại để tiếp tục ở trang này. Lịch giữ chỗ của
                            bạn được kiểm tra lại trước khi xác nhận.
                        </p>
                        <button
                            className="btn"
                            onClick={() => location.reload()}
                        >
                            Tải lại trang hiện tại
                        </button>
                    </div>
                </main>
            );
        return this.props.children;
    }
}
