import fs from "node:fs/promises";
import path from "node:path";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";

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

const html = await fs.readFile(path.join(adminRoot, "admin.html"), "utf8");
if (!html.includes("window.__NMR_CONTEXT") || !html.includes("<template")) {
  throw new Error("Admin HTML shell is missing its context or templates.");
}
await fs.readFile(path.join(adminRoot, "admin.css"), "utf8");
console.log(`Admin lint passed: ${javascriptFiles.length} JavaScript files.`);
