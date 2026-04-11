import { useMutation, useQueryClient } from "@tanstack/react-query";
import { purchaseGroup } from "@/services/group.service";
import { toast } from "sonner";
import { useTranslation } from "react-i18next";

export function usePurchaseGroup() {
  const queryClient = useQueryClient();
  const { t } = useTranslation();

  return useMutation({
    mutationFn: purchaseGroup,
    onSuccess: async (response, variables) => {
      if (response.code === 1 && response.data.purchase) {
        // Purchase successful
        toast(t("series_purchase_dialog.purchase_successful"));
        
        // Refresh both group detail and user data
        await Promise.all([
          queryClient.invalidateQueries({
            queryKey: ["groupDetail", variables.gid],
          }),
          queryClient.invalidateQueries({
            queryKey: ["userInfo"],
          }),
        ]);
      } else if (response.code === 5006) {
        // Series already purchased
        toast(t("series_purchase_dialog.already_purchased"));
        
        // Still refresh group detail in case UI is out of sync
        await queryClient.invalidateQueries({
          queryKey: ["groupDetail", variables.gid],
        });
      } else {
        // Other errors
        toast.error(response.msg || "Purchase failed");
      }
    },
    onError: (error) => {
      console.error("Purchase error:", error);
      toast.error("Purchase failed. Please try again.");
    },
  });
}