export async function compressImage(file, quality = 0.7, maxWidth = 1280) {
  const img = document.createElement("img");
  img.src = URL.createObjectURL(file);

  await new Promise((res) => (img.onload = res));

  const scale = Math.min(1, maxWidth / img.width);

  const canvas = document.createElement("canvas");
  canvas.width = img.width * scale;
  canvas.height = img.height * scale;

  const ctx = canvas.getContext("2d");
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

  const blob = await new Promise((res) =>
    canvas.toBlob(res, "image/jpeg", quality),
  );

  return new File([blob], file.name, { type: "image/jpeg" });
}
