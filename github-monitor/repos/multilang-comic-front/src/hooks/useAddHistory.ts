import { useMutation } from "@tanstack/react-query";
import { addUserHistory } from "../api/user-api";

const useAddHistory = () => {
  const { mutate, isPending, isSuccess } = useMutation({
    mutationFn: async (params: { token: string; cid: string }) => {
      const res = await addUserHistory(params);
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
    isPending,
    isSuccess,
  };
};

export default useAddHistory;
