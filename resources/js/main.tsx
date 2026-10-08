import React from "react";
import { createRoot } from "react-dom/client";
import "../css/app.css";
import App from "./App";
import { HubProvider } from "./hub";
createRoot(document.getElementById("root")!).render(
    <React.StrictMode>
        <HubProvider>
            <App />
        </HubProvider>
    </React.StrictMode>,
);
