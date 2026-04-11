import { useInfiniteQuery } from "@tanstack/react-query";
import { useLocation } from "react-router";
import { fetchVideoList } from "@/services/video.service.ts";

export const useInfiniteRecommendedVideoList = (limit: number = 10) => {
  const location = useLocation();

  // Auto-detect source from current pathname
  const source = location.pathname.split("/")[1] || "home";

  return useInfiniteQuery({
    queryKey: ["infiniteRecommendedVideoList", limit, source],
    queryFn: ({ pageParam = 1, signal }) => {
      const payload = {
        page: pageParam,
        limit,
        random: 1,
      };

      return fetchVideoList(payload, signal);
    },
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      const { current_page, last_page } = lastPage.data;
      return current_page < last_page ? current_page + 1 : undefined;
    },
    select: (data) => ({
      pages: data.pages.map((page) => page.data),
      pageParams: data.pageParams,
    }),
    refetchOnWindowFocus: false,
    staleTime: 5 * 60 * 1000, // Keep data fresh for 5 minutes
  });
};
