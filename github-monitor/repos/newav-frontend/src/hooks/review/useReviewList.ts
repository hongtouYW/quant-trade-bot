import { useQuery } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import { fetchReviewList } from "@/services/review.service.ts";
import { getRandomMockComment } from "@/lib/mockComments.ts";

export const useReviewList = (videoId: string) => {
  const { i18n } = useTranslation();

  return useQuery({
    queryKey: ["reviewList", videoId],
    queryFn: async ({ signal }) => {
      const response = await fetchReviewList(
        {
          vid: +videoId,
        },
        signal,
      );

      // If no comments exist, return 20 mock comments
      if (!response.data || response.data.length === 0) {
        return getRandomMockComment(i18n.language || "en");
      }
      return response.data;
    },
    enabled: !!videoId,
    // staleTime: 1000 * 60 * 5,
  });
};
