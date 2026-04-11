import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { getComicLists } from "../api/comic-api";

const useComicLists = (params: {
  ticai_id?: string;
  weekday?: string;
  year?: string;
  month?: string;
  tag?: string;
  mhstatus?: string;
  type?: string;
  page: number;
  limit: number;
}) => {
  const { data } = useQuery({
    queryKey: ["comicLists", params],
    queryFn: () => getComicLists(params),
    placeholderData: keepPreviousData,
  });

  return { data };
};

export default useComicLists;
