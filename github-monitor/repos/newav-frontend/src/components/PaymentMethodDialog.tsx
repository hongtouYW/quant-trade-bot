import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { usePaymentPlatforms } from "@/hooks/plan/usePaymentPlatforms";
import { usePurchasePackage } from "@/hooks/plan/usePurchasePackage";
import PaymentRedirectDialog from "@/components/PaymentRedirectDialog";
import PaymentConfirmationDialog from "@/components/PaymentConfirmationDialog";
import type { PaymentPlatform } from "@/types/plan.types";

interface PaymentMethodDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  packageData?: {
    id: number;
    name: string;
    price: string;
    type: 1 | 2 | 3; // 1=vip, 2=diamond, 3=point
  };
}

const getPaymentIcon = (name: string) => {
  const lowerName = name.toLowerCase();
  if (lowerName.includes("支付宝") || lowerName.includes("alipay")) {
    return (
      <img src="/zfb.png" alt="Alipay" className="size-15 object-contain" />
    );
  } else if (lowerName.includes("微信") || lowerName.includes("wechat")) {
    return (
      <img src="/wx.svg" alt="WeChat Pay" className="size-15 object-contain" />
    );
  }
  return null;
};

export default function PaymentMethodDialog({
  open,
  onOpenChange,
  packageData,
}: PaymentMethodDialogProps) {
  const { t } = useTranslation();
  const [redirectDialogOpen, setRedirectDialogOpen] = useState(false);
  const [confirmationDialogOpen, setConfirmationDialogOpen] = useState(false);
  const [paymentUrl, setPaymentUrl] = useState<string>();
  const [apiErrorMsg, setApiErrorMsg] = useState<string>();

  const {
    mutate: fetchPlatforms,
    data,
    isPending: platformsLoading,
    isError: platformsError,
  } = usePaymentPlatforms();

  const handleRedirect = (payUrl: string) => {
    setPaymentUrl(payUrl);
    setRedirectDialogOpen(true);
    onOpenChange(false); // Close the payment method dialog
  };

  const handleApiError = (errorMsg: string) => {
    setApiErrorMsg(errorMsg);
  };

  const handleRedirectDialogClose = (open: boolean) => {
    setRedirectDialogOpen(open);
    if (!open) {
      // When redirect dialog closes, show confirmation dialog
      setConfirmationDialogOpen(true);
    }
  };

  const {
    mutate: purchasePackage,
    isPending: purchaseLoading,
    isError: purchaseError,
    reset: resetPurchase,
  } = usePurchasePackage(handleRedirect, handleApiError);

  useEffect(() => {
    if (open && packageData) {
      // Clear error states and reset mutation when opening dialog with new package
      setApiErrorMsg(undefined);
      resetPurchase();
      fetchPlatforms({ vid: packageData.id, p_type: packageData.type });
    } else if (!open) {
      // Clear error states when dialog closes
      setApiErrorMsg(undefined);
      resetPurchase();
    }
  }, [open, packageData, fetchPlatforms, resetPurchase]);

  const handlePaymentSelect = (platform: PaymentPlatform) => {
    if (!packageData) return;

    // Clear any previous error message
    setApiErrorMsg(undefined);

    purchasePackage({
      vid: packageData.id,
      p_type: packageData.type,
      pid: platform.id,
    });
  };

  const platforms = data?.data || [];

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t("plans.select_payment_method")}</DialogTitle>
          </DialogHeader>

          {packageData && (
            <div className="bg-background rounded-lg">
              <div className="text-sm text-foreground">
                {t("plans.selected_package")}
              </div>
              <div className="font-medium">{packageData.name}</div>
              <div className="text-primary font-semibold">
                ¥{packageData.price}
              </div>
            </div>
          )}

          <div className="space-y-3">
            {platformsLoading ? (
              <div className="text-center py-4">
                <div className="text-sm text-gray-500">
                  {t("common.loading")}
                </div>
              </div>
            ) : platformsError ? (
              <div className="text-center py-4">
                <div className="text-sm text-red-500">
                  {t("common.error_occurred")}
                </div>
              </div>
            ) : platforms.length === 0 ? (
              <div className="text-center py-4">
                <div className="text-sm text-gray-500">
                  {t("plans.no_payment_methods")}
                </div>
              </div>
            ) : (
              platforms.map((platform) => (
                <Button
                  key={platform.id}
                  variant="outline"
                  className="w-full justify-between py-1 px-4 h-auto"
                  onClick={() => handlePaymentSelect(platform)}
                  disabled={purchaseLoading}
                >
                  <span>{platform.name}</span>
                  <div>{getPaymentIcon(platform.name)}</div>
                </Button>
              ))
            )}
          </div>

          {(purchaseError || apiErrorMsg) && (
            <div className="text-center py-2">
              <div className="text-sm text-red-500">
                {apiErrorMsg || t("common.error_occurred")}
              </div>
            </div>
          )}

          {purchaseLoading && (
            <div className="text-center py-2">
              <div className="text-sm text-gray-500">
                {t("plans.processing_payment")}
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <PaymentRedirectDialog
        open={redirectDialogOpen}
        onOpenChange={handleRedirectDialogClose}
        paymentUrl={paymentUrl}
      />

      <PaymentConfirmationDialog
        open={confirmationDialogOpen}
        onOpenChange={setConfirmationDialogOpen}
      />
    </>
  );
}
