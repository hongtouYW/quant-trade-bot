import * as CryptoJS from "crypto-js";

const AES_KEY = CryptoJS.enc.Utf8.parse("0XxdjmI55ZjjqQLO3nI7gGqrBP0Vz9jS");
const IV_BASE = "RWf23muavY";
const SIGNATURE_KEY = "NRkw0g3iJLDvw5tJ5PuVt5276z0SOuyL";

function generateRandomIVSuffix(): string {
  const chars =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  let result = "";
  for (let i = 0; i < 6; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

function generateEncodeSign(payload: Record<string, unknown>): string {
  const sortedKeys = Object.keys(payload).sort();
  const queryString = sortedKeys
    .map((key) => `${key}=${payload[key]}`)
    .join("&");
  const fullString = queryString + SIGNATURE_KEY;
  return CryptoJS.MD5(fullString).toString();
}

export function encryptPayload(payload: Record<string, unknown>): {
  encrypted: string;
  ivSuffix: string;
} {
  const payloadWithSign = {
    ...payload,
    encode_sign: generateEncodeSign(payload),
  };

  const jsonStr = JSON.stringify(payloadWithSign);
  const ivSuffix = generateRandomIVSuffix();
  const fullIV = CryptoJS.enc.Utf8.parse(IV_BASE + ivSuffix);

  const encrypted = CryptoJS.AES.encrypt(jsonStr, AES_KEY, {
    iv: fullIV,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.Pkcs7,
  }).ciphertext.toString(CryptoJS.enc.Base64);

  return { encrypted, ivSuffix };
}

// export function decryptPayload(
//   encryptedBase64: string,
//   ivSuffix: string,
// ): Record<string, any> {
//   const fullIV = CryptoJS.enc.Utf8.parse(IV_BASE + ivSuffix);
//   const decrypted = CryptoJS.AES.decrypt(
//     { ciphertext: CryptoJS.enc.Base64.parse(encryptedBase64) },
//     AES_KEY,
//     {
//       iv: fullIV,
//       mode: CryptoJS.mode.CBC,
//       padding: CryptoJS.pad.Pkcs7,
//     },
//   ).toString(CryptoJS.enc.Utf8);
//
//   return JSON.parse(decrypted);
// }
