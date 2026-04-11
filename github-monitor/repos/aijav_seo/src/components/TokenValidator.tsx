import { useEffect } from "react";
import { checkTokenExpiration, removeAuthToken } from "@/utils/auth";
import { useQueryClient } from "@tanstack/react-query";

export const TokenValidator = () => {
  const queryClient = useQueryClient();

  useEffect(() => {
    // Check token on app mount
    if (checkTokenExpiration()) {
      removeAuthToken();
      queryClient.invalidateQueries({ type: "active" });
    }
  }, [queryClient]);

  return null; // This component doesn't render anything
};
