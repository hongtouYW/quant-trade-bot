import { useMutation } from "@tanstack/react-query";
import { submitFeedback } from "@/services/user.service";
import { toast } from "sonner";
import { useTranslation } from "react-i18next";

interface FeedbackPayload {
  title: string;
  content: string;
}

export const useFeedback = () => {
  const { t } = useTranslation();

  return useMutation({
    mutationFn: (payload: FeedbackPayload) => submitFeedback(payload),
    onSuccess: (data) => {
      if (data.code === 1) {
        toast.success(t("feedback.success_message"));
      } else {
        toast.error(t("feedback.error_message"));
      }
    },
    onError: (error) => {
      console.error("Feedback submission failed:", error);
      toast.error(t("feedback.error_message"));
    },
  });
};