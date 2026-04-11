import { useMutation, useQueryClient } from "@tanstack/react-query";
import { deleteReview } from "@/services/review.service";
import type { DeleteReviewPayload } from "@/types/review.types";

export const useDeleteReview = (videoId: string) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: DeleteReviewPayload) => deleteReview(payload),
    onSuccess: () => {
      // Invalidate and refetch review list to remove deleted review
      queryClient.invalidateQueries({ queryKey: ["reviewList", videoId] });
    },
  });
};