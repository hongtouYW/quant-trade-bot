import { useEffect, useState } from "react";
import { checkTokenExpiration, removeAuthToken, getAuthToken } from "@/utils/auth";

export const useAuth = () => {
  const [token, setToken] = useState<string | null>(() => {
    // Check expiration on initial load
    if (checkTokenExpiration()) {
      removeAuthToken();
      return null;
    }
    return getAuthToken();
  });

  const isAuthenticated = !!token;

  useEffect(() => {
    // Check token expiration periodically (every 5 minutes)
    const intervalId = setInterval(() => {
      if (checkTokenExpiration()) {
        removeAuthToken(); // This will trigger tokenChanged event
      }
    }, 5 * 60 * 1000); // 5 minutes

    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === "tokenNew") {
        setToken(e.newValue);
      }
    };

    // Listen for localStorage changes from other tabs/windows
    window.addEventListener("storage", handleStorageChange);

    // Custom event for same-tab changes
    const handleCustomTokenChange = () => {
      setToken(getAuthToken());
    };

    window.addEventListener("tokenChanged", handleCustomTokenChange);

    return () => {
      clearInterval(intervalId);
      window.removeEventListener("storage", handleStorageChange);
      window.removeEventListener("tokenChanged", handleCustomTokenChange);
    };
  }, []);

  return {
    isAuthenticated,
    token,
  };
};