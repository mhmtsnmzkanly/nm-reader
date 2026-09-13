import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { transform } from "esbuild";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const adminRoot = path.resolve(scriptDirectory, "..");
const outputRoot = path.resolve(adminRoot, "../../public");
const outputJsRoot = path.join(outputRoot, "assets", "js");
const outputAdminJsRoot = path.join(outputJsRoot, "admin");
const outputModuleRoot = path.join(outputAdminJsRoot, "modules");
const legacyModuleRoot = path.join(outputJsRoot, "modules");
const outputAdminCssRoot = path.join(outputRoot, "assets", "css", "admin");

async function walk(directory) {
  const entries = await fs.readdir(directory, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const absolutePath = path.join(directory, entry.name);
    if (entry.isDirectory()) files.push(...await walk(absolutePath));
    else if (entry.isFile()) files.push(absolutePath);
  }
  return files;
}

function minifyHtml(source) {
  // Keep the cache-buster comments while removing section comments and the
  // whitespace between tags. Script/style contents are left untouched.
  return source
    .replace(/<!--(?!\s*Increase \+1)[\s\S]*?-->/g, "")
    .replace(/>\s+</g, "><")
    .trim() + "\n";
}

async function writeJavaScript(sourcePath, destinationPath) {
  const source = await fs.readFile(sourcePath, "utf8");
  const result = await transform(source, {
    loader: "js",
    format: "esm",
    target: "es2020",
    minify: true,
    legalComments: "none",
    sourcemap: false,
  });
  await fs.mkdir(path.dirname(destinationPath), { recursive: true });
  await fs.writeFile(destinationPath, result.code, "utf8");
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

await fs.rm(outputModuleRoot, { recursive: true, force: true });
await fs.rm(legacyModuleRoot, { recursive: true, force: true });
await fs.rm(path.join(outputJsRoot, "admin.js"), { force: true });
await fs.rm(path.join(outputRoot, "assets", "css", "admin.css"), { force: true });
await fs.mkdir(outputModuleRoot, { recursive: true });
await fs.mkdir(outputAdminJsRoot, { recursive: true });
await fs.mkdir(outputAdminCssRoot, { recursive: true });

const moduleFiles = (await walk(path.join(adminRoot, "modules")))
  .filter((file) => file.endsWith(".js"));
await Promise.all(moduleFiles.map((sourcePath) => {
  const relativePath = path.relative(path.join(adminRoot, "modules"), sourcePath);
  return writeJavaScript(
    sourcePath,
    path.join(outputModuleRoot, relativePath),
  );
}));

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

console.log(
  `Admin build complete: ${moduleFiles.length + 1} JS, 1 CSS, 1 HTML file.`,
);
