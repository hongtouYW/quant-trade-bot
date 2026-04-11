import { useMutation, useQueryClient } from "@tanstack/react-query";
import { collectVideo } from "@/services/video.service";
import type { Video } from "@/types/video.types";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import { toast } from "sonner";
import { useTranslation } from "react-i18next";

export function useToggleVideoCollection() {
  const queryClient = useQueryClient();
  const { t } = useTranslation();

  return useMutation({
    mutationFn: collectVideo,
    onMutate: async () => {
      // Cancel any outgoing refetches (so they don't overwrite optimistic update)
      await queryClient.cancelQueries({ queryKey: ["myCollectedVideos"] });

      // Get the current data structure - store for potential rollback
      const previousData = queryClient.getQueryData(["myCollectedVideos"]);

      // Return a context object with the snapshotted value
      return { previousData };
    },
    onError: (_err, _variables, context) => {
      // If the mutation fails, make sure data is restored (though we didn't modify it in onMutate)
      if (context?.previousData) {
        queryClient.setQueryData(["myCollectedVideos"], context.previousData);
      }
    },
    onSuccess: (_data, variables) => {
      // Show success toast immediately
      toast(t("toast.video_uncollected"));

      // Delay the actual removal from data to allow animation to complete (300ms)
      setTimeout(() => {
        queryClient.setQueryData(
          ["myCollectedVideos"],
          (old: ApiResponse<PaginatedData<Video>>) => {
            if (!old?.data?.data) {
              return old;
            }

            const newData: ApiResponse<PaginatedData<Video>> = {
              ...old,
              data: {
                ...old.data,
                data: old.data.data.filter(
                  (video: Video) => video.id !== variables.vid,
                ),
                total: old.data.total - 1, // Decrease total count
              },
            };
            return newData;
          },
        );
      }, 300);
    },
    onSettled: () => {
      // Refetch after a delay to ensure we have latest data, but don't interfere with animation
      setTimeout(() => {
        queryClient.invalidateQueries({ queryKey: ["myCollectedVideos"] });
      }, 350);
    },
  });
}