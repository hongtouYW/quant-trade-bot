import { useQuery } from "@tanstack/react-query";
import { fetchActorList } from "@/services/actor.service.ts";

interface ActorSearchParams {
  keyword: string;
  page?: number;
  limit?: number;
}

export const useActorSearch = ({ keyword, page = 1, limit = 20 }: ActorSearchParams) =>
  useQuery({
    queryKey: ["actorSearch", keyword, page, limit],
    queryFn: ({ signal }) => fetchActorList({ keyword, page, limit }, signal),
    enabled: !!keyword,
    refetchOnWindowFocus: false,
  });