import { useQuery } from "@tanstack/react-query";
import { fetchPublisherList } from "@/services/publisher.service.ts";

interface PublisherSearchParams {
  keyword: string;
  page?: number;
  limit?: number;
}

export const usePublisherSearch = ({ keyword, page = 1, limit = 20 }: PublisherSearchParams) =>
  useQuery({
    queryKey: ["publisherSearch", keyword, page, limit],
    queryFn: ({ signal }) => fetchPublisherList({ keyword, page, limit }, signal),
    enabled: !!keyword,
    refetchOnWindowFocus: false,
  });