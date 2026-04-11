import {
  useMutation,
  useQueryClient,
  type InfiniteData,
} from "@tanstack/react-query";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { PublisherSubscribePayload } from "@/types/publisher.types.ts";
import type {
  SubscribeResponse,
  PublisherResult,
} from "@/types/search.types.ts";
import { subscribeToPublisher } from "@/services/publisher.service.ts";
import { toast } from "sonner";
import { useTranslation } from "react-i18next";

export const useSubscribeToPublisher = () => {
  const queryClient = useQueryClient();
  const { t } = useTranslation();

  return useMutation<
    ApiResponse<SubscribeResponse>,
    Error,
    PublisherSubscribePayload
  >({
    mutationFn: subscribeToPublisher,
    onSuccess: (response, variables) => {
      // Show success toast
      if (response.code === 1 && response.data) {
        const message = response.data.isSubscribed
          ? t("toast.publisher_subscribed")
          : t("toast.publisher_unsubscribed");
        toast(message);
      }

      const publisherId = String(variables.pid);
      const isSubscribed = response.data?.isSubscribed;

      // Optimistically update publisher info detail page
      queryClient.setQueriesData(
        { queryKey: ["publisherInfo", publisherId] },
        (oldData: any) => {
          if (!oldData) return oldData;
          return {
            ...oldData,
            data: {
              ...oldData.data,
              is_subscribe: isSubscribed ? 1 : 0,
            },
          };
        }
      );

      // Optimistically update infinite publisher list cache
      queryClient.setQueriesData<
        InfiniteData<ApiResponse<PaginatedData<PublisherResult>>>
      >({ queryKey: ["infinitePublisherList"] }, (oldData) => {
        if (!oldData) return oldData;

        return {
          ...oldData,
          pages: oldData.pages.map((page) => ({
            ...page,
            data: {
              ...page.data,
              data: page.data.data.map((publisher) =>
                String(publisher.id) === publisherId
                  ? { ...publisher, is_subscribe: isSubscribed ? 1 : 0 }
                  : publisher,
              ),
            },
          })),
        };
      });

      // Refetch queries to ensure data is fresh after subscription state change
      queryClient.refetchQueries({
        queryKey: ["publisherInfo", publisherId],
      });
      queryClient.invalidateQueries({
        queryKey: ["publisherList"],
      });
      queryClient.invalidateQueries({
        queryKey: ["infinitePublisherList"],
      });
      // Invalidate Following page queries (for subscribe/unsubscribe updates)
      queryClient.invalidateQueries({
        queryKey: ["infiniteMyPublisherList"],
      });
    },
    onError: () => {
      // Show error toast
      toast(t("toast.subscribe_error"));
    },
  });
};
