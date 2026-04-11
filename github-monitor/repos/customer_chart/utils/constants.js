export const WUKONG_API_BASE = process.env.NEXT_PUBLIC_WUKONG_API_BASE;

export const WUKONG_WS_ADDR = process.env.NEXT_PUBLIC_WUKONG_WS_ADDR;

export const PLATFORM_UID = process.env.NEXT_PUBLIC_PLATFORM_UID;

export const AGENT_TOKEN = process.env.NEXT_PUBLIC_AGENT_TOKEN;

export const APSKY_API = process.env.NEXT_PUBLIC_ABP_API_BASE;

export const wukongState = {
  connected: false,
  everConnected: false,
};

export const wukongSingleton = {
  inited: false,
};

// ✅ Safe getter (only runs in browser)
export function getBrowserInfo() {
  if (typeof window === "undefined" || typeof navigator === "undefined") {
    return null;
  }

  const ua = navigator.userAgent;

  const info = {
    ua,

    browser: (() => {
      if (ua.includes("Edg")) return "Edge";
      if (ua.includes("Chrome")) return "Chrome";
      if (ua.includes("Safari") && !ua.includes("Chrome")) return "Safari";
      if (ua.includes("Firefox")) return "Firefox";
      return "Unknown";
    })(),

    os: (() => {
      if (ua.includes("Windows")) return "Windows";
      if (ua.includes("Mac OS")) return "macOS";
      if (ua.includes("Android")) return "Android";
      if (ua.includes("iPhone") || ua.includes("iPad")) return "iOS";
      return "Unknown";
    })(),

    device: /Android|iPhone|iPad|iPod|Mobi/i.test(ua) ? "Mobile" : "Desktop",

    language: navigator.language,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    screen: `${window.screen.width}x${window.screen.height}`,
  };

  return JSON.stringify(info);
}

export function getCustomerDisplayId(name) {
  if (!name || typeof name !== "string") return "";

  // ⭐ case 1: has colon → take right side
  if (name.includes(":")) {
    const right = name.split(":")[1];
    if (!right) return "";

    // right may still be ap sky_xxx
    if (right.includes("_")) {
      const last = right.split("_").pop();
      return last || "";
    }

    return right;
  }

  // ⭐ case 2: no colon but has underscore
  if (name.includes("_")) {
    const last = name.split("_").pop();
    return last || "";
  }

  // ⭐ fallback
  return name;
}

export const generateTickId = () => {
  return (Date.now() * 10000 + Math.floor(Math.random() * 10000)).toString();
};
