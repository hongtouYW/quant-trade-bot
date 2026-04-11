import { useInfiniteQuery } from "@tanstack/react-query";
import { fetchVideoList } from "@/services/video.service.ts";

export const useInfiniteVideoList = () =>
  useInfiniteQuery({
    queryKey: ["infiniteVideoList"],
    queryFn: ({ pageParam = 1, signal }) => {
      const payload = {
        page: pageParam,
        limit: 20,
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
  });
