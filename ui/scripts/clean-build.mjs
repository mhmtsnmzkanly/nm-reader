import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const outputDirectory = path.resolve(scriptDirectory, "../../public");
const assetsDirectory = path.join(outputDirectory, "assets");
const latestPath = path.join(outputDirectory, "latest.txt");
const dryRun = process.argv.includes("--dry-run");

if (!fs.existsSync(latestPath)) {
  throw new Error(`Build inventory not found: ${latestPath}. Run npm run build first.`);
}

const normalizeRelativePath = (value) => value.replaceAll("\\", "/").replace(/^\.\//, "");
const inventory = fs.readFileSync(latestPath, "utf8")
  .split(/\r?\n/)
  .map((line) => normalizeRelativePath(line.trim()))
  .filter(Boolean);

for (const relativeFile of inventory) {
  if (path.isAbsolute(relativeFile) || relativeFile.split("/").includes("..")) {
    throw new Error(`Unsafe path in latest.txt: ${relativeFile}`);
  }
}

const currentBuildFiles = new Set(inventory);
for (const requiredFile of ["app.html", "assets/build-manifest.json", "latest.txt"]) {
  if (!currentBuildFiles.has(requiredFile)) {
    throw new Error(`Invalid latest.txt: missing ${requiredFile}`);
  }
}

const buildHash = /^[A-Za-z0-9_-]{8}$/;
const legacyHashedFile = /^.+-[A-Za-z0-9_-]{8}\.(?:js|css|woff2?|eot|ttf|otf|png|jpe?g|svg|gif|webp|ico)$/i;
const managedAssetTypes = new Set(["js", "css", "fonts", "images"]);

const isManagedBuildArtifact = (relativeFile) => {
  const segments = relativeFile.split("/");
  if (segments[0] !== "assets") return false;

  if (segments.length >= 3 && managedAssetTypes.has(segments[1]) && buildHash.test(segments[2])) {
    return true;
  }

  if (segments.length >= 2 && buildHash.test(segments[1])) {
    return true;
  }

  return segments.length === 3
    && managedAssetTypes.has(segments[1])
    && legacyHashedFile.test(segments[2]);
};

const files = [];
const visit = (directory) => {
  if (!fs.existsSync(directory)) return;
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const absolutePath = path.join(directory, entry.name);
    if (entry.isDirectory()) visit(absolutePath);
    else if (entry.isFile()) files.push(absolutePath);
  }
};
visit(assetsDirectory);

const staleFiles = files
  .map((absolutePath) => ({
    absolutePath,
    relativeFile: normalizeRelativePath(path.relative(outputDirectory, absolutePath)),
  }))
  .filter(({ relativeFile }) => {
    return !currentBuildFiles.has(relativeFile) && isManagedBuildArtifact(relativeFile);
  })
  .sort((left, right) => left.relativeFile.localeCompare(right.relativeFile));

for (const { absolutePath } of staleFiles) {
  if (!dryRun) fs.rmSync(absolutePath);
}

const removeEmptyDirectories = (directory) => {
  if (!fs.existsSync(directory)) return;
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    if (entry.isDirectory()) removeEmptyDirectories(path.join(directory, entry.name));
  }
  if (directory !== assetsDirectory && fs.readdirSync(directory).length === 0 && !dryRun) {
    fs.rmdirSync(directory);
  }
};
removeEmptyDirectories(assetsDirectory);

const action = dryRun ? "would remove" : "removed";
console.log(`Build cleanup ${action} ${staleFiles.length} stale file(s).`);
for (const { relativeFile } of staleFiles) console.log(`- ${relativeFile}`);
