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
    // Keep hashed chunks available to people already using an earlier build.
    build: { chunkSizeWarningLimit: 800, emptyOutDir: false },
    server: {
        host: "127.0.0.1",
        watch: { ignored: ["**/storage/framework/views/**"] },
    },
});
