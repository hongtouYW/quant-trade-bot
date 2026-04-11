// src/services/baseApi.js
import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";
import { decryptEnvelope } from "@/utils/decrypt";
import { getCookie, setCookie, clearAllCookie } from "@/utils/cookie";
import { COOKIE_NAMES } from "@/constants/cookies";
import { toast } from "react-hot-toast";
import { getDeviceMeta } from "@/utils/utility";

const CK = {
  ACCESS: COOKIE_NAMES.BEARER_TOKEN,
  REFRESH: COOKIE_NAMES.REFRESH_TOKEN,
};

function getStoredAccess() {
  return localStorage.getItem(CK.ACCESS) || getCookie(CK.ACCESS) || null;
}
function getStoredRefresh() {
  return localStorage.getItem(CK.REFRESH) || getCookie(CK.REFRESH) || null;
}

const baseQuery = fetchBaseQuery({
  baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL || "",
  prepareHeaders: (headers, { endpoint }) => {
    const token = getStoredAccess();
    if (token) {
      headers.set(
        "Authorization",
        `Bearer ${decodeURIComponent(String(token))}`,
      );
    }

    const locale = getCookie("NEXT_LOCALE");
    headers.set("Accept-Language", locale || "zh");
    headers.set("X-Device-Meta", JSON.stringify(getDeviceMeta()));
    // match by endpoint name instead of URL

    // if (
    //   endpoint === "login" ||
    //   endpoint === "otpVerify" ||
    //   endpoint === "resetPasswordOtp"
    // ) {
    //   headers.set("X-Device-Meta", JSON.stringify(getDeviceMeta()));
    // }

    return headers;
  },
});

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

const isUnauthorizedBody = (data) => {
  if (!data) return false;
  const msg = String(data.error || "");

  return /unauthenticated\.?/i.test(msg);
};

const shouldRefresh = (result) =>
  result?.error?.status !== 200 && isUnauthorizedBody(result?.error?.data);

/* -------------------------- QUEUE -------------------------------- */
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

/* ----------------------- LOGOUT HANDLER --------------------------- */
function handleLogout() {
  if (typeof window !== "undefined") {
    window.dispatchEvent(
      new CustomEvent("AUTH_EVENT", {
        detail: {
          type: "force_logout",
        },
      }),
    );
  }
}

function sendNetworkError() {
  if (typeof window !== "undefined") {
    window.dispatchEvent(
      new CustomEvent("AUTH_EVENT", {
        detail: {
          type: "network_error",
        },
      }),
    );
  }
}

/* ---------------------- MAIN BASEQUERY ---------------------------- */
export const baseQueryWithReauth = async (args, api, extra) => {
  let result = await baseQueryWithDecrypt(args, api, extra);

  if (!shouldRefresh(result)) {
    return result;
  }

  const refreshToken = getStoredRefresh();
  if (!refreshToken) {
    // Try again after a moment (Safari may restore cookie)
    const fromStorage = localStorage.getItem(CK.REFRESH);
    if (fromStorage) return result; // still logged in

    handleLogout(); // real missing → logout
    return result;
  }

  const isInvalidRefreshToken = (data) => {
    if (!data) return false;

    const msg = String(data.message || data.error || "");
    const statusFalse = data.status === false;

    return statusFalse && /(刷新令牌无效|invalid.*refresh.*token)/i.test(msg);
  };

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

      let decrypted;
      let newAccess;
      let newRefresh;

      let attempts = 0;
      const MAX_REFRESH_RETRY = 3;

      while (attempts < MAX_REFRESH_RETRY) {
        attempts++;

        const refreshRes = await baseQuery(refreshArgs, api, extra);
        const encrypted = refreshRes.data || refreshRes.error?.data;
        decrypted = await tryDecrypt(encrypted);

        const msg = decrypted?.message || decrypted?.error;
        const invalid = decrypted?.status === false;

        // ❗ CASE 1 — refresh token invalid → logout immediately
        if (invalid) {
          handleLogout();
          throw new Error("INVALID_REFRESH_TOKEN");
        }

        // Extract values
        const ok = decrypted?.status === true;
        const tok = decrypted?.token || decrypted || {};
        newAccess = tok?.access_token;
        newRefresh = tok?.refresh_token;

        // ⭐ CASE 2 — refresh success → update token, break loop
        if (ok && newAccess) {
          setCookie(CK.ACCESS, newAccess);
          localStorage.setItem(CK.ACCESS, newAccess);
          if (newRefresh) {
            setCookie(CK.REFRESH, newRefresh);
            localStorage.setItem(CK.REFRESH, newRefresh);
          }
          break;
        }

        // ⭐ CASE 3 — network/server issue → retry (max 3)
        await new Promise((r) => setTimeout(r, 200 * attempts));
      }

      // ❌ After retrying 3 times, still no token
      if (!newAccess) {
        sendNetworkError();
        // window.dispatchEvent(
        //   new CustomEvent("REFRESH_FAILED_WARNING", {
        //     detail: { message: "Network error, please try again." },
        //   })
        // );
        // throw new Error("REFRESH_FAILED");
      }

      return newAccess;
    })();

    refreshPromise
      .then(() => flushQueue(null))
      .catch(() => flushQueue(null))
      .finally(() => (refreshPromise = null));

    await refreshPromise;

    // ⭐ Retry listing ONCE (only after success)
    const latestToken = getStoredAccess();
    const retryArgs = {
      ...args,
      headers: {
        ...(args.headers || {}),
        Authorization: `Bearer ${decodeURIComponent(String(latestToken))}`,
      },
    };

    return await baseQueryWithDecrypt(retryArgs, api, extra);
  } catch (err) {
    console.error("Refresh flow failed:", err);
    return result; // return original error (NO loop)
  }
};

export const baseApi = createApi({
  reducerPath: "baseApi",
  baseQuery: baseQueryWithReauth,
  endpoints: () => ({}),
});

export default baseApi;
