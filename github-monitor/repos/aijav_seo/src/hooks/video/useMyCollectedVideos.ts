import { useQuery } from "@tanstack/react-query";
import { fetchMyCollectedVideos } from "@/services/video.service";

export const useMyCollectedVideos = () => {
  return useQuery({
    queryKey: ["myCollectedVideos"],
    queryFn: ({ signal }) => fetchMyCollectedVideos(signal),
    refetchOnWindowFocus: false,
  });
};