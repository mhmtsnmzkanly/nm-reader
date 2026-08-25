import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import path from "path";
import { defineConfig } from "vite";

export default defineConfig(() => {
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
        input: path.resolve(__dirname, "app.html"),
        output: {
          // --- 1. DOSYA ADLANDIRMA VE KLASÖRLEME ---
          entryFileNames: "assets/js/[hash]/[name].js",
          chunkFileNames: "assets/js/[hash]/[name].js",

          // Statik varlıkları türlerine göre ayrı alt klasörlere yerleştirme:
          assetFileNames: (assetInfo) => {
            const name = assetInfo.name || "";

            // index.html to app.html for backend
            if (name === 'index.html')
              return 'app.html';

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
          manualChunks(id) {
            if (id.includes("node_modules")) {
              // React çekirdek kütüphanelerini ayrı bir chunk yap
              if (
                id.includes("react") ||
                id.includes("react-dom") ||
                id.includes("react-router")
              ) {
                return "react";
              }
              // Büyük ikon veya UI kütüphaneleri varsa ayırabilirsiniz (örnek: lucide-react)
              if (id.includes("lucide-react")) {
                return "lucide";
              }
              // Geriye kalan diğer üçüncü parti kütüphaneler
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
