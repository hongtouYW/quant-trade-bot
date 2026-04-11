import { useQuery } from "@tanstack/react-query";
import { fetchConfig } from "@/services/config.service";

export const useConfig = () => {
  return useQuery({
    queryKey: ["config"],
    queryFn: ({ signal }) => fetchConfig(signal),
    refetchOnWindowFocus: false,
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
};