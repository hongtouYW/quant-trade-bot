import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { AlertCircle, Ticket } from "lucide-react";
import { Checkbox } from "@/components/ui/checkbox";
import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { InsufficientPointsDialog } from "./InsufficientPointsDialog";
import { useUser } from "@/contexts/UserContext";
import { useNavigate } from "react-router";
import { useVoucherPurchase } from "@/hooks/video/useVoucherPurchase";
interface VoucherDialogProps {
  open: boolean;
  onClose: () => void;
  voucherCost?: number;
  vid: number;
}

export function VoucherDialog({
  open,
  onClose,
  voucherCost = 1,
  vid,
}: VoucherDialogProps) {
  const { t } = useTranslation();
  const { user } = useUser();
  const [dontAskAgain, setDontAskAgain] = useState(false);
  const [showInsufficientPointsDialog, setShowInsufficientPointsDialog] =
    useState(false);
  const navigate = useNavigate();
  const { purchaseVoucher, isPurchasing } = useVoucherPurchase({
    vid,
    videoKey: vid ? String(vid) : undefined,
  });

  const userPoints = user?.point;
  const hasEnoughPoints = (userPoints ?? 0) >= voucherCost;

  useEffect(() => {
    if (open) {
      const storedPreference = localStorage.getItem("voucher_dont_ask") === "true";
      setDontAskAgain(storedPreference);
      setShowInsufficientPointsDialog(false);
    }
  }, [open]);

  const handleConfirm = async () => {
    if (!vid || isPurchasing) return;

    if (dontAskAgain) {
      localStorage.setItem("voucher_dont_ask", "true");
    } else {
      localStorage.removeItem("voucher_dont_ask");
    }

    const result = await purchaseVoucher();

    if (result.status === "success") {
      onClose();
    } else if (result.status === "insufficient") {
      setShowInsufficientPointsDialog(true);
    }
  };

  const handleTopUp = () => {
    onClose();
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
          {hasEnoughPoints ? (
            // User has enough points
            <div className="flex flex-col items-center space-y-6">
              <div className="w-20 h-20 rounded-2xl flex items-center justify-center">
                <Ticket className="size-32 text-primary" />
              </div>

              <div className="text-center space-y-2">
                <p className="text-lg font-medium">
                  {t("voucher_dialog.will_use_voucher", { count: voucherCost })}
                </p>
                <p className="text-base text-muted-foreground">
                  {t("voucher_dialog.remaining_vouchers", {
                    count: userPoints,
                  })}
                </p>
              </div>

              <div className="flex items-center space-x-2">
                <Checkbox
                  id="dont-ask"
                  checked={dontAskAgain}
                  onCheckedChange={(checked) =>
                    setDontAskAgain(checked === true)
                  }
                />
                <label
                  htmlFor="dont-ask"
                  className="text-sm text-muted-foreground cursor-pointer"
                >
                  {t("voucher_dialog.dont_ask_again")}
                </label>
              </div>

              <Button
                onClick={() => void handleConfirm()}
                disabled={isPurchasing}
                className="w-full h-12 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-full disabled:opacity-50"
              >
                {isPurchasing
                  ? t("voucher_dialog.purchasing")
                  : t("voucher_dialog.confirm_watch")}
              </Button>
            </div>
          ) : (
            // User doesn't have enough points
            <div className="flex flex-col items-center space-y-6">
              <div className="w-20 h-20 bg-orange-400 rounded-full flex items-center justify-center">
                <AlertCircle className="w-10 h-10 text-white" />
              </div>

              <div className="text-center space-y-2">
                <p className="text-lg font-medium">
                  {t("voucher_dialog.will_use_voucher", { count: voucherCost })}
                </p>
                <p className="text-base">
                  {t("voucher_dialog.insufficient_vouchers", {
                    count: userPoints,
                  })}
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
                  {t("voucher_dialog.please_top_up")}
                </span>
              </div>

              <div className="w-full space-y-3">
                <Button
                  onClick={handleTopUp}
                  className="w-full h-12 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-full"
                >
                  {t("voucher_dialog.top_up_now")}
                </Button>
                <p className="text-center text-sm text-muted-foreground">
                  {t("voucher_dialog.vip_unlimited")}
                </p>
              </div>
            </div>
          )}
        </div>
      </DialogContent>

      <InsufficientPointsDialog
        open={showInsufficientPointsDialog}
        onClose={() => setShowInsufficientPointsDialog(false)}
        userPoints={userPoints}
        voucherCost={voucherCost}
      />
    </Dialog>
  );
}
