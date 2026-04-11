import { useMutation } from "@tanstack/react-query";
import { purchasePackage } from "@/services/plan.service.ts";
import type { PurchasePackageRequest } from "@/types/plan.types.ts";

export const usePurchasePackage = (
  onRedirect?: (payUrl: string) => void,
  onError?: (errorMsg: string) => void
) =>
  useMutation({
    mutationFn: (payload: PurchasePackageRequest) => purchasePackage(payload),
    onSuccess: (data) => {
      // Check if API returned an error in the response
      if (data.code !== 1) {
        // API returned error with 200 status
        if (onError) {
          onError(data.msg);
        }
        return;
      }

      // Success case - redirect to payment
      if (data.data.pay_url && onRedirect) {
        onRedirect(data.data.pay_url);
      }
    },
  });