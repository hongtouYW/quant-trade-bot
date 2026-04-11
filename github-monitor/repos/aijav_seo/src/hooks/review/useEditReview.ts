import { useMutation, useQueryClient } from "@tanstack/react-query";
import { editReview } from "@/services/review.service";
import type { EditReviewPayload } from "@/types/review.types";

export const useEditReview = (videoId: string) => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: EditReviewPayload) => editReview(payload),
    onSuccess: () => {
      // Invalidate and refetch review list to get updated review
      queryClient.invalidateQueries({ queryKey: ["reviewList", videoId] });
    },
  });
};