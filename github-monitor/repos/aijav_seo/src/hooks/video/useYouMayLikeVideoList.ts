import { useQuery } from "@tanstack/react-query";
import { fetchVideoList } from "@/services/video.service.ts";

export const useYouMayLikeVideoList = (limit: number = 30) =>
  useQuery({
    queryKey: ["youMayLikeVideoList", limit],
    queryFn: ({ signal }) => {
      const payload = {
        page: 1,
        limit,
        random: 1,
      };

      return fetchVideoList(payload, signal);
    },
    refetchOnWindowFocus: false,
  });
