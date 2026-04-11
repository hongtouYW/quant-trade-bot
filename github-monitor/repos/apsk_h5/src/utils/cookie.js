import { COOKIE_NAMES, LOCAL_STORAGE_NAMES } from "@/constants/cookies";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useHomeSideBarStore } from "@/store/zustand/useHomeSideBarStore";

// contant name
export const COOKIE_REFERRAL = "referralCode"; // ✅ clearer and consistent
export const COOKIE_AGENT = "agentCode"; // ✅ good, keep as is
export const COOKIE_SHOP = "shopCode"; // ✅ good, keep as is

export const COOKIE_PATH = "/";

export function getCookie(name) {
  if (typeof document === "undefined") return null; // SSR safe
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]+)`));
  return match ? decodeURIComponent(match[1]) : null;
}

export const DEFAULT_MAX_AGE = 60 * 60 * 24 * 365; // one year

export function setCookie(name, value, maxAgeSec = DEFAULT_MAX_AGE) {
  if (typeof document === "undefined") return; // SSR safe
  document.cookie = [
    `${name}=${encodeURIComponent(value)}`,
    `max-age=${maxAgeSec}`,
    `path=${COOKIE_PATH}`,
  ].join("; ");
}

export function clearCookie(name) {
  if (typeof document === "undefined") return;

  const hostname = window.location.hostname;

  // common domain variants: current + leading dot
  const domains = [hostname, `.${hostname}`];
  // common path variants
  const paths = ["/", ""];

  domains.forEach((domain) => {
    paths.forEach((path) => {
      document.cookie = `${name}=; Max-Age=0; path=${path}; domain=${domain}`;
    });
  });

  // also try without domain (some browsers ignore `domain=`)
  document.cookie = `${name}=; Max-Age=0; path=/`;
}

export function clearAllCookie() {
  // 1️⃣ Clear cookies
  clearCookie(COOKIE_NAMES.BEARER_TOKEN);
  clearCookie(COOKIE_NAMES.REFRESH_TOKEN);
  clearCookie(COOKIE_NAMES.MEMBER_INFO);
  clearCookie(COOKIE_REFERRAL);

  // 2️⃣ Only run browser-side logic in client
  if (typeof window === "undefined") return;

  // 3️⃣ Clear localStorage tokens (VERY IMPORTANT)
  try {
    localStorage.removeItem(COOKIE_NAMES.BEARER_TOKEN);
    localStorage.removeItem(COOKIE_NAMES.REFRESH_TOKEN);
    localStorage.removeItem(COOKIE_NAMES.MEMBER_INFO);
    localStorage.removeItem(LOCAL_STORAGE_NAMES.JUST_LOGIN);
    localStorage.removeItem(LOCAL_STORAGE_NAMES.LOGOUT);
    localStorage.removeItem(LOCAL_STORAGE_NAMES.LEVEL);
  } catch (_) {}

  // 4️⃣ Clear persisted stores
  try {
    localStorage.removeItem("balance-store");
  } catch (_) {}

  // 5️⃣ Reset Zustand stores
  try {
    useHomeSideBarStore.getState()?.reset?.();
  } catch (_) {}

  try {
    useBalanceStore.getState()?.resetBalance?.();
  } catch (_) {}

  try {
    useBalanceStore.getState()?.clearPrevProviderId?.();
  } catch (_) {}

  try {
    useBalanceStore.getState()?.clearPrevGameMemberId?.();
  } catch (_) {}
}

export function updateMemberInfo(partial) {
  try {
    const raw = getCookie(COOKIE_NAMES.MEMBER_INFO);
    let current = {};

    if (raw) {
      try {
        current = JSON.parse(raw);
      } catch {
        current = {};
      }
    }

    const updated = {
      ...current,
      ...partial, // 👈 overwrite only what you pass in
    };

    setCookie(COOKIE_NAMES.MEMBER_INFO, JSON.stringify(updated));
    return updated;
  } catch (err) {
    console.error("Failed to update member info:", err);
    return null;
  }
}
