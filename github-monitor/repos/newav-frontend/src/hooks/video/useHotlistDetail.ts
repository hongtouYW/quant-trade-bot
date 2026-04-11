import { useQuery } from "@tanstack/react-query";
import { fetchHotlistDetail } from "@/services/video.service";

export const useHotlistDetail = (hid?: number) => {
  return useQuery({
    queryKey: ["hotlistDetail", hid],
    queryFn: ({ signal }) => fetchHotlistDetail(hid!, signal),
    enabled: !!hid,
    refetchOnWindowFocus: false,
  });
};