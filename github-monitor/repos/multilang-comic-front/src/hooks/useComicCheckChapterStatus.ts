import { useQuery } from "@tanstack/react-query";
import { getComicCheckChapterStatus } from "../api/comic-api";

const useComicCheckChapterStatus = (params: { token: string; mid: string }) => {
  const { data, refetch } = useQuery({
    queryKey: ["comicCheckChaptersStatus", params],
    queryFn: () => getComicCheckChapterStatus(params),
  });

  return { data, refetch };
};

export default useComicCheckChapterStatus;
