import { useMutation } from "@tanstack/react-query";
import { fetchPaymentPlatforms } from "@/services/plan.service.ts";
import type { PaymentPlatformsRequest } from "@/types/plan.types.ts";

export const usePaymentPlatforms = () =>
  useMutation({
    mutationFn: (payload: PaymentPlatformsRequest) => fetchPaymentPlatforms(payload),
  });
