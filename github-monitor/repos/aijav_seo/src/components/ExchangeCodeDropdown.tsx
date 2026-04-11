"use client";

import * as React from "react";
import { useTranslation } from "react-i18next";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { QrCode } from "lucide-react";
import { SidebarMenuButton, SidebarMenuItem, useSidebar } from "@/components/ui/sidebar";
import { redeemCode } from "@/services/user.service";
import { toast } from "sonner";
import { useNavigate } from "react-router";
import { useRedeemRecord } from "@/hooks/user/useRedeemRecord";
import { format } from "date-fns";
import { useQueryClient } from "@tanstack/react-query";

export function ExchangeCodeDropdown() {
  const { t } = useTranslation();
  const { isMobile, setOpenMobile } = useSidebar();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [exchangeCode, setExchangeCode] = React.useState("");
  const [isExchanging, setIsExchanging] = React.useState(false);
  const { data: redeemRecordData, isPending: isLoadingHistory, isError: isHistoryError } = useRedeemRecord();

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

  const handleClick = () => {
    if (isMobile) {
      navigate("/exchange-code");
      setOpenMobile(false);
    }
  };

  // On mobile, just render the button that navigates to the page
  if (isMobile) {
    return (
      <SidebarMenuItem className="items-center">
        <div className="cursor-pointer" onClick={handleClick}>
          <SidebarMenuButton size="lg" asChild tooltip={t("sidebar.exchange_code")}>
            <div>
              <div className="w-6 h-6">
                <QrCode />
              </div>
              <span className="font-medium text-base">
                {t("sidebar.exchange_code")}
              </span>
            </div>
          </SidebarMenuButton>
        </div>
      </SidebarMenuItem>
    );
  }

  // On desktop, render the popover
  return (
    <SidebarMenuItem className="items-center">
      <Popover>
        <PopoverTrigger asChild>
          <div className="cursor-pointer">
            <SidebarMenuButton size="lg" asChild tooltip={t("sidebar.exchange_code")}>
              <div>
                <div className="w-6 h-6">
                  <QrCode />
                </div>
                <span className="font-medium text-base">
                  {t("sidebar.exchange_code")}
                </span>
              </div>
            </SidebarMenuButton>
          </div>
        </PopoverTrigger>
        <PopoverContent side="right" sideOffset={40} className="relative w-80">
          {/* Arrow */}
          <div 
            className="absolute left-[-12px] top-1/2 -translate-y-1/2 w-0 h-0 border-y-12 border-y-transparent border-r-12" 
            style={{ borderRightColor: '#BA12D3' }}
          />

          {/* Content */}
          <div className="space-y-4">
            {/* Title */}
            <div
              className="text-base font-medium text-white px-4 py-2 -mx-4 -mt-6 mb-2 rounded-t-lg"
              style={{ backgroundColor: "#BA12D3" }}
            >
              {t("exchange_code.title")}
            </div>

            {/* Input Section */}
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

            {/* Action Buttons */}
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

            {/* Redeem History Section */}
            <div className="border-t pt-4 mt-4">
              <h3 className="text-sm font-semibold mb-2">
                {t("exchange_code.history_title")}
              </h3>

              {isLoadingHistory ? (
                <div className="text-center py-4 text-sm text-muted-foreground">
                  {t("exchange_code.loading_history")}
                </div>
              ) : isHistoryError ? (
                <div className="text-center py-4 text-sm text-destructive">
                  {t("exchange_code.history_error")}
                </div>
              ) : redeemHistory.length === 0 ? (
                <div className="text-center py-4 text-sm text-muted-foreground">
                  {t("exchange_code.no_history")}
                </div>
              ) : (
                <div className="space-y-2 max-h-48 overflow-y-auto">
                  {redeemHistory.slice(0, 3).map((record) => (
                    <div
                      key={record.id}
                      className="bg-card rounded-lg p-3 border text-xs"
                    >
                      <div className="flex justify-between items-start gap-2">
                        <div className="flex-1 min-w-0">
                          <p className="font-medium break-all line-clamp-1">
                            {record.batch}
                          </p>
                          <p className="text-muted-foreground mt-0.5">
                            {format(new Date(record.exchange_time * 1000), "yyyy-MM-dd HH:mm")}
                          </p>
                        </div>
                        <div className="text-right flex-shrink-0">
                          {record.day_value > 0 && (
                            <p className="font-medium text-primary">
                              {record.day_value} {t("exchange_code.days")}
                            </p>
                          )}
                          {record.diamonds > 0 && (
                            <p className="text-muted-foreground">
                              {record.diamonds} {t("exchange_code.diamonds")}
                            </p>
                          )}
                          {record.points > 0 && (
                            <p className="text-muted-foreground">
                              {record.points} {t("exchange_code.points")}
                            </p>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                  {redeemHistory.length > 3 && (
                    <button
                      onClick={() => {
                        navigate("/exchange-code");
                      }}
                      className="w-full text-center py-2 text-xs text-primary hover:underline"
                    >
                      {t("exchange_code.view_all")}
                    </button>
                  )}
                </div>
              )}
            </div>
          </div>
        </PopoverContent>
      </Popover>
    </SidebarMenuItem>
  );
}
