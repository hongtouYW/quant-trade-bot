import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toggleGroupCollection } from "@/services/group.service";
import type { CollectedGroup } from "@/types/group.types";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import { toast } from "sonner";
import { useTranslation } from "react-i18next";

export function useToggleGroupCollection() {
  const queryClient = useQueryClient();
  const { t } = useTranslation();

  return useMutation({
    mutationFn: toggleGroupCollection,
    onMutate: async () => {
      // Cancel any outgoing refetches (so they don't overwrite optimistic update)
      await queryClient.cancelQueries({ queryKey: ["myCollectedGroups"] });

      // Get the current data structure - store for potential rollback
      const previousData = queryClient.getQueryData(["myCollectedGroups"]);

      // Return a context object with the snapshotted value
      return { previousData };
    },
    onError: (_err, _variables, context) => {
      // If the mutation fails, make sure data is restored (though we didn't modify it in onMutate)
      if (context?.previousData) {
        queryClient.setQueryData(["myCollectedGroups"], context.previousData);
      }
    },
    onSuccess: (_data, variables) => {
      // Show success toast immediately
      if (_data.data.isCollected) {
        toast(t("toast.group_collected"));
      } else {
        toast(t("toast.group_uncollected"));
      }

      // Immediately invalidate the group detail to update the collection state
      queryClient.invalidateQueries({
        queryKey: ["groupDetail", variables.gid],
      });

      // Delay the actual removal from data to allow animation to complete (300ms)
      setTimeout(() => {
        queryClient.setQueryData(
          ["myCollectedGroups"],
          (old: ApiResponse<PaginatedData<CollectedGroup>>) => {
            if (!old?.data?.data) {
              return old;
            }

            const newData: ApiResponse<PaginatedData<CollectedGroup>> = {
              ...old,
              data: {
                ...old.data,
                data: old.data.data.filter(
                  (group: CollectedGroup) => group.id !== variables.gid,
                ),
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
        queryClient.invalidateQueries({ queryKey: ["myCollectedGroups"] });
      }, 350);
    },
  });
}
