import React, {
  createContext,
  useContext,
  useEffect,
  useState,
  useCallback,
} from "react";
import { useAuth } from "@/hooks/auth/useAuth";
import {
  isTokenExpired,
  removeAuthToken,
  getUserCache,
  setUserCache,
  removeUserCache,
} from "@/utils/auth";

export interface SafeUser {
  id: number;
  coin: number;
  point: number;
  username: string;
  ori_password?: string;
  code: string;
  reg_time: number;
  token_val: number;
  vip_end_time: number;
  signature: string;
  is_vip: number;
}

interface UserContextType {
  user: SafeUser | null;
  setUser: (user: SafeUser | null) => void;
  clearUser: () => void;
}

const UserContext = createContext<UserContextType | undefined>(undefined);

export const UserProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const { isAuthenticated } = useAuth();
  const [user, setUserState] = useState<SafeUser | null>(() => {
    if (!isAuthenticated) return null;

    try {
      const cached = getUserCache();
      if (cached) {
        const parsedUser = JSON.parse(cached);
        // Check if cached user data has expired token
        if (parsedUser && isTokenExpired(parsedUser.token_val)) {
          removeUserCache();
          removeAuthToken();
          return null;
        }
        return parsedUser;
      }
      return null;
    } catch {
      return null;
    }
  });

  const setUser = useCallback((userData: SafeUser | null) => {
    // Check token expiration before setting user data
    if (userData && isTokenExpired(userData.token_val)) {
      removeAuthToken();
      return;
    }

    setUserState((prevUser) => {
      // Only update if the data is actually different
      if (!prevUser && !userData) return prevUser;
      if (!prevUser && userData) return userData;
      if (prevUser && !userData) return userData;

      // Deep comparison: check if any user properties have changed
      if (prevUser && userData) {
        const hasChanged =
          prevUser.id !== userData.id ||
          prevUser.coin !== userData.coin ||
          prevUser.point !== userData.point ||
          prevUser.username !== userData.username ||
          prevUser.is_vip !== userData.is_vip ||
          prevUser.vip_end_time !== userData.vip_end_time ||
          prevUser.token_val !== userData.token_val;

        if (!hasChanged) {
          return prevUser; // No changes, keep previous state
        }
      }

      return userData;
    });

    if (userData) {
      setUserCache(JSON.stringify(userData));
    } else {
      removeUserCache();
    }
  }, []);

  const clearUser = useCallback(() => {
    setUserState(null);
    removeUserCache();
  }, []);

  // Clear user data when authentication state changes to false
  useEffect(() => {
    if (!isAuthenticated) {
      clearUser();
    }
  }, [isAuthenticated]);

  return (
    <UserContext.Provider value={{ user, setUser, clearUser }}>
      {children}
    </UserContext.Provider>
  );
};

export const useUser = () => {
  const context = useContext(UserContext);
  if (context === undefined) {
    throw new Error("useUser must be used within a UserProvider");
  }
  return context;
};

// Helper function to convert full User to SafeUser
export const toSafeUser = (user: {
  id: number;
  coin: number;
  point: number;
  username: string;
  ori_password?: string;
  code: string;
  reg_time: number;
  token_val: number;
  vip_end_time: number;
  signature: string;
  is_vip: number;
}): SafeUser => {
  return {
    id: user.id,
    coin: user.coin,
    point: user.point,
    username: user.username,
    ori_password: user.ori_password,
    code: user.code,
    reg_time: user.reg_time,
    token_val: user.token_val,
    vip_end_time: user.vip_end_time,
    signature: user.signature,
    is_vip: user.is_vip,
  };
};
