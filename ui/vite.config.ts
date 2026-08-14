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
          entryFileNames: "assets/js/[name]-[hash].js",
          chunkFileNames: "assets/js/[name]-[hash].js",

          // Statik varlıkları türlerine göre ayrı alt klasörlere yerleştirme:
          assetFileNames: (assetInfo) => {
            const name = assetInfo.name || "";

            if (/\.(woff|woff2|eot|ttf|otf)$/i.test(name)) {
              return "assets/fonts/[name]-[hash][extname]";
            }
            if (/\.(png|jpe?g|svg|gif|webp|ico)$/i.test(name)) {
              return "assets/images/[name]-[hash][extname]";
            }
            if (/\.css$/i.test(name)) {
              return "assets/css/[name]-[hash][extname]";
            }
            return "assets/[name]-[hash][extname]";
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
