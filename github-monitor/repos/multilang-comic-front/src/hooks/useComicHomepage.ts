import { useQuery } from "@tanstack/react-query";
import { getComicHomepage } from "../api/comic-api";

const useComicHomepage = (params: { page: number, limit: number }) => {
  const { data } = useQuery({
    queryKey: ["comicHomepage"],
    queryFn: () => getComicHomepage(params),
  });

  return { data };
};

export default useComicHomepage;