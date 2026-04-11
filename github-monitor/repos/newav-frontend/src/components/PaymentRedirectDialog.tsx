import React from "react";
import { useTranslation } from "react-i18next";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

interface PaymentRedirectDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  paymentUrl?: string;
}

export default function PaymentRedirectDialog({
  open,
  onOpenChange,
  paymentUrl,
}: PaymentRedirectDialogProps) {
  const { t } = useTranslation();

  React.useEffect(() => {
    if (open && paymentUrl) {
      // Auto-redirect after a short delay
      const timer = setTimeout(() => {
        window.open(paymentUrl, "_blank");
      }, 2000);

      return () => clearTimeout(timer);
    }
  }, [open, paymentUrl]);

  const handleManualRedirect = () => {
    if (paymentUrl) {
      window.open(paymentUrl, "_blank");
      onOpenChange(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t("plans.redirecting_to_payment")}</DialogTitle>
        </DialogHeader>

        <div className="text-center pt-6">
          <div className="mb-4">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
          </div>

          <div className="text-sm text-gray-600 mb-6">
            {t("plans.redirect_message")}
          </div>

          <div className="text-xs text-gray-500 mb-4">
            {t("plans.if_not_redirect")}
          </div>

          <Button onClick={handleManualRedirect} className="w-full">
            {t("plans.click_here_to_pay")}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
