import fs from "node:fs/promises";
import path from "node:path";
import { spawn } from "node:child_process";
import { execFileSync } from "node:child_process";
import { auditTemplates } from "./template-audit.mjs";
import { fileURLToPath } from "node:url";
import { transform } from "esbuild";
import { build } from "esbuild";
import { ESLint } from "eslint";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const adminRoot = path.resolve(scriptDirectory, "..");

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

function checkJavaScript(file) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, ["--check", file], { stdio: "inherit" });
    child.once("error", reject);
    child.once("exit", (code) => {
      if (code === 0) resolve();
      else reject(new Error(`JavaScript syntax check failed: ${file}`));
    });
  });
}

const javascriptFiles = [
  path.join(adminRoot, "admin.js"),
  ...(await walk(path.join(adminRoot, "modules"))).filter((file) => file.endsWith(".js")),
];
await Promise.all(javascriptFiles.map(checkJavaScript));
const eslint = new ESLint({ cwd: adminRoot });
const results = await eslint.lintFiles([
  ...javascriptFiles, "scripts/**/*.mjs", "tests/**/*.mjs", "eslint.config.mjs",
]);
if (results.some((result) => result.errorCount || result.warningCount)) {
  const formatter = await eslint.loadFormatter("stylish");
  throw new Error(formatter.format(results));
}
// Resolve local imports and named exports without writing build artifacts.
// CDN imports stay external, just as in the production bundle.
await build({
  entryPoints: [path.join(adminRoot, "admin.js")],
  bundle: true, write: false, format: "esm", platform: "browser",
  external: ["https://*", "http://*"], logLevel: "silent",
});

const html = await fs.readFile(path.join(adminRoot, "admin.html"), "utf8");
const dictionaries = Object.fromEntries(["tr", "en"].map((locale) => {
  const file = path.resolve(adminRoot, "../../storage/lang", `${locale}.php`);
  const dictionary = JSON.parse(execFileSync("php", [
    "-r", "echo json_encode(require $argv[1], JSON_THROW_ON_ERROR);", file,
  ], { encoding: "utf8" }));
  return [locale, dictionary];
}));
const audit = auditTemplates(html, dictionaries);
if (audit.errors.length) throw new Error(audit.errors.join("\n"));
if (!html.includes("<template") || /%%NMR_[A-Z_]+%%/.test(html)) {
  throw new Error("Admin HTML must contain templates and no SSR placeholders.");
}
for (const script of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
  if (/\bsrc\s*=/.test(script[1])) continue;
  const source = script[2];
  const inlineResults = await eslint.lintText(source, {
    filePath: path.join(adminRoot, "admin.js"),
  });
  if (inlineResults.some((result) => result.errorCount || result.warningCount)) {
    const formatter = await eslint.loadFormatter("stylish");
    throw new Error(`Admin HTML inline script:\n${formatter.format(inlineResults)}`);
  }
  await transform(source, {
    loader: "js",
    target: "esnext",
  });
}
const css = await fs.readFile(path.join(adminRoot, "admin.css"), "utf8");
const cssResult = await transform(css, { loader: "css" });
if (cssResult.warnings.length) {
  throw new Error(cssResult.warnings.map((warning) => warning.text).join("\n"));
}
console.log(`Admin lint passed: ${javascriptFiles.length} JavaScript files.`);
