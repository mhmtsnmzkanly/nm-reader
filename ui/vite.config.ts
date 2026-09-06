import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import path from "path";
import fs from "fs";
import { defineConfig } from "vite";

export default defineConfig(() => {
  const outputDir = path.resolve(__dirname, "../public");
  const manifestPath = path.join(outputDir, "assets/build-manifest.json");
  const latestPath = path.join(outputDir, "latest.txt");

  const manifestFiles = (raw: string): Set<string> => {
    const files = new Set<string>();
    const manifest = JSON.parse(raw) as Record<string, { file?: string; css?: string[]; assets?: string[] }>;
    for (const entry of Object.values(manifest)) {
      if (entry.file) files.add(entry.file);
      for (const file of entry.css ?? []) files.add(file);
      for (const file of entry.assets ?? []) files.add(file);
    }
    return files;
  };

  const writeLatestBuildInventory = {
    name: "write-latest-build-inventory",
    closeBundle() {
      if (!fs.existsSync(manifestPath)) {
        throw new Error(`Vite manifest was not created: ${manifestPath}`);
      }

      const currentBuildFiles = manifestFiles(fs.readFileSync(manifestPath, "utf8"));
      currentBuildFiles.add("app.html");
      currentBuildFiles.add("assets/build-manifest.json");
      currentBuildFiles.add("latest.txt");

      const inventory = [...currentBuildFiles]
        .map((relativeFile) => relativeFile.replaceAll("\\", "/"))
        .filter((relativeFile) => {
          return relativeFile !== ""
            && !path.isAbsolute(relativeFile)
            && !relativeFile.split("/").includes("..");
        })
        .sort((left, right) => left.localeCompare(right));

      fs.writeFileSync(latestPath, `${inventory.join("\n")}\n`, "utf8");
      console.log(`latest.txt generated with ${inventory.length} build files.`);
    },
  };

  // Projede app.html veya index.html hangisi varsa giriş olarak kullan
  const inputHtml = fs.existsSync(path.resolve(__dirname, "app.html"))
    ? path.resolve(__dirname, "app.html")
    : path.resolve(__dirname, "index.html");

  return {
    plugins: [react(), tailwindcss(), writeLatestBuildInventory],
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
    },
    build: {
      outDir: outputDir,
      emptyOutDir: false,
      manifest: "assets/build-manifest.json",
      rollupOptions: {
        input: inputHtml,
        output: {
          // --- 1. DOSYA ADLANDIRMA VE KLASÖRLEME ---
          entryFileNames: "assets/js/[name]-[hash].js",
          chunkFileNames: "assets/js/[name]-[hash].js",

          // Statik varlıkları türlerine göre ayrı alt klasörlere yerleştirme:
          assetFileNames: (assetInfo) => {
            const name = assetInfo.name || "";

            // index.html to app.html for backend if needed
            if (name === "index.html" || name === "app.html") {
              return "app.html";
            }

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
