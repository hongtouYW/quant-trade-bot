import { useQuery } from "@tanstack/react-query";
import { getComicRank } from "../api/comic-api";

const useComicRank = (params: { type: string }) => {
  const { data } = useQuery({
    queryKey: ["comicRank", params.type],
    queryFn: () => getComicRank(params),
  });

  return { data };
};

export default useComicRank;