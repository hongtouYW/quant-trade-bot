import { useQuery } from "@tanstack/react-query";
import { fetchVipOrders } from "@/services/plan.service";

export const useVipOrders = () => {
  return useQuery({
    queryKey: ["vipOrders"],
    queryFn: ({ signal }) => fetchVipOrders(signal),
    refetchOnWindowFocus: false,
  });
};