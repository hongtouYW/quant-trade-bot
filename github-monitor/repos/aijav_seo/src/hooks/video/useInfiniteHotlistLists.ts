import { useInfiniteQuery } from "@tanstack/react-query";
import { fetchHotlistLists } from "@/services/video.service";

const DEFAULT_LIMIT = 12;

export const useInfiniteHotlistLists = (limit: number = DEFAULT_LIMIT) =>
  useInfiniteQuery({
    queryKey: ["infiniteHotlistLists", limit],
    queryFn: ({ pageParam = 1, signal }) =>
      fetchHotlistLists(
        {
          page: pageParam,
          limit,
        },
        signal,
      ),
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
