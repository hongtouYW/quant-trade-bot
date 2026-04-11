import { useQuery } from "@tanstack/react-query";
import { fetchGroupList } from "@/services/group.service.ts";

interface GroupSearchParams {
  keyword: string;
  page?: number;
  limit?: number;
}

export const useGroupSearch = ({ keyword, page = 1, limit = 20 }: GroupSearchParams) =>
  useQuery({
    queryKey: ["groupSearch", keyword, page, limit],
    queryFn: ({ signal }) => fetchGroupList({ keyword, page, limit }, signal),
    enabled: !!keyword,
    refetchOnWindowFocus: false,
  });