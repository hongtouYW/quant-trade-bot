import { useMutation } from "@tanstack/react-query";

import { removeComicFavorite } from "../api/comic-api";
import { toast } from "react-toastify";
import { useTranslation } from "react-i18next";

const useComicUnFavorite = (params: { token: string; mid: string }) => {
  const { t } = useTranslation();

  const { mutate, isPending, isSuccess } = useMutation({
    mutationFn: async () => {
      const res = await removeComicFavorite(params);

      return res;
    },
    onSuccess: (res) => {
      if (res?.data?.code === 1) {
        toast.success(t("common.unFavoriteSuccess"));
      } else {
        toast.error(res?.data?.msg);
      }
      return res;
    },
    onError: () => {
      console.error("error");
    },
  });
  return {
    mutate,
    isPending,
    isSuccess,
  };
};

export default useComicUnFavorite;
