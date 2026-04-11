// src/services/baseApi.js
import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";
import { decryptEnvelope } from "@/utils/decrypt";
import { getCookie, setCookie, clearAllCookie } from "@/utils/cookie";
import { COOKIE_NAMES } from "@/constants/cookies";
import { toast } from "react-hot-toast";

/* -------------------------------------------------------------
   COOKIE KEYS
------------------------------------------------------------- */
const CK = {
  ACCESS: COOKIE_NAMES.BEARER_TOKEN,
  REFRESH: COOKIE_NAMES.REFRESH_TOKEN,
};

/* -------------------------------------------------------------
   DUAL-STORAGE GETTERS (cookie + localStorage)
------------------------------------------------------------- */
function getStoredAccess() {
  return getCookie(CK.ACCESS) || localStorage.getItem(CK.ACCESS) || null;
}

function getStoredRefresh() {
  return getCookie(CK.REFRESH) || localStorage.getItem(CK.REFRESH) || null;
}

/* -------------------------------------------------------------
   FETCH BASE QUERY
------------------------------------------------------------- */
const baseQuery = fetchBaseQuery({
  baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL || "",
  prepareHeaders: (headers, { body }) => {
    const token = getStoredAccess();

    if (token) {
      headers.set(
        "Authorization",
        `Bearer ${decodeURIComponent(String(token))}`
      );
    }

    const locale = getCookie("NEXT_LOCALE");
    headers.set("Accept-Language", locale || "zh");

    return headers;
  },
});

/* -------------------------------------------------------------
   DECRYPT ENVELOPE HELPER
------------------------------------------------------------- */
async function tryDecrypt(envelope) {
  if (
    envelope &&
    typeof envelope === "object" &&
    "data" in envelope &&
    "iv" in envelope
  ) {
    const plaintext = await decryptEnvelope({
      data: envelope.data,
      iv: envelope.iv,
    });

    try {
      return JSON.parse(plaintext);
    } catch {
      return plaintext;
    }
  }
  return null;
}

/* -------------------------------------------------------------
   BASE QUERY WITH DECRYPT
------------------------------------------------------------- */
export const baseQueryWithDecrypt = async (args, api, extra) => {
  const res = await baseQuery(args, api, extra);

  if (res.error) {
    try {
      const decrypted = await tryDecrypt(res.error.data);
      if (decrypted !== null) {
        return { error: { status: res.error.status, data: decrypted } };
      }
      return res;
    } catch (e) {
      return {
        error: {
          status: "DECRYPT_FAIL",
          data: e?.message || "Decryption failed",
        },
      };
    }
  }

  try {
    const body = res.data;

    if (body && typeof body === "object" && "data" in body && "iv" in body) {
      if ("code" in body && body.code !== 200) {
        const decrypted = await tryDecrypt(body);
        return {
          error: {
            status: body.code,
            data: decrypted ?? body.message ?? "Server error",
          },
        };
      }

      const decrypted = await tryDecrypt(body);
      if (decrypted !== null) return { data: decrypted };
    }

    return res;
  } catch (e) {
    return {
      error: {
        status: "DECRYPT_FAIL",
        data: e?.message || "Decryption failed",
      },
    };
  }
};

/* -------------------------------------------------------------
   AUTH DETECTION
------------------------------------------------------------- */
const isUnauthorizedBody = (data) => {
  if (!data) return false;
  const msg = String(data?.message || data?.error || "");
  return /unauth|invalid.?token|无权访问/i.test(msg);
};

const shouldRefresh = (result) =>
  (result?.error?.status === 400 || result?.error?.status === 401) &&
  isUnauthorizedBody(result?.error?.data);

/* -------------------------------------------------------------
   PENDING REQUEST QUEUE
------------------------------------------------------------- */
let refreshPromise = null;
let pendingQueue = [];

function queueRequest(callback) {
  return new Promise((resolve, reject) => {
    pendingQueue.push({ callback, resolve, reject });
  });
}

