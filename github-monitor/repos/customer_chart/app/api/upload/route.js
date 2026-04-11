import { writeFile, mkdir } from "fs/promises";
import path from "path";

export async function POST(req) {
  try {
    const form = await req.formData();
    const file = form.get("file");

    if (!file) {
      return Response.json({ error: "No file uploaded" }, { status: 400 });
    }

    // ---------- FILE BUFFER ----------
    const bytes = await file.arrayBuffer();
    const buffer = Buffer.from(bytes);

    // ---------- SANITIZE FILENAME ----------
    const originalName = file.name || "file";
    const ext = originalName.includes(".")
      ? "." + originalName.split(".").pop()
      : "";

    const base = originalName.replace(/\.[^/.]+$/, "");
    const slug = base.replace(/[^a-zA-Z0-9-_]/g, "_");

    const fileName = `${Date.now()}-${slug}${ext}`;

    // ---------- RESOLVE UPLOAD DIR ----------
    const rawDir = process.env.UPLOAD_DIR || "public/uploads";

    const uploadDir = rawDir.startsWith("/")
      ? rawDir
      : path.join(process.cwd(), rawDir);

    await mkdir(uploadDir, { recursive: true });

    const filePath = path.join(uploadDir, fileName);

    // ---------- WRITE FILE ----------
    await writeFile(filePath, buffer);

    // ---------- RETURN URL ----------
    return Response.json({
      status: true,
      url: `/uploads/${fileName}`,
    });
  } catch (err) {
    console.error("Upload error:", err);
    return Response.json(
      { status: false, error: "Upload failed" },
      { status: 500 }
    );
  }
}
