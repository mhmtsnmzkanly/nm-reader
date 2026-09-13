import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { build, transform } from "esbuild";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const adminRoot = path.resolve(scriptDirectory, "..");
const outputRoot = path.resolve(adminRoot, "../../public");
const outputJsRoot = path.join(outputRoot, "assets", "js");
const outputAdminJsRoot = path.join(outputJsRoot, "admin");
const outputModuleRoot = path.join(outputAdminJsRoot, "modules");
const legacyModuleRoot = path.join(outputJsRoot, "modules");
const outputAdminCssRoot = path.join(outputRoot, "assets", "css", "admin");

function minifyHtml(source) {
  // Keep the cache-buster comments while removing section comments and the
  // whitespace between tags. Script/style contents are left untouched.
  return source
    .replace(/<!--(?!\s*Increase \+1)[\s\S]*?-->/g, "")
    .replace(/>\s+</g, "><")
    .trim() + "\n";
}

async function writeJavaScript(sourcePath, destinationPath) {
  await build({
    entryPoints: [sourcePath],
    outfile: destinationPath,
    bundle: true,
    platform: "browser",
    external: ["https://*", "http://*"],
    format: "esm",
    target: "es2020",
    minify: true,
    legalComments: "none",
    sourcemap: false,
  });
}

async function writeCss(sourcePath, destinationPath) {
  const source = await fs.readFile(sourcePath, "utf8");
  const result = await transform(source, {
    loader: "css",
    minify: true,
    sourcemap: false,
  });
  await fs.mkdir(path.dirname(destinationPath), { recursive: true });
  await fs.writeFile(destinationPath, result.code, "utf8");
}

await fs.mkdir(outputAdminJsRoot, { recursive: true });
await fs.mkdir(outputAdminCssRoot, { recursive: true });

await writeJavaScript(
  path.join(adminRoot, "admin.js"),
  path.join(outputAdminJsRoot, "admin.js"),
);
await writeCss(
  path.join(adminRoot, "admin.css"),
  path.join(outputAdminCssRoot, "admin.css"),
);

const html = await fs.readFile(path.join(adminRoot, "admin.html"), "utf8");
await fs.writeFile(
  path.join(outputRoot, "admin.html"),
  minifyHtml(html),
  "utf8",
);

// Remove obsolete generated modules only after the replacement build succeeds.
await fs.rm(outputModuleRoot, { recursive: true, force: true });
await fs.rm(legacyModuleRoot, { recursive: true, force: true });
await fs.rm(path.join(outputJsRoot, "admin.js"), { force: true });
await fs.rm(path.join(outputRoot, "assets", "css", "admin.css"), { force: true });

console.log("Admin build complete: 1 bundled JS, 1 CSS, 1 HTML file.");
