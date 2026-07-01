import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import path from "path";
import fs from "fs";

// Browser → Apache:80(bsagency.lo) → docker Apache:8082 + Vite node:5181
// devServerUrl must match browser origin (same-origin, no CORS)
const devServerUrl = "http://bsagency.lo";
const viteHostPort = 5181;

function fixLaravelHotUrl() {
    return {
        name: "fix-laravel-hot-url",
        configureServer(server) {
            server.httpServer?.once("listening", () => {
                setTimeout(() => {
                    fs.writeFileSync(path.resolve("public/hot"), devServerUrl);
                }, 150);
            });
        },
    };
}

export default defineConfig({
    server: {
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        origin: devServerUrl,
        cors: {
            origin: [
                devServerUrl,
                "http://localhost:8082",
                "http://127.0.0.1:8082",
            ],
        },
        hmr: {
            host: "localhost",
            clientPort: viteHostPort,
            protocol: "ws",
        },
        watch: {
            usePolling: true,
            interval: 300,
        },
    },
    build: {
        chunkSizeWarningLimit: 1600,
    },
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: false,
        }),
        fixLaravelHotUrl(),
        vue(),
    ],
    resolve: {
        alias: {
            vue: "vue/dist/vue.esm-bundler.js",
            $: "jQuery",
            jquery: "jquery/dist/jquery.js",
        },
    },
    optimizeDeps: {
        include: ["jquery"],
    },
});
