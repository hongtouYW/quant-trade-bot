import { useQuery } from "@tanstack/react-query";
import { fetchActorInfo } from "@/services/actor.service.ts";
import type { ApiResponse } from "@/types/api-response.ts";
import type { ActorInfo } from "@/types/actor.types.ts";

export const useActorInfo = (
  actorId: string,
  initialData?: ApiResponse<ActorInfo>,
) =>
  useQuery({
    queryKey: ["actorInfo", Number(actorId)],
    queryFn: ({ signal }) => fetchActorInfo(actorId, signal),
    enabled: !!actorId,
    initialData,
  });
