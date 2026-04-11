import { useMutation, useQueryClient, type InfiniteData } from "@tanstack/react-query";
import type { ActorSubscribePayload, ActorList } from "@/types/actor.types.ts";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";
import type { SubscribeResponse } from "@/types/search.types.ts";
import { subscribeToActor } from "@/services/actor.service.ts";
import { toast } from "sonner";
import { useTranslation } from "react-i18next";

export const useSubscribeToActor = () => {
  const queryClient = useQueryClient();
  const { t } = useTranslation();

  return useMutation<ApiResponse<SubscribeResponse>, Error, ActorSubscribePayload>({
    mutationFn: subscribeToActor,
    onSuccess: (response, variables) => {
      // Show success toast
      if (response.code === 1 && response.data) {
        const message = response.data.isSubscribed
          ? t("toast.actor_subscribed")
          : t("toast.actor_unsubscribed");
        toast(message);
      }

      const actorId = Number(variables.aid);
      const isSubscribed = response.data?.isSubscribed;

      // Optimistically update actor info detail page
      queryClient.setQueriesData(
        { queryKey: ["actorInfo", actorId] },
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

      // Optimistically update infinite actor list cache
      queryClient.setQueriesData<InfiniteData<ApiResponse<PaginatedData<ActorList>>>>(
        { queryKey: ["infiniteActorList"] },
        (oldData) => {
          if (!oldData) return oldData;

          return {
            ...oldData,
            pages: oldData.pages.map(page => ({
              ...page,
              data: {
                ...page.data,
                data: page.data.data.map(actor =>
                  actor.id === actorId
                    ? { ...actor, is_subscribe: isSubscribed ? 1 : 0 }
                    : actor
                )
              }
            }))
          };
        }
      );

      // Also update infinite actor by publisher list cache
      queryClient.setQueriesData<InfiniteData<ApiResponse<PaginatedData<ActorList>>>>(
        { queryKey: ["infiniteActorByPublisherList"] },
        (oldData) => {
          if (!oldData) return oldData;

          return {
            ...oldData,
            pages: oldData.pages.map(page => ({
              ...page,
              data: {
                ...page.data,
                data: page.data.data.map(actor =>
                  actor.id === actorId
                    ? { ...actor, is_subscribe: isSubscribed ? 1 : 0 }
                    : actor
                )
              }
            }))
          };
        }
      );

      // Invalidate queries to ensure fresh data on next fetch
      queryClient.invalidateQueries({
        queryKey: ["actorInfo", actorId],
      });
      queryClient.invalidateQueries({
        queryKey: ["actorByPublisherList"],
      });
      queryClient.invalidateQueries({
        queryKey: ["infiniteActorByPublisherList"],
      });
      queryClient.invalidateQueries({
        queryKey: ["actorList"]
      });
      queryClient.invalidateQueries({
        queryKey: ["infiniteActorList"]
      });
      // Invalidate Following page queries (for subscribe/unsubscribe updates)
      queryClient.invalidateQueries({
        queryKey: ["infiniteSubscribedActorList"]
      });
    },
    onError: () => {
      // Show error toast
      toast(t("toast.subscribe_error"));
    },
  });
};
