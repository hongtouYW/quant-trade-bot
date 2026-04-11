import { keepPreviousData, useQuery, useMutation } from "@tanstack/react-query";
import { getComicChapterInfo } from "../api/comic-api";

// 🔹 Query hook (auto-fetch by cid, with cache)
const useComicChapterInfo = (params: { cid: string }) => {
  const { data, isSuccess, isFetched } = useQuery({
    queryKey: ["comicChapterInfo", params],
    queryFn: () => getComicChapterInfo(params),
    placeholderData: keepPreviousData,
  });

  return { data, isSuccess, isFetched };
};

// 🔹 Mutation hook (manual fetch by any params)
const useComicChapterInfoMutation = () => {
  return useMutation({
    mutationFn: async (params: { cid: string }) => {
      const res = await getComicChapterInfo(params);

      return res;
    },
    onSuccess: (res) => {
      return res;
    },
    onError: () => {
      console.error("error");
    },
  });
};

export { useComicChapterInfo, useComicChapterInfoMutation };
