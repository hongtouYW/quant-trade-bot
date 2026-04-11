import { useRouter } from "next/router";
import { clearAllCookie, getCookie } from "./cookie";
import { COOKIE_NAMES, LOCAL_STORAGE_NAMES } from "@/constants/cookies";
import moment from "moment/moment";

export function maskPhone(fullNumber) {
  if (!fullNumber) return "";
  // Keep +CC visible, hide the middle, show last 4
  const ccMatch = fullNumber.match(/^\+\d{1,3}/); // e.g. +60
  const cc = ccMatch ? ccMatch[0] : "";
  const rest = fullNumber.slice(cc.length);

  if (rest.length <= 4) return fullNumber;

  return cc + " *** *** " + rest.slice(-4);
}

export function maskPhoneCenter(raw) {
  if (!raw) return "";

  const digits = String(raw).replace(/\s+/g, "");
  if (digits.length <= 7) {
    // too short, just return partially masked
    return (
      digits[0] + "*".repeat(Math.max(0, digits.length - 2)) + digits.slice(-1)
    );
  }

  const prefixLen = digits.startsWith("+") ? 4 : 3; // show +CCx or first 3 digits
  const prefix = digits.slice(0, prefixLen);
  const suffix = digits.slice(-4);

  return `${prefix} **** ${suffix}`;
}

export function getTokenFromCookie() {
  if (typeof document === "undefined") return null;
  // ✅ always read using your configured cookie key
  const token = getCookie(COOKIE_NAMES.BEARER_TOKEN);
  if (!token || token === "undefined" || token === "null") return null;
  return token;
}
export function extractError(err) {
  // 🟡 1. Non-API errors (network, JS exception, axios)
  if (!err || typeof err !== "object" || !("data" in err)) {
    const msg =
      err?.message || err?.toString?.() || "Unexpected error occurred";

    return {
      type: "exception",
      message: msg,
    };
  }

  // 🟡 2. API error (RTK Query error format)
  const data = err.data || {};
  const resp = data.response || {};

  // Collect possible message strings
  const rawParts = [
    typeof data.error === "string" ? data.error : null,
    typeof resp.error === "string" ? resp.error : null,
    typeof data.message === "string" ? data.message : null,
    typeof resp.message === "string" ? resp.message : null,
  ]
    .filter(Boolean)
    .map((x) => x.trim());

  const parts = [...new Set(rawParts)];
  const finalMessage = parts.length > 0 ? parts.join("\n") : "Unknown error";

  // 🟡 3. Validation error (422)
  if (
    data.error &&
    typeof data.error === "object" &&
    !Array.isArray(data.error)
  ) {
    const fieldErrors = {};
    for (const [field, msgs] of Object.entries(data.error)) {
      fieldErrors[field] = Array.isArray(msgs) ? msgs[0] : String(msgs);
    }

    return {
      type: "validation",
      fieldErrors,
      message: finalMessage,
    };
  }

  // 🟡 4. Generic API error
  return {
    type: "generic",
    message: finalMessage,
  };
}

export function delayedRedirect(router, path, delay = 500) {
  // router: useRouter() instance
  // path:   target route
  // delay:  milliseconds (default 2s)

  setTimeout(() => {
    router.replace(path);
  }, delay);
}

export function triggerLogout() {
  // // Clear all cookies
  // clearAllCookie();
  // // Clear all localStorage
  // localStorage.clear();
  // Redirect to login
  // if (typeof window !== "undefined") {
  //   window.location.href = "/login";
  // }
}

export const passwordRegex = /^.{6,16}$/;

/**
 * Get member info from cookie
 * @returns {Object} parsed member info with safe defaults
 */
export function getMemberInfo() {
  if (typeof document === "undefined") {
    return {
      member_id: null,
      member_login: null,
      member_name: null,
      phone: null,
    };
  }

  const match = document.cookie.match(
    new RegExp(`(?:^|; )member_info=([^;]+)`), // adjust if using COOKIE_NAMES
  );
  if (!match) {
    return {
      member_id: null,
      member_login: null,
      member_name: null,
      phone: null,
    };
  }

  try {
    return JSON.parse(decodeURIComponent(match[1]));
  } catch (e) {
    console.error("Failed to parse member_info cookie", e);
    return {
      member_id: null,
      member_login: null,
      member_name: null,
      phone: null,
    };
  }
}

