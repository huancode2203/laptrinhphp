import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
export default defineConfig({
    plugins: [
        laravel({ input: ["resources/js/main.tsx"], refresh: true }),
        react(),
        tailwindcss(),
    ],
    build: { chunkSizeWarningLimit: 800 },
    server: {
        host: "127.0.0.1",
        watch: { ignored: ["**/storage/framework/views/**"] },
    },
});
