// vite.config.js
import { defineConfig } from "file:///E:/laragon/www/B2BBlueSky/node_modules/vite/dist/node/index.js";
import laravel from "file:///E:/laragon/www/B2BBlueSky/node_modules/laravel-vite-plugin/dist/index.js";
import vue from "file:///E:/laragon/www/B2BBlueSky/node_modules/@vitejs/plugin-vue/dist/index.mjs";
import path from "path";
var vite_config_default = defineConfig({
  server: {
    host: "0.0.0.0",
    port: 5173,
    strictPort: true,
    cors: {
      origin: [
        "http://bluesky.lo",
        "http://localhost:8081",
        "http://127.0.0.1:8081",
        "http://localhost:5173",
        "http://127.0.0.1:5173",
        "http://127.0.0.1:8000"
      ]
    },
    hmr: {
      host: "localhost",
      port: 5173
    },
    watch: {
      usePolling: true,
      interval: 300
    }
  },
  build: {
    chunkSizeWarningLimit: 1600
  },
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.js"],
      refresh: true
    }),
    vue()
  ],
  resolve: {
    alias: {
      "@": path.resolve("./resources/js"),
      vue: "vue/dist/vue.esm-bundler.js",
      "$": "jQuery",
      "jquery": "jquery/dist/jquery.js"
    }
  },
  optimizeDeps: {
    include: ["jquery"]
  }
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJFOlxcXFxsYXJhZ29uXFxcXHd3d1xcXFxCMkJCbHVlU2t5XCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ZpbGVuYW1lID0gXCJFOlxcXFxsYXJhZ29uXFxcXHd3d1xcXFxCMkJCbHVlU2t5XFxcXHZpdGUuY29uZmlnLmpzXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ltcG9ydF9tZXRhX3VybCA9IFwiZmlsZTovLy9FOi9sYXJhZ29uL3d3dy9CMkJCbHVlU2t5L3ZpdGUuY29uZmlnLmpzXCI7aW1wb3J0IHsgZGVmaW5lQ29uZmlnIH0gZnJvbSBcInZpdGVcIjtcbmltcG9ydCBsYXJhdmVsIGZyb20gXCJsYXJhdmVsLXZpdGUtcGx1Z2luXCI7XG5pbXBvcnQgdnVlIGZyb20gXCJAdml0ZWpzL3BsdWdpbi12dWVcIjtcbmltcG9ydCBwYXRoIGZyb20gXCJwYXRoXCI7XG5cbmV4cG9ydCBkZWZhdWx0IGRlZmluZUNvbmZpZyh7XG4gICAgc2VydmVyOiB7XG4gICAgICAgIGhvc3Q6IFwiMC4wLjAuMFwiLFxuICAgICAgICBwb3J0OiA1MTczLFxuICAgICAgICBzdHJpY3RQb3J0OiB0cnVlLFxuICAgICAgICBjb3JzOiB7XG4gICAgICAgICAgICBvcmlnaW46IFtcbiAgICAgICAgICAgICAgICBcImh0dHA6Ly9ibHVlc2t5LmxvXCIsXG4gICAgICAgICAgICAgICAgXCJodHRwOi8vbG9jYWxob3N0OjgwODFcIixcbiAgICAgICAgICAgICAgICBcImh0dHA6Ly8xMjcuMC4wLjE6ODA4MVwiLFxuICAgICAgICAgICAgICAgIFwiaHR0cDovL2xvY2FsaG9zdDo1MTczXCIsXG4gICAgICAgICAgICAgICAgXCJodHRwOi8vMTI3LjAuMC4xOjUxNzNcIixcbiAgICAgICAgICAgICAgICBcImh0dHA6Ly8xMjcuMC4wLjE6ODAwMFwiLFxuICAgICAgICAgICAgXSxcbiAgICAgICAgfSxcbiAgICAgICAgaG1yOiB7XG4gICAgICAgICAgICBob3N0OiBcImxvY2FsaG9zdFwiLFxuICAgICAgICAgICAgcG9ydDogNTE3MyxcbiAgICAgICAgfSxcbiAgICAgICAgd2F0Y2g6IHtcbiAgICAgICAgICAgIHVzZVBvbGxpbmc6IHRydWUsXG4gICAgICAgICAgICBpbnRlcnZhbDogMzAwLFxuICAgICAgICB9LFxuICAgIH0sXG4gICAgYnVpbGQ6IHtcbiAgICAgICAgY2h1bmtTaXplV2FybmluZ0xpbWl0OiAxNjAwLFxuICAgIH0sXG4gICAgcGx1Z2luczogW1xuICAgICAgICBsYXJhdmVsKHtcbiAgICAgICAgICAgIGlucHV0OiBbXCJyZXNvdXJjZXMvY3NzL2FwcC5jc3NcIiwgXCJyZXNvdXJjZXMvanMvYXBwLmpzXCJdLFxuICAgICAgICAgICAgcmVmcmVzaDogdHJ1ZSxcbiAgICAgICAgfSksXG4gICAgICAgIHZ1ZSgpLFxuICAgIF0sXG4gICAgcmVzb2x2ZToge1xuICAgICAgICBhbGlhczoge1xuICAgICAgICAgICAgXCJAXCI6IHBhdGgucmVzb2x2ZShcIi4vcmVzb3VyY2VzL2pzXCIpLFxuICAgICAgICAgICAgdnVlOiBcInZ1ZS9kaXN0L3Z1ZS5lc20tYnVuZGxlci5qc1wiLFxuICAgICAgICAgICAgJyQnOiAnalF1ZXJ5JyxcbiAgICAgICAgICAgICdqcXVlcnknOiAnanF1ZXJ5L2Rpc3QvanF1ZXJ5LmpzJyxcbiAgICAgICAgfSxcbiAgICB9LFxuICAgIG9wdGltaXplRGVwczoge1xuICAgICAgICBpbmNsdWRlOiBbJ2pxdWVyeSddXG4gICAgfVxufSk7XG4iXSwKICAibWFwcGluZ3MiOiAiO0FBQXFRLFNBQVMsb0JBQW9CO0FBQ2xTLE9BQU8sYUFBYTtBQUNwQixPQUFPLFNBQVM7QUFDaEIsT0FBTyxVQUFVO0FBRWpCLElBQU8sc0JBQVEsYUFBYTtBQUFBLEVBQ3hCLFFBQVE7QUFBQSxJQUNKLE1BQU07QUFBQSxJQUNOLE1BQU07QUFBQSxJQUNOLFlBQVk7QUFBQSxJQUNaLE1BQU07QUFBQSxNQUNGLFFBQVE7QUFBQSxRQUNKO0FBQUEsUUFDQTtBQUFBLFFBQ0E7QUFBQSxRQUNBO0FBQUEsUUFDQTtBQUFBLFFBQ0E7QUFBQSxNQUNKO0FBQUEsSUFDSjtBQUFBLElBQ0EsS0FBSztBQUFBLE1BQ0QsTUFBTTtBQUFBLE1BQ04sTUFBTTtBQUFBLElBQ1Y7QUFBQSxJQUNBLE9BQU87QUFBQSxNQUNILFlBQVk7QUFBQSxNQUNaLFVBQVU7QUFBQSxJQUNkO0FBQUEsRUFDSjtBQUFBLEVBQ0EsT0FBTztBQUFBLElBQ0gsdUJBQXVCO0FBQUEsRUFDM0I7QUFBQSxFQUNBLFNBQVM7QUFBQSxJQUNMLFFBQVE7QUFBQSxNQUNKLE9BQU8sQ0FBQyx5QkFBeUIscUJBQXFCO0FBQUEsTUFDdEQsU0FBUztBQUFBLElBQ2IsQ0FBQztBQUFBLElBQ0QsSUFBSTtBQUFBLEVBQ1I7QUFBQSxFQUNBLFNBQVM7QUFBQSxJQUNMLE9BQU87QUFBQSxNQUNILEtBQUssS0FBSyxRQUFRLGdCQUFnQjtBQUFBLE1BQ2xDLEtBQUs7QUFBQSxNQUNMLEtBQUs7QUFBQSxNQUNMLFVBQVU7QUFBQSxJQUNkO0FBQUEsRUFDSjtBQUFBLEVBQ0EsY0FBYztBQUFBLElBQ1YsU0FBUyxDQUFDLFFBQVE7QUFBQSxFQUN0QjtBQUNKLENBQUM7IiwKICAibmFtZXMiOiBbXQp9Cg==