export function isValidEmail(s) {
  // Simple, robust check: no spaces, 1 "@", at least one dot in domain, 2+ TLD chars
  if (!s) return false;
  const email = String(s).trim();
  if (/\s/.test(email)) return false;
  const parts = email.split("@");
  if (parts.length !== 2) return false;
  const [user, domain] = parts;
  if (!user || !domain) return false;
  if (!domain.includes(".")) return false;
  const tld = domain.split(".").pop();
  if (!tld || tld.length < 2) return false;
  return (
    /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+$/.test(user) &&
    /^[A-Za-z0-9.-]+$/.test(domain)
  );
}

export const calcPoints = (credit) => {
  const num = parseFloat(credit) || 0;
  return num.toFixed(2); // credit → points
};

export const calcCredit = (points) => {
  const num = parseFloat(points) || 0;
  return num.toFixed(2); // points → credit
};

// ATM style input handler
export const handleATMInput = (e, value, setValue, syncOther) => {
  const update = (digits) => {
    const val = (parseInt(digits || "0", 10) / 100).toFixed(2);
    setValue(val);
    syncOther(val);
  };

  // -------------------------------
  // 1️⃣ MOBILE FIRST: beforeInput
  // -------------------------------
  if (e.nativeEvent?.inputType === "insertText") {
    e.preventDefault();
    const input = e.nativeEvent.data;
    if (!/^[0-9]$/.test(input)) return; // allow only digits

    const digits = value.replace(/\D/g, "") + input;
    return update(digits);
  }

  // -------------------------------
  // 2️⃣ KEYBOARD INPUT (Desktop + Some Android)
  // -------------------------------
  if (/^[0-9]$/.test(e.key)) {
    e.preventDefault();
    const digits = value.replace(/\D/g, "") + e.key;
    return update(digits);
  }

  // -------------------------------
  // 3️⃣ BACKSPACE (all platforms)
  // -------------------------------
  if (
    e.key === "Backspace" ||
    e.nativeEvent?.inputType === "deleteContentBackward"
  ) {
    e.preventDefault();
    const digits = value.replace(/\D/g, "").slice(0, -1);
    return update(digits);
  }

  // Block everything else
  e.preventDefault();
};

export function isFullHttpUrl(str) {
  return /^https?:\/\//i.test(str);
}

export async function getClientIp() {
  try {
    const res = await fetch("https://www.cloudflare.com/cdn-cgi/trace", {
      cache: "no-store",
    });

    if (!res.ok) throw new Error("Failed Cloudflare trace");

    const text = await res.text();

    const ip = text.match(/ip=([^\n]+)/)?.[1];

    return ip || "unknown";
  } catch (err) {
    // fallback to ipify (CORS OK)
    try {
      const r = await fetch("https://api.ipify.org?format=json", {
        cache: "no-store",
      });
      const d = await r.json();
      return d.ip || "unknown";
    } catch (_) {
      return "unknown";
    }
  }
}

export const PASSWORD_RULE = {
  REGEX: /^.{6,}$/, // ✅ any character, at least 6 characters long
  MESSAGE_KEY: "pwd.errors.format",
};
/**
 * Check if a password meets the global rule
 * @param {string} password - password to check
 * @returns {boolean} true if valid, false otherwise
 */
export async function PasswordChecking(password) {
  return PASSWORD_RULE.REGEX.test(password);
}

export function isUnauthorized(msg) {
  return msg.includes("Unauthenticated") || msg.includes("无权访问");
}

