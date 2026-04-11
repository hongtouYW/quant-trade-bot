import { useMutation, useQueryClient } from "@tanstack/react-query";
import { likeReview } from "@/services/review.service";
import type { LikeReviewPayload } from "@/types/review.types";

export const useLikeReview = (videoId: string) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: LikeReviewPayload) => likeReview(payload),
    onSuccess: () => {
      // Invalidate and refetch review list to get updated like counts
      queryClient.invalidateQueries({ queryKey: ["reviewList", videoId] });
    },
  });
};