import { useQuery } from "@tanstack/react-query";
import { fetchMyCollectedGroups } from "@/services/group.service";

export function useMyCollectedGroups() {
  return useQuery({
    queryKey: ["myCollectedGroups"],
    queryFn: ({ signal }) => fetchMyCollectedGroups(signal),
    select: (data) => data.data?.data || [],
    refetchOnWindowFocus: false,
  });
}