import { useMutation } from "@tanstack/react-query";
import { submitUserFeedback } from "../api/user-api";
import { toast } from "react-toastify";
import { useTranslation } from "react-i18next";

const useUserFeedback = () => {
  const { t } = useTranslation();

  const { mutate, isPending, isSuccess } = useMutation({
    mutationFn: async (params: { token?: string; satisfaction: 1 | 2 | 3; content: string; contact?: string }) => {
      const finalParams = {
        satisfaction: params.satisfaction,
        content: params.content,
        ...(params.contact ? { contact: params.contact } : {}),
        ...(params.token ? { token: params.token } : {}),
      }
      const res = await submitUserFeedback(finalParams);
      return res;
    },
    onSuccess: (res) => {
      if (res?.data?.code === 1) {
        toast.success(t("feedback.feedbackSubmitSuccess"));
      } else {
        console.log(res);

        toast.error(res?.data?.msg);
      }
    },
    onError: () => {
      toast.error(t("feedback.feedbackSubmitError"));
    },
  });

  return { mutate, isPending, isSuccess };
};

export default useUserFeedback;
