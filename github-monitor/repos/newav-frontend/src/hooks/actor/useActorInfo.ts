import { useQuery } from "@tanstack/react-query";
import { fetchActorInfo } from "@/services/actor.service.ts";

export const useActorInfo = (actorId: string) =>
  useQuery({
    queryKey: ["actorInfo", Number(actorId)], // Include the param in query key
    queryFn: ({ signal }) => fetchActorInfo(actorId, signal),
    // Return full API response to enable proper error handling
    // Component can access data via response.data and error info via response.code/msg
    enabled: !!actorId, // optional: avoids running if userId is falsy
  });
