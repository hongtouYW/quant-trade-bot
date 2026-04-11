import { useTranslation } from "react-i18next";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

interface PaymentConfirmationDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export default function PaymentConfirmationDialog({
  open,
  onOpenChange,
}: PaymentConfirmationDialogProps) {
  const { t } = useTranslation();

  const handleRefreshPage = () => {
    window.location.reload();
  };

  const handleNotPaid = () => {
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t("plans.payment_confirmation")}</DialogTitle>
        </DialogHeader>

        <div className="py-4">
          <div className="text-center mb-6">
            <div className="text-sm text-gray-600 mb-4">
              {t("plans.payment_confirmation_message")}
            </div>
          </div>

          <div className="space-y-3">
            <Button
              onClick={handleRefreshPage}
              className="w-full"
            >
              {t("plans.yes_refresh_page")}
            </Button>

            <Button
              onClick={handleNotPaid}
              variant="outline"
              className="w-full"
            >
              {t("plans.no_not_yet")}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}