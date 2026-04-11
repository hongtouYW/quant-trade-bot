import { useQuery } from "@tanstack/react-query";
import { fetchGlobalSearch } from "@/services/search.service.ts";

export const useGlobalSearch = (keyword: string) =>
  useQuery({
    queryKey: ["globalSearchResult", keyword], // Include the param in query key
    queryFn: ({ signal }) => fetchGlobalSearch(keyword, signal),
    select: (data) => {
      return data.data;
    },
    enabled: !!keyword,
  });
