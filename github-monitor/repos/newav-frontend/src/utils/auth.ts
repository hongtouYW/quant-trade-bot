// Utility functions for authentication state management

// Calculate expiration timestamp based on remember preference
export const calculateExpiration = (rememberMe: boolean): number => {
  const now = Math.floor(Date.now() / 1000); // Current time in seconds
  const expirationDuration = rememberMe
    ? 30 * 24 * 60 * 60 // 30 days in seconds
    : 24 * 60 * 60; // 1 day in seconds
  return now + expirationDuration;
};

// Enhanced setAuthToken with expiration
export const setAuthToken = (token: string, rememberMe: boolean = false) => {
  const expiration = calculateExpiration(rememberMe);

  // Persist across browser sessions (localStorage only)
  localStorage.setItem("tokenNew", token);
  localStorage.setItem("tokenExpiration", expiration.toString());

  // Clear any legacy sessionStorage tokens to prevent confusion
  sessionStorage.removeItem("tokenNew");
  sessionStorage.removeItem("tokenExpiration");
  sessionStorage.removeItem("rememberMe");

  // Dispatch custom event for same-tab reactivity
  window.dispatchEvent(new Event("tokenChanged"));
};

// Check if current token is expired
export const checkTokenExpiration = (): boolean => {
  const expiration = localStorage.getItem("tokenExpiration");
  if (!expiration) return false; // No expiration set = not expired

  const currentTime = Math.floor(Date.now() / 1000);
  return currentTime >= Number(expiration);
};

export const removeAuthToken = () => {
  // Clear from localStorage
  localStorage.removeItem("tokenNew");
  localStorage.removeItem("tokenExpiration");
  localStorage.removeItem("voucher_dont_ask");

  // Clear legacy sessionStorage keys if present
  sessionStorage.removeItem("tokenNew");
  sessionStorage.removeItem("tokenExpiration");
  sessionStorage.removeItem("rememberMe");

  // Clear user cache using helper (defined below)
  removeUserCache();

  // Dispatch custom event for same-tab reactivity
  window.dispatchEvent(new Event("tokenChanged"));
};

export const getAuthToken = () => {
  return localStorage.getItem("tokenNew");
};

export const isTokenExpired = (tokenVal: number): boolean => {
  const currentTimeInSeconds = Math.floor(Date.now() / 1000);
  return currentTimeInSeconds >= tokenVal;
};

// User cache storage helpers
export const setUserCache = (userData: string) => {
  localStorage.setItem("userInfoCache", userData);
  sessionStorage.removeItem("userInfoCache");
};

export const getUserCache = (): string | null => {
  return localStorage.getItem("userInfoCache");
};

export const removeUserCache = () => {
  localStorage.removeItem("userInfoCache");
  sessionStorage.removeItem("userInfoCache");
};
