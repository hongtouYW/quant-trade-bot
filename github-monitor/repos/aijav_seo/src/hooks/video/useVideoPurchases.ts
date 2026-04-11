import { useQuery } from "@tanstack/react-query";
import { fetchVideoPurchases } from "@/services/video.service";

export const useVideoPurchases = () => {
  return useQuery({
    queryKey: ["videoPurchases"],
    queryFn: ({ signal }) => fetchVideoPurchases(signal),
    refetchOnWindowFocus: false,
  });
};