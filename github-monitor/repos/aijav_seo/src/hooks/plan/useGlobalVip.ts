import { useQuery } from "@tanstack/react-query";
import { fetchGlobalVip } from "@/services/plan.service.ts";

export const useGlobalVip = () =>
  useQuery({
    queryKey: ["globalVip"],
    queryFn: ({ signal }) => fetchGlobalVip(signal),
    select: (data) => data.data,
    refetchOnWindowFocus: false,
  });