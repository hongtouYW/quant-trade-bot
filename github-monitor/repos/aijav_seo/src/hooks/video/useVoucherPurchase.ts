import { useState, useCallback } from "react";
import { useTranslation } from "react-i18next";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { purchaseVideo } from "@/services/video.service";

type PurchaseStatus = "success" | "insufficient" | "error";

export interface PurchaseResult {
  status: PurchaseStatus;
  message?: string;
  code?: number;
}

interface UseVoucherPurchaseOptions {
  vid?: number;
  /**
   * Optional key used when invalidating the video info query.
   * Falls back to the numeric video id if not provided.
   */
  videoKey?: string | number;
}

export const useVoucherPurchase = ({
  vid,
  videoKey,
}: UseVoucherPurchaseOptions) => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [isPurchasing, setIsPurchasing] = useState(false);

  const purchaseVoucher = useCallback(async (): Promise<PurchaseResult> => {
    if (!vid || isPurchasing) {
      return { status: "error" };
    }

    setIsPurchasing(true);

    try {
      const response = await purchaseVideo({ vid });

      if (response.code === 1 && response.data.purchase) {
        toast.success(t("voucher_dialog.purchase_success"));

        const normalizedVideoKey =
          videoKey !== undefined
            ? String(videoKey)
            : typeof vid !== "undefined"
              ? String(vid)
              : undefined;

        const invalidations: Promise<unknown>[] = [];

        if (normalizedVideoKey) {
          invalidations.push(
            queryClient.invalidateQueries({
              queryKey: ["videoInfo", normalizedVideoKey],
            }),
          );
        } else {
          invalidations.push(
            queryClient.invalidateQueries({
              queryKey: ["videoInfo"],
            }),
          );
        }

        invalidations.push(
          queryClient.invalidateQueries({
            queryKey: ["videoAccess", vid],
          }),
        );

        invalidations.push(
          queryClient.invalidateQueries({
            queryKey: ["videoUrl", vid],
          }),
        );

        invalidations.push(
          queryClient.invalidateQueries({
            queryKey: ["userInfo"],
          }),
        );

        await Promise.allSettled(invalidations);

        return { status: "success" };
      }

      if (response.code === 6001) {
        const message = response.msg || t("voucher_dialog.purchase_failed");
        toast.error(message);
        return { status: "insufficient", message, code: response.code };
      }

      const message = response.msg || t("voucher_dialog.purchase_failed");
      toast.error(message);
      return { status: "error", message, code: response.code };
    } catch (error) {
      console.error("Purchase error:", error);
      toast.error(t("voucher_dialog.purchase_failed"));
      return { status: "error" };
    } finally {
      setIsPurchasing(false);
    }
  }, [vid, isPurchasing, queryClient, t, videoKey]);

  return {
    purchaseVoucher,
    isPurchasing,
  };
};
