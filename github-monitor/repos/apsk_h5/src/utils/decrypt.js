// utils/decrypt.js
const te = new TextEncoder();
const td = new TextDecoder();

function b64ToU8(b64) {
  const bin = atob(b64);
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}

/** Decrypts {data: base64, iv: "xxxxxx"} using AES-256-CBC.
 * Needs env:
 *  - NEXT_PUBLIC_AES_KEY (32 chars)
 *  - NEXT_PUBLIC_IV_BASE (10 chars)
 */
export async function decryptEnvelope({ data, iv }) {
  const AES_KEY = process.env.NEXT_PUBLIC_AES_KEY || process.env.AES_KEY;
  const IV_BASE = process.env.NEXT_PUBLIC_IV_BASE || process.env.IV_BASE;

  if (!AES_KEY || AES_KEY.length !== 32)
    throw new Error("AES_KEY must be 32 chars");
  if (!IV_BASE || IV_BASE.length !== 10)
    throw new Error("IV_BASE must be 10 chars");
  if (!data || !iv) throw new Error("Missing data/iv");

  const fullIV = IV_BASE + iv; // 10 + 6 = 16 chars
  const key = await crypto.subtle.importKey(
    "raw",
    te.encode(AES_KEY),
    { name: "AES-CBC" },
    false,
    ["decrypt"]
  );
  const plainBuf = await crypto.subtle.decrypt(
    { name: "AES-CBC", iv: te.encode(fullIV) },
    key,
    b64ToU8(data)
  );
  return td.decode(plainBuf); // usually JSON string
}
