import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { build, transform } from "esbuild";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const adminRoot = path.resolve(scriptDirectory, "..");
const outputRoot = path.resolve(adminRoot, "../../public");
const outputJsRoot = path.join(outputRoot, "assets", "js");
const outputAdminJsRoot = path.join(outputJsRoot, "admin");
const outputAdminCssRoot = path.join(outputRoot, "assets", "css", "admin");

function minifyHtml(source) {
  // Keep the cache-buster comments while removing section comments and the
  // whitespace between tags. Script/style contents are left untouched.
  return source.split(/(<(?:script|style|pre|textarea)\b[^>]*>[\s\S]*?<\/(?:script|style|pre|textarea)\s*>)/gi)
    .map((part, index) => index % 2 ? part : part
      .replace(/<!--(?!\s*Increase \+1)[\s\S]*?-->/g, "")
      .replace(/^[\t ]+$/gm, "")
      .replace(/>\s+</g, "><"))
    .join("").trim() + "\n";
}

async function writeJavaScript(sourcePath, destinationPath) {
  const result = await build({
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
    write: false,
  });
  return result.outputFiles[0].contents;
}

async function writeCss(sourcePath) {
  const source = await fs.readFile(sourcePath, "utf8");
  const result = await transform(source, {
    loader: "css",
    minify: true,
    sourcemap: false,
  });
  return result.code;
}

await fs.mkdir(outputAdminJsRoot, { recursive: true });
await fs.mkdir(outputAdminCssRoot, { recursive: true });

const javascript = await writeJavaScript(
  path.join(adminRoot, "admin.js"),
  path.join(outputAdminJsRoot, "admin.js"),
);
const css = await writeCss(
  path.join(adminRoot, "admin.css"),
);

const html = await fs.readFile(path.join(adminRoot, "admin.html"), "utf8");
const minifiedHtml = minifyHtml(html);
// Compile every input before replacing any existing build output.
await fs.writeFile(path.join(outputAdminJsRoot, "admin.js"), javascript);
await fs.writeFile(path.join(outputAdminCssRoot, "admin.css"), css, "utf8");
await fs.writeFile(
  path.join(outputRoot, "admin.html"),
  minifiedHtml,
  "utf8",
);

console.log("Admin build complete: 1 bundled JS, 1 CSS, 1 HTML file.");
