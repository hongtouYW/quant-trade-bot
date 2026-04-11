import { useMutation } from "@tanstack/react-query";
import { purchaseComicChapter } from "../api/comic-api";

const useComicChapterBuy = () => {
  const { mutate, data, isPending, isSuccess } = useMutation({
    mutationFn: async (params: { token: string; cid: string }) => {
      const res = await purchaseComicChapter(params);

      return res;
    },
    onSuccess: (res) => {
      return res;
    },
    onError: () => {
      console.error("error");
    },
  });

  return {
    mutate,
    data,
    isPending,
    isSuccess,
  };
};

export default useComicChapterBuy;
