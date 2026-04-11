import { useQuery } from "@tanstack/react-query";
import { getComicAllRank } from "../api/comic-api";

const useComicAllRank = (params: { range: string }) => {
  const { data } = useQuery({
    queryKey: ["comicAllRank", params.range],
    queryFn: () => getComicAllRank(params),
  });

  return { data };
};

export default useComicAllRank;
