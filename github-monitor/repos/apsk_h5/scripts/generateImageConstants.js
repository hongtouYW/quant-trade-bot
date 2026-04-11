// scripts/generateImageConstants.js
const fs = require("fs");
const path = require("path");

function walkDir(dir) {
  return (
    fs
      .readdirSync(dir, { withFileTypes: true })
      // ← filter out Mac/.DS_Store and any hidden files
      .filter((ent) => !ent.name.startsWith("."))
      .reduce((acc, ent) => {
        const fullPath = path.join(dir, ent.name);
        const relPath = path
          .relative(path.join(__dirname, "..", "public", "images"), fullPath)
          .replace(/\\/g, "/");
        const key = ent.name
          .replace(/\.[^/.]+$/, "")
          .replace(/[-_\s]+(.)/g, (_, c) => c.toUpperCase())
          .replace(/^(.)/, (_, c) => c.toLowerCase());

        if (ent.isDirectory()) {
          acc[key] = walkDir(fullPath);
        } else {
          acc[key] = `/images/${relPath}`;
        }
        return acc;
      }, {})
  );
}

const imagesDir = path.join(__dirname, "..", "public", "images");
const tree = walkDir(imagesDir);

const outFile = path.join(__dirname, "..", "src", "constants", "images.js");
const contents =
  `// AUTO-GENERATED — do NOT edit by hand\n` +
  `export const IMAGES = ${JSON.stringify(tree, null, 2)};\n`;

fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, contents, "utf-8");
console.log("✅ images.js generated at", outFile);
