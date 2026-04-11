import { useQuery } from "@tanstack/react-query";
import { fetchUserInfo } from "@/services/user.service.ts";
import { useAuth } from "@/hooks/auth/useAuth";
import { useUser, toSafeUser } from "@/contexts/UserContext";
import { useEffect } from "react";
import { isTokenExpired, removeAuthToken } from "@/utils/auth";

export const useUserInfo = () => {
  const { isAuthenticated } = useAuth();
  const { setUser } = useUser();

  const query = useQuery({
    queryKey: ["userInfo"],
    queryFn: ({ signal }) => fetchUserInfo(signal),
    enabled: isAuthenticated, // Only fetch when authenticated
    select: (data) => {
      return data.data;
    },
  });

  // Update user context when data is successfully fetched
  useEffect(() => {
    if (query.data && query.isSuccess) {
      const safeUser = toSafeUser(query.data);

      // Check if token is expired
      if (isTokenExpired(safeUser.token_val)) {
        removeAuthToken();
        return; // Don't set user data if token is expired
      }

      // Only update if the user ID is different (to prevent unnecessary re-renders)
      setUser(safeUser);
    }
  }, [query.data, query.isSuccess, setUser]);

  return query;
};
