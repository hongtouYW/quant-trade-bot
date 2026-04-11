import { useQuery } from "@tanstack/react-query";
import { fetchPlayLog } from "@/services/video.service";

export const usePlayLog = () => {
  return useQuery({
    queryKey: ["playLog"],
    queryFn: ({ signal }) => fetchPlayLog(signal),
    refetchOnWindowFocus: false,
  });
};