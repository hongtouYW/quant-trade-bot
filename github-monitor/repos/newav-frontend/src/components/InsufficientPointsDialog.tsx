import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { AlertCircle } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";

interface InsufficientPointsDialogProps {
  open: boolean;
  onClose: () => void;
  userPoints?: number;
  voucherCost?: number;
  onCloseAll?: () => void;
}

export function InsufficientPointsDialog({
  open,
  onClose,
  userPoints = 0,
  voucherCost = 1,
  onCloseAll,
}: InsufficientPointsDialogProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const handleTopUpNow = () => {
    if (onCloseAll) {
      onCloseAll();
    } else {
      onClose();
    }
    navigate("/plans");
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md bg-card text-card-foreground rounded-2xl p-0 overflow-hidden border border-border">
        <DialogHeader className="p-6 pb-4 relative">
          <DialogTitle className="text-center text-lg font-semibold">
            {t("voucher_dialog.title")}
          </DialogTitle>
        </DialogHeader>

        <div className="px-6 pb-6">
          <div className="flex flex-col items-center space-y-6">
            <div className="w-20 h-20 bg-orange-400 rounded-full flex items-center justify-center">
              <AlertCircle className="w-10 h-10 text-white" />
            </div>

            <div className="text-center space-y-2">
              <p className="text-lg font-medium">
                {t("voucher_dialog.will_use_voucher", { count: voucherCost })}
              </p>
              <p className="text-base">
                {t("voucher_dialog.you_currently_have")}{" "}
                <span className="text-red-500 font-semibold">{userPoints}</span>{" "}
                {t("voucher_dialog.viewing_vouchers")}，
                {t("voucher_dialog.cannot_watch")}
              </p>
            </div>

            <div className="flex items-center space-x-2 text-muted-foreground">
              <div className="transform rotate-180">
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  className="text-muted-foreground/80"
                >
                  <path
                    d="M7 17L17 7M17 7H7M17 7V17"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </div>
              <span className="text-sm">
                {t("voucher_dialog.please_top_up_to_continue")}
              </span>
            </div>

            <div className="w-full space-y-3">
              <Button
                onClick={handleTopUpNow}
                className="w-full h-12 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-full"
              >
                {t("voucher_dialog.top_up_now")}
              </Button>
              <p className="text-center text-sm text-muted-foreground">
                {t("voucher_dialog.vip_unlimited")}
              </p>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
