import { useQuery } from "@tanstack/react-query";
import { fetchGroupDetail } from "@/services/group.service";

export function useGroupDetail(gid: number | undefined) {
  return useQuery({
    queryKey: ["groupDetail", gid],
    queryFn: ({ signal }) => {
      if (gid === undefined) throw new Error("gid is required");
      return fetchGroupDetail(gid, signal);
    },
    select: (data) => data.data,
    enabled: !!gid,
  });
}
