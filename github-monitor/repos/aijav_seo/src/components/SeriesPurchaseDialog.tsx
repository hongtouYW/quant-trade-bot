import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { AlertCircle, LoaderCircle } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { useUser } from "@/contexts/UserContext";
import { usePurchaseGroup } from "@/hooks/group/usePurchaseGroup";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import diamondIcon from "@/assets/diamond-icon.png";

interface SeriesPurchaseDialogProps {
  open: boolean;
  onClose: () => void;
  seriesId: number;
  seriesTitle: string;
  seriesImage: string;
  diamondCost: number;
}

export function SeriesPurchaseDialog({
  open,
  onClose,
  seriesId,
  seriesTitle,
  seriesImage,
  diamondCost,
}: SeriesPurchaseDialogProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useUser();
  const { executeWithAuth } = useAuthAction();
  const { mutate: purchaseSeries, isPending } = usePurchaseGroup();

  const userDiamonds = user?.coin || 0;
  const hasEnoughDiamonds = userDiamonds >= diamondCost;

  const handleConfirm = () => {
    executeWithAuth(() => {
      if (isPending) return;

      purchaseSeries(
        { gid: seriesId },
        {
          onSuccess: () => {
            // Close dialog on successful purchase
            onClose();
          },
        },
      );
    });
  };

  const handleTopUp = () => {
    navigate("/plans");
    onClose();
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md bg-white rounded-2xl p-0 overflow-hidden border-none">
        <DialogHeader className="p-6 pb-4 relative">
          <DialogTitle className="text-center text-lg font-semibold text-black">
            {t("series_purchase_dialog.title")}
          </DialogTitle>
        </DialogHeader>

        <div className="px-6 pb-6">
          {hasEnoughDiamonds ? (
            // User has enough diamonds
            <div className="flex flex-col items-center space-y-6">
              {/* Series Image */}
              <div className="w-20 h-20 rounded-2xl overflow-hidden">
                <img
                  src={seriesImage}
                  alt={seriesTitle}
                  className="w-full h-full object-cover"
                />
              </div>

              {/* Series Info */}
              <div className="text-center space-y-2">
                <h3 className="text-lg font-medium text-black line-clamp-2">
                  {seriesTitle}
                </h3>
                <div className="flex items-center justify-center gap-1 text-lg font-medium text-black">
                  <span>{t("series_purchase_dialog.series_requires")}</span>
                  <img
                    src={diamondIcon}
                    className="w-5 h-5"
                    alt="diamond icon"
                  />
                  <span>{diamondCost}</span>
                  <span>{t("series_purchase_dialog.diamonds_unit")}</span>
                </div>
                <div className="flex items-center justify-center gap-1 text-base text-gray-600">
                  <span>{t("series_purchase_dialog.remaining_diamonds_label")}</span>
                  <img
                    src={diamondIcon}
                    className="w-4 h-4"
                    alt="diamond icon"
                  />
                  <span>{userDiamonds}</span>
                  <span>{t("series_purchase_dialog.diamonds_count_unit")}</span>
                </div>
              </div>

              {/* Confirm Button */}
              <Button
                onClick={handleConfirm}
                disabled={isPending}
                className="w-full h-12 bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white font-semibold rounded-full disabled:opacity-50 flex items-center justify-center gap-2"
              >
                {isPending ? (
                  <>
                    <LoaderCircle className="w-5 h-5 animate-spin" />
                    {t("series_purchase_dialog.purchasing")}
                  </>
                ) : (
                  <>{t("series_purchase_dialog.confirm_purchase")}</>
                )}
              </Button>
            </div>
          ) : (
            // User doesn't have enough diamonds
            <div className="flex flex-col items-center space-y-6">
              {/* Warning Icon */}
              <div className="w-20 h-20 bg-orange-400 rounded-full flex items-center justify-center">
                <AlertCircle className="w-10 h-10 text-white" />
              </div>

              {/* Series Info */}
              <div className="text-center space-y-2">
                <h3 className="text-lg font-medium text-black line-clamp-2">
                  {seriesTitle}
                </h3>
                <div className="flex items-center justify-center gap-1 text-lg font-medium text-black">
                  <span>{t("series_purchase_dialog.series_requires")}</span>
                  <img
                    src={diamondIcon}
                    className="w-5 h-5"
                    alt="diamond icon"
                  />
                  <span>{diamondCost}</span>
                  <span>{t("series_purchase_dialog.diamonds_unit")}</span>
                </div>
                <p className="text-base">
                  {t("series_purchase_dialog.insufficient_diamonds", {
                    count: userDiamonds,
                  })}
                </p>
              </div>

              {/* Top-up Hint */}
              <div className="flex items-center space-x-2 text-gray-500">
                <div className="transform rotate-180">
                  <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    className="text-gray-400"
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
                  {t("series_purchase_dialog.please_top_up_diamonds")}
                </span>
              </div>

              {/* Top-up Button */}
              <div className="w-full space-y-3">
                <Button
                  onClick={handleTopUp}
                  className="w-full h-12 bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white font-semibold rounded-full"
                >
                  {t("series_purchase_dialog.top_up_now")}
                </Button>
              </div>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
