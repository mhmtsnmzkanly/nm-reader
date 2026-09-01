import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import path from "path";
import fs from "fs";
import { defineConfig } from "vite";

export default defineConfig(() => {
  // Projede app.html veya index.html hangisi varsa giriş olarak kullan
  const inputHtml = fs.existsSync(path.resolve(__dirname, "app.html"))
    ? path.resolve(__dirname, "app.html")
    : path.resolve(__dirname, "index.html");

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
    },
    build: {
      outDir: path.resolve(__dirname, "../public"),
      emptyOutDir: false,
      rollupOptions: {
        input: inputHtml,
        output: {
          // --- 1. DOSYA ADLANDIRMA VE KLASÖRLEME ---
          entryFileNames: "assets/js/[hash]/[name].js",
          chunkFileNames: "assets/js/[hash]/[name].js",

          // Statik varlıkları türlerine göre ayrı alt klasörlere yerleştirme:
          assetFileNames: (assetInfo) => {
            const name = assetInfo.name || "";

            // index.html to app.html for backend if needed
            if (name === "index.html" || name === "app.html") {
              return "app.html";
            }

            if (/\.(woff|woff2|eot|ttf|otf)$/i.test(name)) {
              return "assets/fonts/[hash]/[name][extname]";
            }

            if (/\.(png|jpe?g|svg|gif|webp|ico)$/i.test(name)) {
              return "assets/images/[hash]/[name][extname]";
            }
            if (/\.css$/i.test(name)) {
              return "assets/css/[hash]/[name][extname]";
            }
            return "assets/[hash]/[name][extname]";
          },

          // --- 2. CHUNK BÖLME (MANUAL CHUNKS) ---
          // React hook/context uyumsuzluğunu önleyecek şekilde izole chunking
          manualChunks(id) {
            if (id.includes("node_modules")) {
              if (
                id.includes("/react/") ||
                id.includes("/react-dom/") ||
                id.includes("/react-router/") ||
                id.includes("/react-router-dom/") ||
                id.includes("/scheduler/")
              ) {
                return "react";
              }
              if (id.includes("lucide-react")) {
                return "lucide";
              }
              return "vendor";
            }
          },
        },
      },
    },
    server: {
      hmr: process.env.DISABLE_HMR !== "true",
      watch: process.env.DISABLE_HMR === "true" ? null : {},
    },
  };
});
