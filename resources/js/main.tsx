import React from "react";
import { createRoot } from "react-dom/client";
import "../css/app.css";
import App from "./App";
import { HubProvider } from "./hub";
import ErrorBoundary from "./ErrorBoundary";
createRoot(document.getElementById("root")!).render(
    <React.StrictMode>
        <ErrorBoundary>
            <HubProvider>
                <App />
            </HubProvider>
        </ErrorBoundary>
    </React.StrictMode>,
);