function flushQueue(error) {
  pendingQueue.forEach(({ callback, resolve, reject }) => {
    if (error) reject(error);
    else resolve(callback());
  });
  pendingQueue = [];
}

/* -------------------------------------------------------------
   MAIN — BASE QUERY WITH REAUTH
------------------------------------------------------------- */
export const baseQueryWithReauth = async (args, api, extra) => {
  let result = await baseQueryWithDecrypt(args, api, extra);

  if (shouldRefresh(result)) {
    const refreshToken = getStoredRefresh();

    const cookieRef = getCookie(CK.REFRESH);
    const lsRef = localStorage.getItem(CK.REFRESH);

    if (!cookieRef && !lsRef) {
      if (typeof window !== "undefined") {
        toast.error("Session expired. Please log in again.", {
          duration: 6000, // custom duration for this toast only
        });
      }

      clearAllCookie();
      localStorage.clear();

      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }

      return result;
    }

    try {
      if (refreshPromise) {
        return queueRequest(() => baseQueryWithDecrypt(args, api, extra));
      }

      refreshPromise = (async () => {
        const refreshArgs = {
          url: "/api/member/refresh",
          method: "POST",
          body: { refresh_token: refreshToken },
        };

        let refreshRes;
        let decrypted;
        let attempts = 0;
        let success = false;

        while (attempts < 1 && !success) {
          attempts++;

          refreshRes = await baseQuery(refreshArgs, api, extra);

          const encrypted = refreshRes.data || refreshRes.error?.data;
          decrypted = await tryDecrypt(encrypted);

          const msg = decrypted?.message || decrypted?.error;

          const invalid = decrypted?.status === false && msg === "刷新令牌无效";

          // ⭐ MOVED TOKEN HANDLING INSIDE SUCCESS BRANCH
          if (!invalid) {
            const ok = decrypted?.status === true;
            const tok = decrypted?.token || decrypted || {};
            const newAccess = tok?.access_token;
            const newRefresh = tok?.refresh_token;

            if (!ok || !newAccess) {
              await new Promise((r) => setTimeout(r, 150 * attempts));
              continue;
            }

            setCookie(CK.ACCESS, newAccess);
            localStorage.setItem(CK.ACCESS, newAccess);

            if (newRefresh) {
              setCookie(CK.REFRESH, newRefresh);
              localStorage.setItem(CK.REFRESH, newRefresh);
            }

            await new Promise((r) => setTimeout(r, 250));
            success = true;
            break;
          }

          await new Promise((r) => setTimeout(r, 150 * attempts));
        }

        if (!success) {
          // show toast only on client
          if (typeof window !== "undefined") {
            toast.error("Session expired. Please log in again.", {
              duration: 6000, // custom duration for this toast only
            });
          }

          clearAllCookie();
          localStorage.clear();

          if (typeof window !== "undefined") {
            window.location.href = "/login";
          }

          throw new Error("INVALID_REFRESH_TOKEN");
        }

        return decrypted.token?.access_token || decrypted.access_token;
      })();

      refreshPromise
        .then(() => flushQueue(null))
        .catch((err) => flushQueue(err))
        .finally(() => {
          refreshPromise = null;
        });

      await refreshPromise;

      const latestToken = getStoredAccess();
      const retryArgs = {
        ...args,
        headers: {
          ...(args.headers || {}),
          Authorization: `Bearer ${decodeURIComponent(String(latestToken))}`,
        },
      };

      result = await baseQueryWithDecrypt(retryArgs, api, extra);

      if (shouldRefresh(result)) {
        await new Promise((r) => setTimeout(r, 250));
        result = await baseQueryWithDecrypt(retryArgs, api, extra);
      }
    } catch (err) {
      console.error("🔴 Refresh or retry failed:", err);
      return result;
    }
  }

  return result;
};

/* -------------------------------------------------------------
   CREATE API
------------------------------------------------------------- */
export const baseApi = createApi({
  reducerPath: "baseApi",
  baseQuery: baseQueryWithReauth,
  endpoints: () => ({}),
});

export default baseApi;
