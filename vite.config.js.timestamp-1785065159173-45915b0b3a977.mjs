// vite.config.js
import { defineConfig } from "file:///var/www/html/node_modules/vite/dist/node/index.js";
import laravel from "file:///var/www/html/node_modules/laravel-vite-plugin/dist/index.js";
import vue from "file:///var/www/html/node_modules/@vitejs/plugin-vue/dist/index.mjs";
import path from "path";
var vite_config_default = defineConfig({
  server: {
    host: "0.0.0.0",
    port: 5173,
    strictPort: true,
    cors: {
      origin: [
        "http://bsagency.lo",
        "http://bluesky.lo",
        "http://localhost:8081",
        "http://127.0.0.1:8081",
        "http://localhost:5173",
        "http://127.0.0.1:5173",
        "http://localhost:5181",
        "http://127.0.0.1:5181",
        "http://127.0.0.1:8000"
      ]
    },
    hmr: {
      host: "localhost",
      clientPort: 5181
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
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCIvdmFyL3d3dy9odG1sXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ZpbGVuYW1lID0gXCIvdmFyL3d3dy9odG1sL3ZpdGUuY29uZmlnLmpzXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ltcG9ydF9tZXRhX3VybCA9IFwiZmlsZTovLy92YXIvd3d3L2h0bWwvdml0ZS5jb25maWcuanNcIjtpbXBvcnQgeyBkZWZpbmVDb25maWcgfSBmcm9tIFwidml0ZVwiO1xuaW1wb3J0IGxhcmF2ZWwgZnJvbSBcImxhcmF2ZWwtdml0ZS1wbHVnaW5cIjtcbmltcG9ydCB2dWUgZnJvbSBcIkB2aXRlanMvcGx1Z2luLXZ1ZVwiO1xuaW1wb3J0IHBhdGggZnJvbSBcInBhdGhcIjtcblxuZXhwb3J0IGRlZmF1bHQgZGVmaW5lQ29uZmlnKHtcbiAgICBzZXJ2ZXI6IHtcbiAgICAgICAgaG9zdDogXCIwLjAuMC4wXCIsXG4gICAgICAgIHBvcnQ6IDUxNzMsXG4gICAgICAgIHN0cmljdFBvcnQ6IHRydWUsXG4gICAgICAgIGNvcnM6IHtcbiAgICAgICAgICAgIG9yaWdpbjogW1xuICAgICAgICAgICAgICAgIFwiaHR0cDovL2JzYWdlbmN5LmxvXCIsXG4gICAgICAgICAgICAgICAgXCJodHRwOi8vYmx1ZXNreS5sb1wiLFxuICAgICAgICAgICAgICAgIFwiaHR0cDovL2xvY2FsaG9zdDo4MDgxXCIsXG4gICAgICAgICAgICAgICAgXCJodHRwOi8vMTI3LjAuMC4xOjgwODFcIixcbiAgICAgICAgICAgICAgICBcImh0dHA6Ly9sb2NhbGhvc3Q6NTE3M1wiLFxuICAgICAgICAgICAgICAgIFwiaHR0cDovLzEyNy4wLjAuMTo1MTczXCIsXG4gICAgICAgICAgICAgICAgXCJodHRwOi8vbG9jYWxob3N0OjUxODFcIixcbiAgICAgICAgICAgICAgICBcImh0dHA6Ly8xMjcuMC4wLjE6NTE4MVwiLFxuICAgICAgICAgICAgICAgIFwiaHR0cDovLzEyNy4wLjAuMTo4MDAwXCIsXG4gICAgICAgICAgICBdLFxuICAgICAgICB9LFxuICAgICAgICBobXI6IHtcbiAgICAgICAgICAgIGhvc3Q6IFwibG9jYWxob3N0XCIsXG4gICAgICAgICAgICBjbGllbnRQb3J0OiA1MTgxLFxuICAgICAgICB9LFxuICAgICAgICB3YXRjaDoge1xuICAgICAgICAgICAgdXNlUG9sbGluZzogdHJ1ZSxcbiAgICAgICAgICAgIGludGVydmFsOiAzMDAsXG4gICAgICAgIH0sXG4gICAgfSxcbiAgICBidWlsZDoge1xuICAgICAgICBjaHVua1NpemVXYXJuaW5nTGltaXQ6IDE2MDAsXG4gICAgfSxcbiAgICBwbHVnaW5zOiBbXG4gICAgICAgIGxhcmF2ZWwoe1xuICAgICAgICAgICAgaW5wdXQ6IFtcInJlc291cmNlcy9jc3MvYXBwLmNzc1wiLCBcInJlc291cmNlcy9qcy9hcHAuanNcIl0sXG4gICAgICAgICAgICByZWZyZXNoOiB0cnVlLFxuICAgICAgICB9KSxcbiAgICAgICAgdnVlKCksXG4gICAgXSxcbiAgICByZXNvbHZlOiB7XG4gICAgICAgIGFsaWFzOiB7XG4gICAgICAgICAgICBcIkBcIjogcGF0aC5yZXNvbHZlKFwiLi9yZXNvdXJjZXMvanNcIiksXG4gICAgICAgICAgICB2dWU6IFwidnVlL2Rpc3QvdnVlLmVzbS1idW5kbGVyLmpzXCIsXG4gICAgICAgICAgICAnJCc6ICdqUXVlcnknLFxuICAgICAgICAgICAgJ2pxdWVyeSc6ICdqcXVlcnkvZGlzdC9qcXVlcnkuanMnLFxuICAgICAgICB9LFxuICAgIH0sXG4gICAgb3B0aW1pemVEZXBzOiB7XG4gICAgICAgIGluY2x1ZGU6IFsnanF1ZXJ5J11cbiAgICB9XG59KTtcbiJdLAogICJtYXBwaW5ncyI6ICI7QUFBeU4sU0FBUyxvQkFBb0I7QUFDdFAsT0FBTyxhQUFhO0FBQ3BCLE9BQU8sU0FBUztBQUNoQixPQUFPLFVBQVU7QUFFakIsSUFBTyxzQkFBUSxhQUFhO0FBQUEsRUFDeEIsUUFBUTtBQUFBLElBQ0osTUFBTTtBQUFBLElBQ04sTUFBTTtBQUFBLElBQ04sWUFBWTtBQUFBLElBQ1osTUFBTTtBQUFBLE1BQ0YsUUFBUTtBQUFBLFFBQ0o7QUFBQSxRQUNBO0FBQUEsUUFDQTtBQUFBLFFBQ0E7QUFBQSxRQUNBO0FBQUEsUUFDQTtBQUFBLFFBQ0E7QUFBQSxRQUNBO0FBQUEsUUFDQTtBQUFBLE1BQ0o7QUFBQSxJQUNKO0FBQUEsSUFDQSxLQUFLO0FBQUEsTUFDRCxNQUFNO0FBQUEsTUFDTixZQUFZO0FBQUEsSUFDaEI7QUFBQSxJQUNBLE9BQU87QUFBQSxNQUNILFlBQVk7QUFBQSxNQUNaLFVBQVU7QUFBQSxJQUNkO0FBQUEsRUFDSjtBQUFBLEVBQ0EsT0FBTztBQUFBLElBQ0gsdUJBQXVCO0FBQUEsRUFDM0I7QUFBQSxFQUNBLFNBQVM7QUFBQSxJQUNMLFFBQVE7QUFBQSxNQUNKLE9BQU8sQ0FBQyx5QkFBeUIscUJBQXFCO0FBQUEsTUFDdEQsU0FBUztBQUFBLElBQ2IsQ0FBQztBQUFBLElBQ0QsSUFBSTtBQUFBLEVBQ1I7QUFBQSxFQUNBLFNBQVM7QUFBQSxJQUNMLE9BQU87QUFBQSxNQUNILEtBQUssS0FBSyxRQUFRLGdCQUFnQjtBQUFBLE1BQ2xDLEtBQUs7QUFBQSxNQUNMLEtBQUs7QUFBQSxNQUNMLFVBQVU7QUFBQSxJQUNkO0FBQUEsRUFDSjtBQUFBLEVBQ0EsY0FBYztBQUFBLElBQ1YsU0FBUyxDQUFDLFFBQVE7QUFBQSxFQUN0QjtBQUNKLENBQUM7IiwKICAibmFtZXMiOiBbXQp9Cg==
