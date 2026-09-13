import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const outputRoot = path.resolve(scriptDirectory, "../../../public");
const generatedPaths = [
  path.join(outputRoot, "admin.html"),
  path.join(outputRoot, "assets", "css", "admin"),
  path.join(outputRoot, "assets", "js", "admin"),
  path.join(outputRoot, "assets", "js", "modules"),
  path.join(outputRoot, "assets", "css", "admin.css"),
  path.join(outputRoot, "assets", "js", "admin.js"),
];
const dryRun = process.argv.includes("--dry-run");

for (const target of generatedPaths) {
  if (dryRun) {
    console.log(`Would remove ${path.relative(outputRoot, target)}`);
  } else {
    await fs.rm(target, { recursive: true, force: true });
  }
}

console.log(`Admin clean ${dryRun ? "planned" : "completed"}.`);
