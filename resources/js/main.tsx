import React from "react";
import { createRoot } from "react-dom/client";
import "../css/app.css";
import App from "./App";
import { FriendlyForms } from "./ui";
import { HubProvider } from "./hub";
import ErrorBoundary from "./ErrorBoundary";
createRoot(document.getElementById("root")!).render(
    <React.StrictMode>
        <ErrorBoundary>
            <HubProvider>
                <FriendlyForms />
                <App />
            </HubProvider>
        </ErrorBoundary>
    </React.StrictMode>,
);
