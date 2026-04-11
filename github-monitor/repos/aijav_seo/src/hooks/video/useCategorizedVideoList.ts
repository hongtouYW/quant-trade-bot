import { useInfiniteQuery } from "@tanstack/react-query";
import { fetchCategorizedVideoList } from "@/services/video.service.ts";

export const useInfiniteCategorizedVideoList = (limit: number = 3) =>
  useInfiniteQuery({
    queryKey: ["infiniteCategorizedVideo", limit],
    queryFn: ({ pageParam = 1, signal }) => {
      const payload = {
        page: pageParam,
        limit,
      };
      return fetchCategorizedVideoList(payload, signal);
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
  });
