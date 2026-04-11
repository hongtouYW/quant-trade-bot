import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { getComicInfo } from "../api/comic-api";

const useComicDetail = (params: {
  mid: string;
  page?: number;
  limit?: number;
  sort?: string;
}) => {
  const { data, isSuccess } = useQuery({
    enabled: !!params.mid,
    queryKey: ["mid", params?.mid, params?.page, params?.sort],
    queryFn: () => getComicInfo(params),
    placeholderData: keepPreviousData,
  });

  return { data, isSuccess };
};

export default useComicDetail;
