import { useMutation } from "@tanstack/react-query";

import { addComicFavorite } from "../api/comic-api";
import { toast } from "react-toastify";
import { useTranslation } from "react-i18next";

const useComicFavorite = (params: { token: string; mid: string }) => {
  const { t } = useTranslation();

  const { mutate, isPending, isSuccess } = useMutation({
    mutationFn: async () => {
      const res = await addComicFavorite(params);

      return res;
    },
    onSuccess: (res) => {
      if (res?.data?.code === 1) {
        toast.success(t("common.favoriteSuccess"));

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

export default useComicFavorite;
