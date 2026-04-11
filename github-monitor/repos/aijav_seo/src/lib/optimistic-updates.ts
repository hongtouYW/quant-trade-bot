import { QueryClient } from "@tanstack/react-query";
import type { ApiResponse, PaginatedData } from "@/types/api-response.ts";

// Utility function to update an actor's subscription status in any list
export const updateActorInCache = (
  queryClient: QueryClient,
  actorId: string,
  isSubscribed: boolean
) => {
  const subscriptionValue = isSubscribed ? 1 : 0;
  
  // Update all actor list queries
  queryClient.setQueriesData(
    { queryKey: ["actorList"] },
    (oldData: ApiResponse<PaginatedData<any>> | undefined) => {
      if (!oldData?.data?.data) return oldData;
      
      return {
        ...oldData,
        data: {
          ...oldData.data,
          data: oldData.data.data.map((actor: any) =>
            String(actor.id) === actorId
              ? { ...actor, is_subscribe: subscriptionValue }
              : actor
          ),
        },
      };
    }
  );
  
  // Update actor info query if it exists
  queryClient.setQueryData(
    ["actorInfo", Number(actorId)],
    (oldData: ApiResponse<any> | undefined) => {
      if (!oldData?.data) return oldData;
      
      return {
        ...oldData,
        data: {
          ...oldData.data,
          is_subscribe: subscriptionValue,
        },
      };
    }
  );
  
  // Update search results if they contain actors
  queryClient.setQueriesData(
    { queryKey: ["search"] },
    (oldData: any) => {
      if (!oldData?.data?.actor?.data) return oldData;
      
      return {
        ...oldData,
        data: {
          ...oldData.data,
          actor: {
            ...oldData.data.actor,
            data: oldData.data.actor.data.map((actor: any) =>
              String(actor.id) === actorId
                ? { ...actor, is_subscribe: subscriptionValue }
                : actor
            ),
          },
        },
      };
    }
  );
};

// Utility function to update a publisher's subscription status in any list
export const updatePublisherInCache = (
  queryClient: QueryClient,
  publisherId: string,
  isSubscribed: boolean
) => {
  const subscriptionValue = isSubscribed ? 1 : 0;
  
  // Update publisher list queries
  queryClient.setQueriesData(
    { queryKey: ["publisherList"] },
    (oldData: ApiResponse<PaginatedData<any>> | undefined) => {
      if (!oldData?.data?.data) return oldData;
      
      return {
        ...oldData,
        data: {
          ...oldData.data,
          data: oldData.data.data.map((publisher: any) =>
            String(publisher.id) === publisherId
              ? { ...publisher, is_subscribe: subscriptionValue }
              : publisher
          ),
        },
      };
    }
  );
  
  // Update publisher info query if it exists
  queryClient.setQueryData(
    ["publisherInfo", Number(publisherId)],
    (oldData: ApiResponse<any> | undefined) => {
      if (!oldData?.data) return oldData;
      
      return {
        ...oldData,
        data: {
          ...oldData.data,
          is_subscribe: subscriptionValue,
        },
      };
    }
  );
  
  // Update search results if they contain publishers
  queryClient.setQueriesData(
    { queryKey: ["search"] },
    (oldData: any) => {
      if (!oldData?.data?.publisher?.data) return oldData;
      
      return {
        ...oldData,
        data: {
          ...oldData.data,
          publisher: {
            ...oldData.data.publisher,
            data: oldData.data.publisher.data.map((publisher: any) =>
              String(publisher.id) === publisherId
                ? { ...publisher, is_subscribe: subscriptionValue }
                : publisher
            ),
          },
        },
      };
    }
  );
};