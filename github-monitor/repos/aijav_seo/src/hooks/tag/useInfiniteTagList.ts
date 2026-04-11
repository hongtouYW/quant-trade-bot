import { useInfiniteQuery } from "@tanstack/react-query";
import { fetchTagList } from "@/services/tag.service.ts";
import type { TagListRequest } from "@/types/tag.types.ts";

interface InfiniteTagListParams {
  top?: 0 | 1;
}

export const useInfiniteTagList = (params: InfiniteTagListParams = {}) =>
  useInfiniteQuery({
    queryKey: ["infiniteTagList", params],
    queryFn: ({ pageParam = 1, signal }) => {
      // First page gets 60 items, subsequent pages get 30
      const limit = pageParam === 1 ? 60 : 30;
      
      const payload: TagListRequest = {
        page: pageParam,
        limit,
        ...params,
      };
      
      return fetchTagList(payload, signal);
    },
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      const { current_page, last_page } = lastPage.data;
      return current_page < last_page ? current_page + 1 : undefined;
    },
    select: (data) => ({
      pages: data.pages.map(page => page.data),
      pageParams: data.pageParams,
    }),
    refetchOnWindowFocus: false,
  });