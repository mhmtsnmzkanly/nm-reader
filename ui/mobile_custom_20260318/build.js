#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..');
const srcDir = path.resolve(__dirname);
const distDir = path.resolve(root, 'public', 'mobile');

const files = ['index.html', 'app.js', 'app.css'];

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function copyFile(name) {
  const src = path.join(srcDir, name);
  const dest = path.join(distDir, name);
  fs.copyFileSync(src, dest);
}

function main() {
  ensureDir(distDir);
  files.forEach(copyFile);
  console.log(`Mobile build complete: ${distDir}`);
}

main();