export const fixUrl = (url) => {
  if (!url) return "";

  let u = url.trim();

  // Fix double colon: https::// → https://
  u = u.replace(/^https::\/\//i, "https://");

  // Fix missing protocol: support.xxx.com → https://support.xxx.com
  if (!u.startsWith("http://") && !u.startsWith("https://")) {
    u = "https://" + u;
  }

  return u;
};

export function formatDate(raw) {
  if (!raw) return "";

  const d = moment(raw);
  if (!d.isValid()) return "";

  return d.format("DD MMM YYYY, HH:mm A");
}

export const getUserLevel = (defaultValue = 0) => {
  // 1. Check if the code is running in a client environment where window and localStorage exist.
  if (typeof window === "undefined" || !window.localStorage) {
    // During server-side rendering (SSR), this returns the default value (0).
    return defaultValue;
  }

  try {
    const vipString = localStorage.getItem(LOCAL_STORAGE_NAMES.LEVEL);

    if (!vipString) {
      return defaultValue;
    }

    const vipNumber = Number(vipString);

    if (!isNaN(vipNumber)) {
      return vipNumber;
    }

    return defaultValue;
  } catch (error) {
    return defaultValue;
  }
};

const KNOWN_DIAL_CODES = ["1111", "111", "60", "1"];

export function formatPhonePretty(raw) {
  if (!raw) return "";

  if (/[a-zA-Z]/.test(String(raw))) {
    return String(raw);
  }

  const d = String(raw).replace(/\D/g, "");
  if (!d) return "";

  // 1) New world: use stored country if exists
  try {
    const saved = localStorage.getItem("selected_phone_country");
    if (saved) {
      const { dial } = JSON.parse(saved);
      const dialDigits = dial?.replace("+", "");

      if (dialDigits && d.startsWith(dialDigits)) {
        const local = d.slice(dialDigits.length);
        return formatByDial(dialDigits, local);
      }
    }
  } catch {
    // ignore localStorage / JSON errors
  }

  // 2) Old world: whitelist guessing
  const dial = KNOWN_DIAL_CODES.find((code) => d.startsWith(code));

  if (dial) {
    const local = d.slice(dial.length);
    return formatByDial(dial, local);
  }

  // 3) No match at all → generic spacing fallback
  return formatGeneric(d);
}

export function maskPhoneCompact(raw) {
  if (!raw) return "";

  if (/[a-zA-Z]/.test(String(raw))) {
    return String(raw);
  }

  const digits = String(raw).replace(/\D/g, "");
  if (!digits) return "";

  const dial = KNOWN_DIAL_CODES.find((code) => digits.startsWith(code));
  const suffix = digits.slice(-4);

  if (!suffix) return `+${digits}`;

  if (dial) {
    return `+${dial}xxxx${suffix}`;
  }

  if (digits.length <= 4) {
    return `+${digits}`;
  }

  return `+${digits.slice(0, Math.min(3, digits.length - 4))}xxxx${suffix}`;
}

// -----------------------------
// Dial-specific formatting
// -----------------------------
function formatByDial(dial, local) {
  // Malaysia: +60 xx xxxx xxxx
  if (dial === "60") {
    return `+60 ${local.slice(0, 2)} ${local.slice(2, 6)} ${local.slice(6)}`;
  }

  // US/CA: +1 xxx xxx xxxx
  if (dial === "1") {
    return `+1 ${local.slice(0, 3)} ${local.slice(3, 6)} ${local.slice(6)}`;
  }

  // +111 xxxx xxxx
  if (dial === "111") {
    return `+111 ${local.slice(0, 4)} ${local.slice(4)}`;
  }

  // +1111 xxxx xxxx
  if (dial === "1111") {
    return `+1111 ${local.slice(0, 4)} ${local.slice(4)}`;
  }

  // Safety fallback (should not hit)
  return `+${dial} ${local}`;
}

// -----------------------------
// Generic spacing fallback
// -----------------------------
function formatGeneric(d) {
  // Always show spaces, best-effort
  // Try to make it look like: +XXX XXXX XXXX

  if (d.length <= 7) {
    return `+${d}`;
  }

  if (d.length <= 10) {
    // +XXX XXXXXXX
    return `+${d.slice(0, 3)} ${d.slice(3)}`;
  }

  if (d.length <= 13) {
    // +XXX XXXX XXXX
    return `+${d.slice(0, 3)} ${d.slice(3, 7)} ${d.slice(7)}`;
  }

  // Very long numbers: +XXX XXXX XXXX XXXX
  return `+${d.slice(0, 3)} ${d.slice(3, 7)} ${d.slice(7, 11)} ${d.slice(11)}`;
}

export function getDeviceMeta() {
  if (typeof window === "undefined") {
    return {};
  }

  const ua = navigator.userAgent; // ✅ exactly what you want
  const lang = navigator.language;
  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  let device_id = localStorage.getItem("device_id");

  if (!device_id) {
    device_id = crypto.randomUUID().replace(/-/g, "").substring(0, 16);
    localStorage.setItem("device_id", device_id);
  }
  const platform =
    navigator.userAgentData?.platform ||
    (ua.includes("iPhone") && "iOS") ||
    (ua.includes("Android") && "Android") ||
    (ua.includes("Windows") && "Windows") ||
    (ua.includes("Mac") && "Mac") ||
    (ua.includes("Linux") && "Linux") ||
    "Unknown";

  return {
    ua, // <-- exact UA string from browser
    platform,
    lang,
    tz,
    device_id,
  };
}
