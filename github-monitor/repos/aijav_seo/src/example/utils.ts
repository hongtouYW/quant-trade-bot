import CryptoJS from "crypto-js";
import dayjs from "dayjs";
import Cookies from "universal-cookie";
import { INSNAME, THEME_COLOR, TOKEN_NAME } from "./constant";

const cookies = new Cookies();

class Util {
  key: string;
  iv: string;
  sign_key: string;
  suffix: string;
  secretKey: string;

  constructor() {
    this.key = import.meta.env.VITE_AES_KEY;
    this.iv = import.meta.env.VITE_AES_IV_BASE;
    this.sign_key = import.meta.env.VITE_SIGNATURE_KEY;
    this.suffix = "NWSdef";
    this.secretKey = import.meta.env.VITE_SECRET_KEY;
  }

  encrypt = (data: string, suffix = this.suffix) => {
    let new_key: any = this.key;
    let new_iv: any = this.iv;

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

  decrypt = (data: string, suffix: string = this.suffix) => {
    let new_key: any = this.key;
    let new_iv: any = this.iv;
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

  base64Sign = (data: any) => {
    data = this.objKeySort(data);
    let pre_sign = "";
    for (const i in data) {
      pre_sign += i + "=" + data[i] + "&";
    }
    pre_sign += this.sign_key;
    return CryptoJS.MD5(pre_sign).toString();
  };

  objKeySort = function (arys: any) {
    const newObj: any = {};
    const newkey = Object.keys(arys).sort();
    for (let i = 0; i < newkey.length; i++) {
      newObj[newkey[i]] = arys[newkey[i]];
    }
    return newObj;
  };

  arrayToChunk = (array: any[], chunkSize: number) => {
    const newArray = [];
    for (let i = 0; i < array.length; i += chunkSize) {
      const chunk = array.slice(i, i + chunkSize);
      newArray.push(chunk);
    }

    return newArray;
  };

  isMobile = () => {
    return window.matchMedia("only screen and (max-width: 700px)").matches;
  };

  setTokens = (
    tokenName: string,
    tokenValue: string,
    expiresInDays: number
  ) => {
    const currentWindowLocation = window.location.hostname;
    const domain = u.getMaindomain(currentWindowLocation);

    // const expiredTime = dayjs(timestamp * 1000).format("YYYY-MM-DD HH:mm:ss");
    const timestamp1 = new Date().getTime();
    const expire = timestamp1 + 60 * 60 * 24 * 1000 * expiresInDays;
    const expireDate = new Date(expire);

    cookies.set(tokenName, tokenValue, {
      domain,
      path: "/",
      expires: expireDate,
    });
    // localStorage.setItem("token_name", tokenName);
  };

  setCookies = (
    name: string,
    value: string,
    expiresInDays: number,
    shareState: boolean = false
  ) => {
    const exp = dayjs()
      .add(expiresInDays, "days")
      .format("YYYY-MM-DD HH:mm:ss");
    const currentWindowLocation = window.location.hostname;
    const domain = u.getMaindomain(currentWindowLocation);

    if (shareState) {
      cookies.set(name, value, {
        domain,
        path: "/",
        expires: new Date(exp),
      });
    } else {
      cookies.set(name, value, {
        path: "/",
        expires: new Date(exp),
      });
    }
  };

  removeTokens = () => {
    const token = cookies.get(TOKEN_NAME);
    const currentWindowLocation = window.location.hostname;
    const domain = u.getMaindomain(currentWindowLocation);

    if (token) {
      cookies.remove(TOKEN_NAME, { domain, path: "/" });
    }
  };

  setLocalItemExpires(key: string, values: string, expires: any) {
    const obj = {
      values,
      expires,
    };

    localStorage.setItem(key, JSON.stringify(obj));
  }

  timestampToDate(timestamp: any) {
    if (timestamp) {
      return dayjs(parseInt(timestamp) * 1000).format("YYYY-MM-DD HH:mm:ss");
    }
  }

  dateToTimestamp(date: any) {
    if (date) {
      return new Date(date).getTime();
    }
  }

  fetchData = async (url: string) => {
    try {
      const response = await fetch(url);
      const data = await response.text();
      return data;
    } catch {
      return "";
    }
  };

  imgDecrypt = (data: any) => {
    try {
      const asc_key = import.meta.env.VITE_DES_KEY || "jeH3O1VX";
      const base_lv = import.meta.env.VITE_DES_IV || "nHnsU4cX";
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

  saveAsImg = (uri: any, filename: string) => {
    const link = document.createElement("a");
    if (typeof link.download === "string") {
      link.href = uri;
      link.download = filename;
      //Firefox requires the link to be in the body
      document?.body?.appendChild(link);
      //simulate click
      link.click();
      //remove the link when done
      document?.body?.removeChild(link);
    } else {
      window.open(uri);
    }
  };

  getSubdomain = (url: string) => {
    let domain = url;
    if (url.includes("://")) {
      domain = url.split("://")[1];
    }
    const subdomain = domain.split(".")[0];
    return subdomain;
  };

  // getMaindomain1 = (url: string) => {
  //   let domain = url;
  //   if (url.includes("://")) {
  //     domain = url.split("://")[1];
  //   }
  //   const mainDomain = `${domain.split(".")[1]}.com`;
  //   return mainDomain;
  // };

  getMaindomain(hostname: string) {
    // 分割 hostname 并获取主域名
    if (hostname.includes("://")) {
      hostname = hostname.split("://")[1];
    }
    const parts = hostname.split(".");
    // console.log('hostname', hostname)
    if (parts.length > 2) {
      return parts.slice(-2).join(".").replace("/", "");
    }
    return hostname.replace("/", "");
  }

  checkSubdomain = () => {
    const currentWindowLocation = window.location.href;
    const subdomain = u.getSubdomain(currentWindowLocation);

    return subdomain;
  };

  siteType = () => {
    const subdomain = u.checkSubdomain();
    // console.log('subdomain', subdomain)
    switch (subdomain) {
      case INSNAME.WUMA:
        return {
          // vip: "is_vip2",
          // vipEndTime: "wm_end_time",
          theme: THEME_COLOR.PURPLE,
        };
      case INSNAME.DM:
        return {
          // vip: "is_vip3",
          // vipEndTime: "dm_end_time",
          theme: THEME_COLOR.YELLOW,
        };
      case INSNAME["4K"]:
        return {
          // vip: "is_vip4",
          // vipEndTime: "k4_end_time",
          theme: THEME_COLOR.BLUE,
        };
      default:
        return {
          // vip: "is_vip1",
          // vipEndTime: "vip_end_time",
          theme: THEME_COLOR.GREEN,
        };
    }
  };

  addImgKeyParam = (url: string) => {
    const secret_key = this.secretKey;
    const currentTime = Math.floor(Date.now() / 1000); // Current time in seconds
    const storedTime = sessionStorage.getItem("timestamp"); // Get the stored timestamp from sessionStorage

    let time; // Declare the time variable

    // If there is a stored time and 5 minutes haven't passed, reuse the stored time
    if (storedTime && currentTime - parseInt(storedTime) < 300) {
      time = parseInt(storedTime) + 300; // Use the stored timestamp and add 300 seconds
    } else {
      // If no stored time or more than 5 minutes have passed, generate a new timestamp
      time = currentTime + 300; // Add 300 seconds to the current time
      sessionStorage.setItem("timestamp", currentTime.toString()); // Store the new timestamp in sessionStorage
    }

    // let hexTime = time.toString(16); // 转换为十六进制字符串
    // let uri = "/xmmvip/xmmvip/e73172371207066dc2d5ad72f2__289916/e73172371207066dc2d5ad72f2__289916_thumb_5903.jpg"

    // The URI for which we're generating the key
    const uri = url;

    // Concatenate secret_key, uri, and time (decimal) for the MD5 hash
    const paramToAppend = secret_key + uri + time; // 确保这里是十六进制

    const key = CryptoJS.MD5(paramToAppend).toString(); // 使用CryptoJS计算MD5

    return "?wsSecret=" + key + "&wsTime=" + time; // 使用十六进制时间戳
  };

  addImageAuthentication = (url: string) => {
    let imageUrlKey = "https://mig.zzbabylon.com";
    let newSrcValue: any = url;
    if (
      url &&
      (url.includes("https://") || url.includes("http://"))
    ) {
      const convertSrcValue = new URL(url);
      newSrcValue = convertSrcValue?.pathname;
      imageUrlKey = convertSrcValue?.origin;
    }

    const vidKeyParam = this.addImgKeyParam(newSrcValue);

    return `${imageUrlKey}${newSrcValue}${vidKeyParam}`;
  };
}

const u = new Util();
export default u;
