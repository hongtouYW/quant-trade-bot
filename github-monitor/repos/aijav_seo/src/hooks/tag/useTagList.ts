import { useQuery } from "@tanstack/react-query";
import { fetchTagList } from "@/services/tag.service.ts";
import type { TagListRequest } from "@/types/tag.types.ts";

export const useTagList = (payload: TagListRequest = { page: 1 }) =>
  useQuery({
    queryKey: ["tagList", payload],
    queryFn: ({ signal }) => fetchTagList(payload, signal),
    select: (data) => data.data,
    refetchOnWindowFocus: false,
  });