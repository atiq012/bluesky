import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig({

    server: {
        hmr: { host: 'localhost' },
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
            vue: "vue/dist/vue.esm-bundler.js",
            '$': 'jQuery',
            'jquery': 'jquery/dist/jquery.js',
        },
    },
    optimizeDeps: {
        include: ['jquery']
    }
});
