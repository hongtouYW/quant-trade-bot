import { useQuery } from "@tanstack/react-query";
import { fetchHotlistLists } from "@/services/video.service";

export const useHotlistLists = () => {
  return useQuery({
    queryKey: ["hotlistLists"],
    queryFn: ({ signal }) => fetchHotlistLists({ limit: 15 }, signal),
    refetchOnWindowFocus: false,
  });
};
