import { Button } from "@/components/ui/button.tsx";
import {
  Lock,
  LogIn,
  AlertTriangle,
  ShoppingCart,
  Package,
  Crown,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import type { VideoGroup } from "@/types/video.types";

interface VideoAuthOverlayProps {
  onLoginClick: () => void;
  errorType?:
    | "auth"
    | "purchase"
    | "purchase-or-vip"
    | "series"
    | "server"
    | "vip-expired";
  errorMessage?: string;
  onPurchaseClick?: () => void;
  videoGroup?: VideoGroup[];
  vipVideoDuration?: string;
}

export const VideoAuthOverlay = ({
  onLoginClick,
  errorType = "auth",
  onPurchaseClick,
  videoGroup,
  vipVideoDuration,
}: VideoAuthOverlayProps) => {
  const { t } = useTranslation();
  const navigate = useNavigate();

  // Get the first video group ID if available
  const videoGroupId =
    videoGroup && videoGroup.length > 0 ? videoGroup[0].id : null;

  // Calculate year range based on vipVideoDuration (in months)
  const getVipYearRange = () => {
    if (!vipVideoDuration) return { startYear: "", endYear: "" };

    const durationMonths = parseInt(vipVideoDuration, 10);
    if (isNaN(durationMonths)) return { startYear: "", endYear: "" };

    const now = new Date();
    const endYear = now.getFullYear();
    const startYear = endYear - Math.floor(durationMonths / 12);

    return { startYear: startYear.toString(), endYear: endYear.toString() };
  };

  const { startYear, endYear } = getVipYearRange();

  const isPurchaseError = errorType === "purchase";
  const isPurchaseOrVipError = errorType === "purchase-or-vip";
  const isSeriesError = errorType === "series";
  const isServerError = errorType === "server";
  const isVipExpired = errorType === "vip-expired";

  return (
    <div className="absolute inset-0 bg-black/80 flex items-center justify-center z-40 rounded-none md:rounded-lg">
      <div className="text-center text-white space-y-2 sm:space-y-3 md:space-y-6 p-4 sm:p-6 md:p-8 max-w-sm mx-auto">
        <div className="flex justify-center mb-2 sm:mb-3 md:mb-4">
          {isServerError ? (
            <AlertTriangle className="w-10 h-10 sm:w-12 sm:h-12 md:w-16 md:h-16 text-red-400" />
          ) : isVipExpired ? (
            <Crown className="w-10 h-10 sm:w-12 sm:h-12 md:w-16 md:h-16 text-yellow-500" />
          ) : isPurchaseError || isPurchaseOrVipError ? (
            <ShoppingCart className="w-10 h-10 sm:w-12 sm:h-12 md:w-16 md:h-16 text-gray-300" />
          ) : isSeriesError ? (
            <Package className="w-10 h-10 sm:w-12 sm:h-12 md:w-16 md:h-16 text-gray-300" />
          ) : (
            <Lock className="w-10 h-10 sm:w-12 sm:h-12 md:w-16 md:h-16 text-gray-300" />
          )}
        </div>
        <h3 className="text-base sm:text-lg md:text-xl font-semibold px-2">
          {isVipExpired
            ? t("auth.vip_expired_title")
            : isPurchaseError
              ? t("auth.purchase_required_title")
              : isPurchaseOrVipError
                ? t("auth.purchase_or_vip_required_title")
                : isSeriesError
                  ? t("auth.series_required_title")
                  : isServerError
                    ? t("auth.server_error_title")
                    : t("auth.login_to_watch_title")}
        </h3>
        {isPurchaseError && (
          <p className="text-gray-300 text-xs sm:text-sm px-2">
            {startYear && endYear
              ? t("auth.purchase_required_message", { startYear, endYear })
              : t("auth.purchase_required_message")}
          </p>
        )}
        {isSeriesError && (
          <p className="text-gray-300 text-xs sm:text-sm px-2">
            {t("auth.series_required_message")}
          </p>
        )}
        {errorType === "auth" && (
          <Button
            onClick={onLoginClick}
            className="bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white px-4 sm:px-5 md:px-6 py-1.5 sm:py-2 rounded-full font-medium text-sm sm:text-base"
          >
            <LogIn className="w-3 h-3 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" />
            {t("auth.login_now")}
          </Button>
        )}
        {errorType === "purchase" && (
          <Button
            onClick={onPurchaseClick}
            className="bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white px-3 py-1.5 rounded-full font-medium text-sm"
          >
            <ShoppingCart className="w-3 h-3 mr-1.5" />
            {t("auth.purchase_video")}
          </Button>
        )}
        {errorType === "purchase-or-vip" && (
          <div className="flex flex-col gap-3">
            <Button
              onClick={onPurchaseClick}
              className="bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white px-3 py-1.5 rounded-full font-medium text-sm"
            >
              <ShoppingCart className="w-3 h-3 mr-1.5" />
              {t("auth.purchase_video")}
            </Button>
            <div className="text-center">
              <p className="text-gray-300 text-xs mb-2">{t("common.or")}</p>
              <Button
                variant="outline"
                onClick={() => navigate("/plans")}
                className="text-accent-foreground border-white/30 hover:bg-white/90 px-3 py-1.5 rounded-full w-full text-sm"
              >
                <Crown className="w-3 h-3 mr-1.5 stroke-yellow-500" />
                {t("auth.upgrade_to_vip")}
              </Button>
            </div>
          </div>
        )}
        {errorType === "series" && (
          <Button
            onClick={() => {
              if (videoGroupId) {
                navigate(`/series/${videoGroupId}`);
              } else {
                navigate("/series");
              }
            }}
            className="bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white px-4 sm:px-5 md:px-6 py-1.5 sm:py-2 rounded-full font-medium text-sm sm:text-base"
          >
            <Package className="w-3 h-3 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" />
            {t("auth.purchase_series")}
          </Button>
        )}
        {isVipExpired && (
          <div className="flex flex-col gap-3">
            <Button
              onClick={onPurchaseClick}
              className="bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white px-3 py-1.5 rounded-full font-medium text-sm"
            >
              <ShoppingCart className="w-3 h-3 mr-1.5" />
              {t("auth.purchase_video")}
            </Button>
            <div className="text-center">
              <p className="text-gray-300 text-xs mb-2">{t("common.or")}</p>
              <Button
                variant="outline"
                onClick={() => navigate("/plans")}
                className="text-accent-foreground border-white/30 hover:bg-white/90 px-3 py-1.5 rounded-full w-full text-sm"
              >
                <Crown className="w-3 h-3 mr-1.5 stroke-yellow-500" />
                {t("auth.renew_vip")}
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
