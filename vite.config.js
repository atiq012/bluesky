import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import path from "path";

export default defineConfig({
    server: {
        host: "0.0.0.0",
        port: 5000,
        strictPort: true,
        cors: {
            origin: [
                "http://bluesky.lo",
                "http://localhost:8081",
                "http://127.0.0.1:8081",
                "http://localhost:5000",
                "http://127.0.0.1:5000",
                "http://127.0.0.1:8000",
            ],
        },
        hmr: {
            host: "localhost",
            port: 5000,
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
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            "@": path.resolve("./resources/js"),
            vue: "vue/dist/vue.esm-bundler.js",
            '$': 'jQuery',
            'jquery': 'jquery/dist/jquery.js',
        },
    },
    optimizeDeps: {
        include: ['jquery']
    }
});
