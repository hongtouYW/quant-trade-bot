import { fetchBaseQuery } from "@reduxjs/toolkit/query/react";
import { decryptEnvelope } from "@/utils/decrypt"; // <- make sure this exists
import { getCookie } from "@/utils/cookie";
import { getTokenFromCookie } from "@/utils/utility";

const baseQuery = fetchBaseQuery({
  baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL || "",
  prepareHeaders: (headers, { getState }) => {
    // const token2 = getState()?.auth?.token;
    // alert(token2);
    const token = getTokenFromCookie();
    if (token) {
      headers.set("Authorization", `Bearer ${decodeURIComponent(token)}`);
    }

    const locale = getCookie("NEXT_LOCALE");
    if (locale) {
      headers.set("Accept-Language", locale);
    } else headers.set("Accept-Language", "zh");

    headers.set("Content-Type", "application/json");
    return headers;
  },
});

// Small helper to detect & decrypt the envelope
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
      return plaintext; // non-JSON plaintext
    }
  }
  return null; // not an encrypted envelope
}

export const baseQueryWithDecrypt = async (args, api, extra) => {
  const res = await baseQuery(args, api, extra);

  // ========= Non-2xx (error path) =========
  if (res.error) {
    try {
      const decrypted = await tryDecrypt(res.error.data);
      if (decrypted !== null) {
        // keep original status (e.g., 401) but return decrypted body
        return {
          error: {
            status: res.error.status,
            data: decrypted,
          },
        };
      }
      // not encrypted → pass through
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

  // ========= 2xx (success path) =========
  try {
    const body = res.data;

    // If server used the encryption envelope { code, data, iv, message? }
    if (body && typeof body === "object" && "data" in body && "iv" in body) {
      if ("code" in body && body.code !== 200) {
        // decrypt anyway if you want the real error payload
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

    // Not encrypted → return as-is
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
