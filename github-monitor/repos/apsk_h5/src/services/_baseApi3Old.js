// src/services/baseApi.js
import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";
import { decryptEnvelope } from "@/utils/decrypt";
import { getCookie, setCookie, clearAllCookie } from "@/utils/cookie";
import { getTokenFromCookie } from "@/utils/utility";
import { COOKIE_NAMES } from "@/constants/cookies";

/* -------------------- Cookie Keys -------------------- */
const CK = {
  ACCESS: COOKIE_NAMES.BEARER_TOKEN,
  REFRESH: COOKIE_NAMES.REFRESH_TOKEN,
};

/* -------------------- Base Query -------------------- */
const baseQuery = fetchBaseQuery({
  baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL || "",
  prepareHeaders: (headers, { endpoint, body }) => {
    const token = getTokenFromCookie();
    if (token) {
      headers.set(
        "Authorization",
        `Bearer ${decodeURIComponent(String(token))}`
      );
    }

    const locale = getCookie("NEXT_LOCALE");
    headers.set("Accept-Language", locale || "zh");

    // 🧩 Detect FormData to avoid forcing JSON
    if (!(body instanceof FormData)) {
      headers.set("Content-Type", "application/json");
    }

    return headers;
  },
});

/* -------------------- Decrypt Helper -------------------- */
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

/* -------------------- Base Query with Decrypt -------------------- */
export const baseQueryWithDecrypt = async (args, api, extra) => {
  const res = await baseQuery(args, api, extra);

  // 🔸 Error path
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

  // 🔸 Success path
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

/* -------------------- Auth Detection -------------------- */
const isUnauthorizedBody = (data) => {
  if (!data) return false;
  const msg = String(data?.message || data?.error || "");
  return /unauth|无权访问|invalid.?token/i.test(msg);
};

const shouldRefresh = (result) =>
  (result?.error?.status === 400 || result?.error?.status === 401) &&
  isUnauthorizedBody(result?.error?.data);

/* ============================================================
   🔒 REFRESH LOCK + RETRY SAFE IMPLEMENTATION
   ============================================================ */
let refreshPromise = null;

export const baseQueryWithReauth = async (args, api, extra) => {
  let result = await baseQueryWithDecrypt(args, api, extra);

  if (shouldRefresh(result)) {
    const refreshToken = getCookie(CK.REFRESH);

    if (!refreshToken) {
      clearAllCookie();
      if (typeof window !== "undefined") window.location.href = "/login";
      return result;
    }

    try {
      // ✅ Only one refresh request at a time
      if (!refreshPromise) {
        refreshPromise = (async () => {
          const refreshArgs = {
            url: "/api/member/refresh",
            method: "POST",
            body: { refresh_token: refreshToken },
          };

          const refreshRes = await baseQuery(refreshArgs, api, extra);

          // 🧩 Try decrypting refresh response
          const decrypted = await tryDecrypt(refreshRes.data);
          const ok = decrypted?.status === true;
          const tok = decrypted?.token || decrypted || {};
          const newAccess = tok?.access_token;
          const newRefresh = tok?.refresh_token;

          if (ok && newAccess) {
            setCookie(CK.ACCESS, newAccess);
            if (newRefresh) setCookie(CK.REFRESH, newRefresh);
            // Give time for cookie to persist
            await new Promise((r) => setTimeout(r, 300));
            return newAccess;
          }

          throw new Error("Refresh failed");
        })();

        refreshPromise.finally(() => {
          refreshPromise = null;
        });
      }

      // 🕒 Wait for refresh to finish
      await refreshPromise;

      // 🔁 Retry original request with latest token
      const latestToken = getTokenFromCookie();
      const retryArgs = {
        ...args,
        headers: {
          ...(args.headers || {}),
          Authorization: `Bearer ${decodeURIComponent(String(latestToken))}`,
        },
      };

      result = await baseQueryWithDecrypt(retryArgs, api, extra);

      // 🩹 Optional: retry again if cookie delay still caused invalid token
      if (shouldRefresh(result)) {
        await new Promise((r) => setTimeout(r, 300));
        result = await baseQueryWithDecrypt(retryArgs, api, extra);
      }
    } catch (err) {
      console.error("🔴 Refresh or retry failed:", err);
      clearAllCookie();
      if (typeof window !== "undefined") window.location.href = "/login";
      return {
        error: { status: "REFRESH_FAIL", data: "Reauthentication failed" },
      };
    }
  }

  return result;
};

/* -------------------- Create API -------------------- */
export const baseApi = createApi({
  reducerPath: "baseApi",
  baseQuery: baseQueryWithReauth,
  endpoints: () => ({}),
});

export default baseApi;
