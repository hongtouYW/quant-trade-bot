import { useQuery } from "@tanstack/react-query";
import { fetchSeriesPurchases } from "@/services/group.service";

export const useSeriesPurchases = () => {
  return useQuery({
    queryKey: ["seriesPurchases"],
    queryFn: ({ signal }) => fetchSeriesPurchases(signal),
    refetchOnWindowFocus: false,
  });
};