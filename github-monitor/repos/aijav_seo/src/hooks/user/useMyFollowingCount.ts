import { useQuery } from "@tanstack/react-query";
import { fetchSubscribedActorList } from "@/services/actor.service.ts";
import { fetchMyPublisherList } from "@/services/publisher.service.ts";

type FollowingCountResult = {
  actorTotal: number;
  publisherTotal: number;
  totalCount: number;
};

export const useMyFollowingCount = () => {
  return useQuery<FollowingCountResult>({
    queryKey: ["myFollowingCount"],
    queryFn: async ({ signal }) => {
      const [actorResponse, publisherResponse] = await Promise.all([
        fetchSubscribedActorList(signal, { page: 1, limit: 1 }),
        fetchMyPublisherList(signal, { page: 1, limit: 1 }),
      ]);

      const actorTotal = actorResponse?.data?.total ?? 0;
      const publisherTotal = publisherResponse?.data?.total ?? 0;

      return {
        actorTotal,
        publisherTotal,
        totalCount: actorTotal + publisherTotal,
      };
    },
    refetchOnWindowFocus: false,
  });
};
