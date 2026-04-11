import { useId } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { Textarea } from "@/components/ui/textarea";
import { Separator } from "@/components/ui/separator";
import { Button } from "@/components/ui/button";
import { SendHorizonal } from "lucide-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { submitReview } from "@/services/review.service";
import type { SubmitReviewPayload } from "@/types/review.types";
import { Rating, RatingButton } from "@/components/ui/rating";
import { useAuthAction } from "@/hooks/auth/useAuthAction";

// 🧠 Zod Schema
const createFormSchema = (t: (key: string) => string, isReply = false) =>
  z.object({
    review: z.string().max(225, t("comments.max_characters")).min(1),
    rating: isReply
      ? z.number().optional()
      : z.number().min(1, t("comments.rating_required")),
  });

type FormData = z.infer<ReturnType<typeof createFormSchema>>;

type CommentAreaProps = {
  videoId: string | undefined;
  parentId?: number;
  onCancel?: () => void;
  isReply?: boolean;
};

export default function CommentArea({
  videoId,
  parentId,
  onCancel,
  isReply = false,
}: CommentAreaProps) {
  const { t } = useTranslation();
  const id = useId();
  const queryClient = useQueryClient();
  const { executeWithAuth } = useAuthAction();

  const FormSchema = createFormSchema(t, isReply);

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors, isValid },
  } = useForm<FormData>({
    resolver: zodResolver(FormSchema),
    defaultValues: {
      review: "",
      rating: isReply ? undefined : 5, // 默认是5星评分，回复时不需要评分
    },
  });

  const commentValue = watch("review");

  const { mutate, isPending } = useMutation({
    mutationFn: (payload: SubmitReviewPayload) => submitReview(payload),
    onSuccess: () => {
      reset();
      queryClient.invalidateQueries({ queryKey: ["reviewList", videoId] });
      toast(t("toast.comment_submitted"));
      if (isReply && onCancel) {
        onCancel();
      }
    },
  });

  const onSubmit = (data: FormData) => {
    executeWithAuth(() => {
      const payload: SubmitReviewPayload = {
        review: data.review,
        rating: data.rating || 5, // Default rating for replies
        vid: Number(videoId!),
        ...(parentId && { parent_id: parentId }),
      };
      mutate(payload);
    });
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      {!isReply && (
        <div className="flex gap-2 mb-4 items-center">
          <p className="font-semibold">{t("comments.rating_label")}</p>
          <Rating
            className="text-sm sm:text-lg"
            onValueChange={(number) => setValue("rating", number)}
            defaultValue={watch("rating")}
          >
            {Array.from({ length: 5 }).map((_, index) => (
              <RatingButton key={index} />
            ))}
          </Rating>
          {errors.rating && (
            <p className="text-sm font-semibold text-red-500">
              {errors.rating.message}
            </p>
          )}
        </div>
      )}

      <div className="mt-2 bg-muted pt-4 pb-2 px-2 rounded-lg">
        <Textarea
          className="border-none shadow-none placeholder:text-base placeholder:font-semibold placeholder:text-[#BDBDBD]"
          id={id}
          placeholder={t("comments.comment_placeholder")}
          maxLength={225}
          aria-describedby={`${id}-description`}
          {...register("review")}
        />
        {errors.review && (
          <p className="text-sm text-red-500 mt-1">{errors.review.message}</p>
        )}

        <Separator className="my-2 bg-[#E0E0E0]" />

        <div className="flex items-center justify-between">
          <div>
            {/*<Button variant="ghost" size="icon" type="button">*/}
            {/*  <Smile className="stroke-[#757575]" />*/}
            {/*</Button>*/}
            {/*<Button variant="ghost" size="icon" type="button">*/}
            {/*  <Image className="stroke-[#757575]" />*/}
            {/*</Button>*/}
          </div>
          <div className="flex items-center gap-4">
            <p
              id={`${id}-description`}
              className="text-[#757575] font-semibold text-base"
              role="status"
              aria-live="polite"
            >
              <span className="tabular-nums">{commentValue?.length}/225</span>
            </p>
            <div className="flex gap-2">
              <Button
                size="sm"
                className="text-base"
                type="submit"
                disabled={!isValid || isPending}
              >
                <SendHorizonal className="mr-1 h-4 w-4" />
                {isReply
                  ? t("comments.post_reply")
                  : t("comments.post_comment")}
              </Button>
              {isReply && onCancel && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="text-base"
                  onClick={onCancel}
                >
                  {t("comments.cancel_reply")}
                </Button>
              )}
            </div>
          </div>
        </div>
      </div>
    </form>
  );
}
