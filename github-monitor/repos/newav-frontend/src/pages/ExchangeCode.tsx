import { useTranslation } from "react-i18next";
import { ArrowLeft } from "lucide-react";
import { useNavigate } from "react-router";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useState } from "react";
import { redeemCode } from "@/services/user.service";
import { toast } from "sonner";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";
import { useRedeemRecord } from "@/hooks/user/useRedeemRecord";
import { format } from "date-fns";
import { useQueryClient } from "@tanstack/react-query";

export default function ExchangeCode() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [exchangeCode, setExchangeCode] = useState("");
  const [isExchanging, setIsExchanging] = useState(false);
  const {
    data: redeemRecordData,
    isPending: isLoadingHistory,
    isError: isHistoryError,
  } = useRedeemRecord();

  const redeemHistory = redeemRecordData?.data?.data || [];

  const handleClearAll = () => {
    setExchangeCode("");
  };

  const handleExchange = async () => {
    if (!exchangeCode.trim()) return;

    setIsExchanging(true);
    try {
      const response = await redeemCode({ code: exchangeCode.trim() });

      if (response.code === 1) {
        toast.success(t("exchange_code.success"));
        setExchangeCode("");
        // Refresh redeem record and user profile on successful redeem
        await queryClient.invalidateQueries({ queryKey: ["redeemRecord"] });
        await queryClient.invalidateQueries({ queryKey: ["userInfo"] });
      } else if (response.code === 2026) {
        toast.error(t("exchange_code.code_expired"));
      } else if (response.code === 2024) {
        toast.error(t("exchange_code.code_already_used"));
      } else {
        toast.error(t("exchange_code.failed"));
      }
    } catch (error) {
      console.error("Exchange failed:", error);
      toast.error(t("exchange_code.failed"));
    } finally {
      setIsExchanging(false);
    }
  };

  return (
    <div className="flex flex-col min-h-screen bg-background">
      {/* Header */}
      <div className="sticky top-0 z-10 flex items-center gap-4 px-4 py-3 bg-background border-b">
        <Button
          variant="ghost"
          size="icon"
          onClick={() => navigate(-1)}
          className="h-8 w-8"
        >
          <ArrowLeft className="h-5 w-5" />
        </Button>
        <h1 className="text-lg font-semibold">
          {t("exchange_code.page_title")}
        </h1>
      </div>

      {/* Content */}
      <div className="flex-1 p-4">
        <div className="space-y-3 bg-card rounded-lg p-4 border">
          <div className="space-y-2">
            <label className="text-sm font-medium">
              {t("exchange_code.username_label")}
            </label>
            <Input
              value={exchangeCode}
              onChange={(e) => setExchangeCode(e.target.value)}
              placeholder={t("exchange_code.input_placeholder")}
              className="w-full"
            />
          </div>

          <div className="flex gap-3">
            <Button
              variant="secondary"
              onClick={handleClearAll}
              className="flex-1 rounded-full"
              disabled={isExchanging}
            >
              {t("exchange_code.clear_all")}
            </Button>
            <Button
              onClick={handleExchange}
              className="flex-1 rounded-full bg-purple-600 hover:bg-purple-700"
              disabled={!exchangeCode.trim() || isExchanging}
            >
              {isExchanging
                ? t("exchange_code.exchanging")
                : t("exchange_code.exchange")}
            </Button>
          </div>
        </div>
      </div>

      {/* Redeem History Section */}
      <div className="px-4 pb-4">
        <h2 className="text-lg font-semibold mb-3">
          {t("exchange_code.history_title")}
        </h2>

        {isLoadingHistory ? (
          <div className="text-center py-8 text-muted-foreground">
            {t("exchange_code.loading_history")}
          </div>
        ) : isHistoryError ? (
          <div className="text-center py-8 text-destructive">
            {t("exchange_code.history_error")}
          </div>
        ) : redeemHistory.length === 0 ? (
          <div className="text-center py-8 text-muted-foreground">
            {t("exchange_code.no_history")}
          </div>
        ) : (
          <div className="space-y-2">
            {redeemHistory.map((record) => (
              <div key={record.id} className="bg-card rounded-lg p-4 border">
                <div className="flex justify-between items-start gap-4">
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-sm break-all">
                      {record.batch}
                    </p>
                    <p className="text-sm text-muted-foreground mt-1">
                      {format(
                        new Date(record.exchange_time * 1000),
                        "yyyy-MM-dd HH:mm",
                      )}
                    </p>
                  </div>
                  <div className="text-right flex-shrink-0">
                    {record.day_value > 0 && (
                      <p className="text-sm font-medium text-primary">
                        {record.day_value} {t("exchange_code.days")}
                      </p>
                    )}
                    {record.diamonds > 0 && (
                      <p className="text-sm text-muted-foreground">
                        {record.diamonds} {t("exchange_code.diamonds")}
                      </p>
                    )}
                    {record.points > 0 && (
                      <p className="text-sm text-muted-foreground">
                        {record.points} {t("exchange_code.points")}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="py-4 px-4">
        <RecommendedHorizontalList />
      </div>
    </div>
  );
}
