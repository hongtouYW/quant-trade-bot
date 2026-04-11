import { useQuery } from "@tanstack/react-query";
import { fetchRedeemRecord } from "@/services/user.service";
import { useAuth } from "@/hooks/auth/useAuth";

export const useRedeemRecord = () => {
  const { isAuthenticated } = useAuth();

  return useQuery({
    queryKey: ["redeemRecord"],
    queryFn: ({ signal }) => fetchRedeemRecord(signal),
    enabled: isAuthenticated,
    refetchOnWindowFocus: false,
  });
};
