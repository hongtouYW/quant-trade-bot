import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
// import { visualizer } from "rollup-plugin-visualizer";

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      "/api": {
        target: "https://insapi.uxb89.com",
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ""),
      },
    },
  },
  build: {
    rollupOptions: {
      // plugins: [
      //   visualizer({
      //     open: false, // 直接在浏览器中打开分析报告
      //     filename: "stats.html", // 输出文件的名称
      //     gzipSize: true, // 显示gzip后的大小
      //     brotliSize: true, // 显示brotli压缩后的大小
      //   }),
      // ],
      output: {
        manualChunks: {
          html2canvas: ["html2canvas"],
          'dplayer': ["dplayer"],
          'hls.js': ["hls.js"],
        },
        // manualChunks(id) {
        //   if (id.includes("html2canvas")) {
        //     return "html2canvas"; // 把 html2canvas 相关代码分到一个单独 chunk
        //   }

        //   // if (id.includes("video.js")) {
        //   //   return "video.js"; // 把 video.js 相关代码分到一个单独 chunk
        //   // }
        // },
      },
    },
  },
});
