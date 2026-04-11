import CryptoJS from "crypto-js";
import moment from "moment";
import Cookies from "universal-cookie";
import { COOKIE_NAME } from "./constant";

const KEY = "RkxNd1lyOGlFYW9xTUkyTFozQ0pWMXlmSEVvcHdvdnU="; // 加密后的KEY = FLMwYr8iEaoqMI2LZ3CJV1yfHEopwovu
const IV = "bXcybmIxSjk3Vw=="; // 加密后的IV = mw2nb1J97W
const SIGN_KEY = "VWpHRk9CZGZ6UTYwOTA0QjhJMEs2blJNNTlWQ0E0OTI="; // 加密后的SIGN_KEY = UjGFOBdfzQ60904B8I0K6nRM59VCA492
const SUFFIX = 123456;
const defaultLanguage = import.meta.env.VITE_DEFAULT_LANGUAGE;

const cookies = new Cookies();

const objKeySort = (arys: any) => {
  const newObj: any = {};
  const newkey = Object.keys(arys).sort();
  for (let i = 0; i < newkey.length; i++) {
    newObj[newkey[i]] = arys[newkey[i]];
  }
  return newObj;
};

const base64decoder = (Context: any) => {
  const tmp = CryptoJS.enc.Base64.parse(Context); // encryptedWord via Base64.parse()
  return CryptoJS.enc.Utf8.stringify(tmp);
};

export const base64Sign = (data: any) => {
  data = objKeySort(data);
  let pre_sign = "";
  for (const i in data) {
    pre_sign += i + "=" + data[i] + "&";
  }
  const key = base64decoder(SIGN_KEY);

  pre_sign += key;
  return CryptoJS.MD5(pre_sign).toString();
};

export const encrypt = (data: any, suffix = SUFFIX) => {
  let new_key: any = base64decoder(KEY);
  let new_iv: any = base64decoder(IV);

  new_iv = CryptoJS.enc.Utf8.parse(new_iv + suffix);
  new_key = CryptoJS.enc.Utf8.parse(new_key);

  const encrypted = CryptoJS.AES.encrypt(data, new_key, {
    iv: new_iv,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.Pkcs7,
    formatter: CryptoJS.format.OpenSSL,
  });
  return encrypted.toString();
};

export const decrypt = (data: any, suffix = SUFFIX) => {
  let new_key: any = base64decoder(KEY);
  let new_iv: any = base64decoder(IV);

  new_iv = CryptoJS.enc.Utf8.parse(new_iv + suffix);
  new_key = CryptoJS.enc.Utf8.parse(new_key);

  const decrypted = CryptoJS.AES.decrypt(data, new_key, {
    iv: new_iv,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.Pkcs7,
    formatter: CryptoJS.format.OpenSSL,
  });
  return decrypted.toString(CryptoJS.enc.Utf8);
};

export const isMaxSmScreen = () => {
  return window.matchMedia("only screen and (width < 40rem)").matches;
}

export const isMaxMdScreen = () => {
  return window.matchMedia("only screen and (width < 48rem)").matches;
}

export const isMobile = () => {
  return window.matchMedia("only screen and (max-width: 769px)").matches;
};

export const isTablet = () => {
  return window.matchMedia("only screen and (max-width: 1024px)").matches;
};

export const systemLanguage = () => {
  const language = localStorage.getItem("language") || defaultLanguage;
  if (language === "zh-CN") {
    return "zh";
  } else {
    return "en";
  }
};

export const randomIntFromInterval = (min: number, max: number) => {
  return Math.floor(Math.random() * (max - min + 1) + min);
};

export const toFmt = (t: any, format = "YYYY-MM-DD") => {
  // console.log("t", t);
  // console.log('moment(t).format("YYYY-MM-DD")', moment(t).format("YYYY-MM-DD"));
  return t ? moment(t).format(format) : t;
};

export const humanizeNumber = (num: number): string => {
  if (num >= 1_000_000_000) {
    return (num / 1_000_000_000).toFixed(1).replace(/\.0$/, "") + "b";
  }
  if (num >= 1_000_000) {
    return (num / 1_000_000).toFixed(1).replace(/\.0$/, "") + "m";
  }
  if (num >= 1_000) {
    return (num / 1_000).toFixed(1).replace(/\.0$/, "") + "k";
  }
  return "1k"; // force minimum "1k"
};

export const getToken = () => {
  const token = cookies.get(COOKIE_NAME);
  return token || "";
};

export const fetchData = async (url: string) => {
  try {
    const response = await fetch(url);
    const data = await response.text();
    return data;
  } catch {
    return "";
  }
};

export const imgDecrypt = (data: any) => {
  try {
    const asc_key = "jeH3O1VX";
    const base_lv = "nHnsU4cX";
    const tmpiv = CryptoJS.enc.Utf8.parse(base_lv);
    const keyHex = CryptoJS.enc.Utf8.parse(asc_key);
    const decrypted = CryptoJS.DES.decrypt(data, keyHex, {
      iv: tmpiv,
      mode: CryptoJS.mode.CBC,
      padding: CryptoJS.pad.Pkcs7,
      formatter: CryptoJS.format.OpenSSL,
    });
    return decrypted.toString(CryptoJS.enc.Utf8);
  } catch {
    return "";
  }
};

export const formatImgUrl = async (
  domain: string,
  src: string,
  size: boolean = true,
  imageSize = "600x600"
) => {
  const imageUrlKey = domain || "";
  if (
    src &&
    (src.includes("https://") ||
      src.includes("http://") ||
      src.includes("data:image"))
  ) {
    return src;
  }

  if (imageUrlKey !== "" && src !== "") {
    let encryptUrls = `${imageUrlKey}/${src}.txt`;

    if (size && !encryptUrls.includes("?size=")) {
      encryptUrls = `${encryptUrls}?size=${imageSize}`;
    }

    const res = await fetchData(encryptUrls);
    // console.log("res", res);
    let __decrypted = "";
    if (res) {
      __decrypted = res.indexOf("data") >= 0 ? res : imgDecrypt(res);
      return __decrypted;
    }
  }
};
// export const scrollToImage = (id: string) => {
//   const el = document.getElementById(`image-${id}`);
//   if (!el) return;

//   // Find nearest scrollable parent
//   const parent = el.parentElement;
//   if (!parent) return;

//   parent.scrollTop = el.offsetTop; // 👈 instant jump
// };
