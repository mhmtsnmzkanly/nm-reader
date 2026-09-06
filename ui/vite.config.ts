import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import path from "path";
import fs from "fs";
import { defineConfig } from "vite";

export default defineConfig(() => {
  const outputDir = path.resolve(__dirname, "../public");
  const manifestPath = path.join(outputDir, "assets/build-manifest.json");

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

  const cleanupStaleBuildAssets = {
    name: "cleanup-stale-build-assets",
    closeBundle() {
      if (!fs.existsSync(manifestPath)) return;

      let currentBuildFiles: Set<string>;
      try {
        currentBuildFiles = manifestFiles(fs.readFileSync(manifestPath, "utf8"));
      } catch {
        return;
      }

      const currentBuildDirectories = new Set(
        [...currentBuildFiles].map((relativeFile) => path.dirname(relativeFile))
      );

      // Vite creates one eight-character hash directory per generated asset.
      // Prune only those recognized directories, and only after a valid new
      // manifest exists, so unrelated public files are never touched.
      for (const assetType of ["js", "css", "fonts", "images"]) {
        const assetRoot = path.join(outputDir, "assets", assetType);
        if (!fs.existsSync(assetRoot)) continue;

        for (const entry of fs.readdirSync(assetRoot, { withFileTypes: true })) {
          if (!entry.isDirectory() || !/^[A-Za-z0-9_-]{8}$/.test(entry.name)) continue;

          const relativeDirectory = `assets/${assetType}/${entry.name}`;
          if (currentBuildDirectories.has(relativeDirectory)) continue;

          const staleDirectory = path.join(assetRoot, entry.name);
          if (path.dirname(staleDirectory) !== assetRoot) continue;
          fs.rmSync(staleDirectory, { recursive: true, force: true });
        }

        // Remove assets produced by the project's former flat naming scheme;
        // admin bundles and other hand-maintained files do not match this form.
        for (const entry of fs.readdirSync(assetRoot, { withFileTypes: true })) {
          if (!entry.isFile()) continue;
          if (!/^(?:app|react|vendor)-[A-Za-z0-9_-]{8}\.(?:js|css)$/.test(entry.name)) continue;

          const staleFile = path.join(assetRoot, entry.name);
          if (path.dirname(staleFile) !== assetRoot) continue;
          fs.rmSync(staleFile);
        }
      }
    },
  };

  // Projede app.html veya index.html hangisi varsa giriş olarak kullan
  const inputHtml = fs.existsSync(path.resolve(__dirname, "app.html"))
    ? path.resolve(__dirname, "app.html")
    : path.resolve(__dirname, "index.html");

  return {
    plugins: [react(), tailwindcss(), cleanupStaleBuildAssets],
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
