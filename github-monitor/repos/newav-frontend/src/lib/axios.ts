import axios, { AxiosError } from "axios";
import type { InternalAxiosRequestConfig } from "axios";
import {
  isTokenExpired,
  removeAuthToken,
  getAuthToken,
  getUserCache,
} from "@/utils/auth";
import i18n from "@/i18n";
import { RATE_LIMIT_ERROR_CODE } from "@/constants/network.ts";
import u from "@/example/utils";

// const RATE_LIMIT_TOAST_COOLDOWN_MS = 10000; // 10 sec
// let lastRateLimitToastTimestamp = 0;
const SUFFIX_HEADER = "NWSdef";

type EncryptableAxiosConfig = InternalAxiosRequestConfig & {
  skipEncryption?: boolean;
};

const isPlainObject = (value: unknown): value is Record<string, any> => {
  if (value === null || typeof value !== "object") {
    return false;
  }

  const isFormData =
    typeof FormData !== "undefined" && value instanceof FormData;
  const isUrlParams =
    typeof URLSearchParams !== "undefined" && value instanceof URLSearchParams;

  if (isFormData || isUrlParams) {
    return false;
  }

  return (
    Object.getPrototypeOf(value) === Object.prototype ||
    Object.getPrototypeOf(value) === null
  );
};

const buildBasePayload = (data: Record<string, any>) => {
  const payload: Record<string, any> = {
    timestamp: Date.now(),
    ...data,
  };

  return payload;
};

const encryptPayload = (payload: Record<string, any>) => {
  const payloadWithSignature = {
    ...payload,
    encode_sign: u.base64Sign(payload),
  };

  return {
    "post-data": u.encrypt(JSON.stringify(payloadWithSignature)),
  };
};

const tryDecryptResponse = (data: any) => {
  if (
    data &&
    typeof data === "object" &&
    typeof data.data === "string" &&
    data.data &&
    typeof data.suffix === "string"
  ) {
    try {
      const decrypted = u.decrypt(data.data, data.suffix);
      return JSON.parse(decrypted);
    } catch (error) {
      console.warn("Failed to decrypt response payload", error);
    }
  }

  return null;
};

const instance = axios.create({
  baseURL: "https://newavapi.9xyrp3kg4b86.com/",
  headers: {
    "Content-Type": "application/json",
    suffix: SUFFIX_HEADER,
  },
});

// Function to get current language
const getCurrentLanguage = () => {
  return i18n.language || "zh"; // Default to Chinese
};

instance.interceptors.request.use((config) => {
  const requestConfig = config as EncryptableAxiosConfig;
  requestConfig.headers = requestConfig.headers ?? {};

  // Add language header to all requests
  requestConfig.headers.lang = getCurrentLanguage();
  requestConfig.headers.suffix = SUFFIX_HEADER;

  // Do not attach token for login endpoint
  if (!config.url?.endsWith("/user/login")) {
    const token = getAuthToken();
    if (token) {
      // Check token expiration before making request
      try {
        const userCache = getUserCache();
        if (userCache) {
          const userData = JSON.parse(userCache);
          if (
            userData &&
            userData.token_val &&
            isTokenExpired(userData.token_val)
          ) {
            removeAuthToken();
            return Promise.reject(new Error("Token expired"));
          }
        }
      } catch (error) {
        console.warn(
          "Error checking token expiration in request interceptor:",
          error,
        );
      }

      const canProcessBody =
        requestConfig.data === undefined || isPlainObject(requestConfig.data);

      if (canProcessBody) {
        const currentData = isPlainObject(requestConfig.data)
          ? { ...requestConfig.data }
          : {};
        requestConfig.data = {
          ...currentData,
          token,
        };
      } else if (
        typeof FormData !== "undefined" &&
        requestConfig.data instanceof FormData
      ) {
        requestConfig.data.set("token", token);
      }
    }
  }

  const shouldEncrypt = true;
  const shouldHandlePayload =
    requestConfig.data === undefined || isPlainObject(requestConfig.data);

  if (!shouldHandlePayload) {
    return requestConfig;
  }

  const baseData = isPlainObject(requestConfig.data)
    ? { ...requestConfig.data }
    : {};
  const payload = buildBasePayload(baseData);

  requestConfig.data = shouldEncrypt ? encryptPayload(payload) : payload;

  return requestConfig;
});

instance.interceptors.response.use(
  (response) => {
    const decryptedPayload = tryDecryptResponse(response?.data);
    if (decryptedPayload) {
      response.data = decryptedPayload;
    }

    // console.log(decryptedPayload);

    const token = getAuthToken();
    if (token && response?.data?.code === 2000) {
      removeAuthToken(); // Use utility function for complete cleanup
    }
    if (response?.data?.code === RATE_LIMIT_ERROR_CODE) {
      // const now = Date.now();
      const fallbackMessage = i18n.t("toast.rate_limit");
      // if (now - lastRateLimitToastTimestamp > RATE_LIMIT_TOAST_COOLDOWN_MS) {
      //   toast.error(response?.data?.msg || fallbackMessage);
      //   lastRateLimitToastTimestamp = now;
      // }

      return Promise.reject(
        new AxiosError(
          response?.data?.msg || fallbackMessage,
          undefined,
          response.config,
          response.request,
          response,
        ),
      );
    }
    return response;
  },
  (error) => Promise.reject(error),
);

export default instance;
