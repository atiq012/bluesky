// vite.config.js
import { defineConfig } from "file:///E:/laragon/www/B2BBlueSky/node_modules/vite/dist/node/index.js";
import laravel from "file:///E:/laragon/www/B2BBlueSky/node_modules/laravel-vite-plugin/dist/index.js";
import vue from "file:///E:/laragon/www/B2BBlueSky/node_modules/@vitejs/plugin-vue/dist/index.mjs";
var vite_config_default = defineConfig({
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
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJFOlxcXFxsYXJhZ29uXFxcXHd3d1xcXFxCMkJCbHVlU2t5XCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ZpbGVuYW1lID0gXCJFOlxcXFxsYXJhZ29uXFxcXHd3d1xcXFxCMkJCbHVlU2t5XFxcXHZpdGUuY29uZmlnLmpzXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ltcG9ydF9tZXRhX3VybCA9IFwiZmlsZTovLy9FOi9sYXJhZ29uL3d3dy9CMkJCbHVlU2t5L3ZpdGUuY29uZmlnLmpzXCI7aW1wb3J0IHsgZGVmaW5lQ29uZmlnIH0gZnJvbSBcInZpdGVcIjtcbmltcG9ydCBsYXJhdmVsIGZyb20gXCJsYXJhdmVsLXZpdGUtcGx1Z2luXCI7XG5pbXBvcnQgdnVlIGZyb20gXCJAdml0ZWpzL3BsdWdpbi12dWVcIjtcblxuZXhwb3J0IGRlZmF1bHQgZGVmaW5lQ29uZmlnKHtcblxuICAgIGJ1aWxkOiB7XG4gICAgICAgIGNodW5rU2l6ZVdhcm5pbmdMaW1pdDogMTYwMCxcbiAgICB9LFxuICAgIHBsdWdpbnM6IFtcbiAgICAgICAgbGFyYXZlbCh7XG4gICAgICAgICAgICBpbnB1dDogW1wicmVzb3VyY2VzL2Nzcy9hcHAuY3NzXCIsIFwicmVzb3VyY2VzL2pzL2FwcC5qc1wiXSxcbiAgICAgICAgICAgIHJlZnJlc2g6IHRydWUsXG4gICAgICAgIH0pLFxuICAgICAgICB2dWUoKSxcbiAgICBdLFxuICAgIHJlc29sdmU6IHtcbiAgICAgICAgYWxpYXM6IHtcbiAgICAgICAgICAgIHZ1ZTogXCJ2dWUvZGlzdC92dWUuZXNtLWJ1bmRsZXIuanNcIixcbiAgICAgICAgICAgICckJzogJ2pRdWVyeScsXG4gICAgICAgICAgICAnanF1ZXJ5JzogJ2pxdWVyeS9kaXN0L2pxdWVyeS5qcycsXG4gICAgICAgIH0sXG4gICAgfSxcbiAgICBvcHRpbWl6ZURlcHM6IHtcbiAgICAgICAgaW5jbHVkZTogWydqcXVlcnknXVxuICAgIH1cbn0pO1xuIl0sCiAgIm1hcHBpbmdzIjogIjtBQUFxUSxTQUFTLG9CQUFvQjtBQUNsUyxPQUFPLGFBQWE7QUFDcEIsT0FBTyxTQUFTO0FBRWhCLElBQU8sc0JBQVEsYUFBYTtBQUFBLEVBRXhCLE9BQU87QUFBQSxJQUNILHVCQUF1QjtBQUFBLEVBQzNCO0FBQUEsRUFDQSxTQUFTO0FBQUEsSUFDTCxRQUFRO0FBQUEsTUFDSixPQUFPLENBQUMseUJBQXlCLHFCQUFxQjtBQUFBLE1BQ3RELFNBQVM7QUFBQSxJQUNiLENBQUM7QUFBQSxJQUNELElBQUk7QUFBQSxFQUNSO0FBQUEsRUFDQSxTQUFTO0FBQUEsSUFDTCxPQUFPO0FBQUEsTUFDSCxLQUFLO0FBQUEsTUFDTCxLQUFLO0FBQUEsTUFDTCxVQUFVO0FBQUEsSUFDZDtBQUFBLEVBQ0o7QUFBQSxFQUNBLGNBQWM7QUFBQSxJQUNWLFNBQVMsQ0FBQyxRQUFRO0FBQUEsRUFDdEI7QUFDSixDQUFDOyIsCiAgIm5hbWVzIjogW10KfQo=
