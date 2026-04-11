import CryptoJS from "crypto-js";

const SECRET_KEY = process.env.NEXT_PUBLIC_AES_KEY;

if (!SECRET_KEY) {
  throw new Error("Missing AES key env for encryption");
}

export function encryptObject(obj) {
  const json = JSON.stringify(obj);
  return CryptoJS.AES.encrypt(json, SECRET_KEY).toString();
}

export function decryptObject(cipher) {
  const bytes = CryptoJS.AES.decrypt(cipher, SECRET_KEY);
  const decrypted = bytes.toString(CryptoJS.enc.Utf8);
  return JSON.parse(decrypted);
}
