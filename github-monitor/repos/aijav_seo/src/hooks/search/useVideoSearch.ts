import { useQuery } from "@tanstack/react-query";
import { fetchVideoList } from "@/services/video.service.ts";

interface VideoSearchParams {
  keyword: string;
  page?: number;
  limit?: number;
}

export const useVideoSearch = ({ keyword, page = 1, limit = 20 }: VideoSearchParams) =>
  useQuery({
    queryKey: ["videoSearch", keyword, page, limit],
    queryFn: ({ signal }) => fetchVideoList({ keyword, page, limit }, signal),
    enabled: !!keyword,
    refetchOnWindowFocus: false,
  });