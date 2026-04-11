import { useQuery } from "@tanstack/react-query";
import { fetchGlobalImage } from "@/services/index.service.ts";

export const useGlobalImage = () =>
  useQuery({
    queryKey: ["globalImage"],
    queryFn: ({ signal }) => fetchGlobalImage(signal),
    select: (data) => data.data,
    refetchOnWindowFocus: false,
  });