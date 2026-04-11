import { CircleArrowUp, CirclePlus, Gem, IdCard, Ticket } from "lucide-react";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip.tsx";
import { SidebarMenuItem, useSidebar } from "@/components/ui/sidebar";
import { cn } from "@/lib/utils.ts";
import { useTranslation } from "react-i18next";
import type { SafeUser } from "@/contexts/UserContext.tsx";
import { formattedVipTime } from "@/utils/common.ts";
import { useNavigate } from "react-router";
import { VipButton } from "@/components/vip-button.tsx";
import {
  PLAN_STACKED_SECTION_IDS,
  type PlanStackedSectionId,
} from "@/constants/planSections.ts";

interface UserStatsSectionProps {
  user?: SafeUser | null;
  isCollapsed: boolean;
}

export const UserStatsSection = ({
  user,
  isCollapsed,
}: UserStatsSectionProps) => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { isMobile, toggleSidebar } = useSidebar();

  // On mobile, never show collapsed view (mobile sidebar doesn't collapse)
  const showCollapsed = isCollapsed && !isMobile;

  function handleNavigateToPlans(focusPlanId?: PlanStackedSectionId) {
    const navigationOptions =
      focusPlanId !== undefined ? { state: { focusPlanId } } : undefined;

    navigate("/plans", navigationOptions);
    if (isMobile) {
      toggleSidebar();
    }
  }

  return (
    <SidebarMenuItem>
      {showCollapsed ? (
        // Collapsed state: Icon-only with tooltips
        <div className="flex flex-col gap-1 py-1">
          {/* Points - Icon only with tooltip */}
          <Tooltip>
            <TooltipTrigger asChild>
              <div className="w-8 h-8 flex items-center justify-center rounded-md hover:bg-sidebar-accent">
                <Ticket className="w-6 h-6" />
              </div>
            </TooltipTrigger>
            <TooltipContent side="right">
              {user?.point} {t("sidebar.points")}
            </TooltipContent>
          </Tooltip>

          {/* Diamonds - Icon only with tooltip */}
          <Tooltip>
            <TooltipTrigger asChild>
              <div className="w-8 h-8 flex items-center justify-center rounded-md hover:bg-sidebar-accent">
                <Gem className="w-6 h-6" />
              </div>
            </TooltipTrigger>
            <TooltipContent side="right">
              {user?.coin} {t("sidebar.diamonds")}
            </TooltipContent>
          </Tooltip>

          {/* VIP Status - Icon only with tooltip */}
          <Tooltip>
            <TooltipTrigger asChild>
              <div
                className={cn(
                  "w-8 h-8 flex items-center justify-center rounded-md hover:bg-sidebar-accent",
                  user?.is_vip === 1 ? "bg-vip-background" : "",
                )}
              >
                <IdCard
                  className={cn(
                    "w-6 h-6",
                    user?.is_vip === 1 ? "text-vip-text" : "",
                  )}
                />
              </div>
            </TooltipTrigger>
            <TooltipContent side="right">
              <div className="flex flex-col">
                <span>
                  {user?.is_vip === 1
                    ? t("sidebar.vip_member")
                    : t("sidebar.regular_member")}
                </span>
                {user?.vip_end_time && (
                  <span className="text-xs text-muted-foreground">
                    {formattedVipTime(user?.vip_end_time)}
                  </span>
                )}
              </div>
            </TooltipContent>
          </Tooltip>
        </div>
      ) : (
        // Expanded state: Full display with text
        <div className="flex flex-col gap-0">
          {/* Points */}
          <div className="flex items-center gap-2 px-2 py-1.5">
            <div className="w-6 h-6">
              <Ticket />
            </div>
            <div className="flex justify-between rounded-full border-primary border bg-secondary  pl-3 pr-1.5 py-1 w-full text-sm leading-tight">
              <span className="truncate font-medium">
                {user?.point} {t("sidebar.points")}
              </span>
              <CirclePlus
                onClick={() =>
                  handleNavigateToPlans(PLAN_STACKED_SECTION_IDS.points)
                }
                className="size-5 stroke-primary cursor-pointer"
              />
            </div>
          </div>

          {/* Diamonds */}
          <div className="flex items-center gap-2 px-2 py-1.5">
            <div className="w-6 h-6">
              <Gem />
            </div>
            <div className="flex justify-between rounded-full border-primary border bg-secondary pl-3 pr-1.5 py-1 w-full text-sm leading-tight">
              <span className="truncate font-medium">
                {user?.coin} {t("sidebar.diamonds")}
              </span>
              <CirclePlus
                onClick={() =>
                  handleNavigateToPlans(PLAN_STACKED_SECTION_IDS.diamonds)
                }
                className="size-5 stroke-primary cursor-pointer"
              />
            </div>
          </div>

          {/* VIP Status */}
          <div className="flex items-center gap-2 px-2 py-1.5">
            <div className="w-6 h-6">
              <IdCard />
            </div>
            <div
              className={cn(
                "rounded-full border bg-muted pl-3 pr-1.5 py-1 w-full text-sm leading-tight",
                user?.is_vip === 1
                  ? "border-vip-border bg-vip-background"
                  : "border-muted-foreground",
              )}
            >
              <div className="font-medium flex items-center justify-between gap-1">
                <span
                  className={cn(
                    "flex-shrink-0",
                    user?.is_vip === 1 ? "text-vip-text" : "",
                  )}
                >
                  {user?.is_vip === 1
                    ? t("sidebar.vip_member")
                    : t("sidebar.regular_member")}
                </span>
                {!user?.vip_end_time || user?.is_vip === 0 ? (
                  <CircleArrowUp
                    onClick={() =>
                      handleNavigateToPlans(PLAN_STACKED_SECTION_IDS.vip)
                    }
                    className="size-5 flex-shrink-0 stroke-muted-foreground cursor-pointer"
                  />
                ) : (
                  <span
                    className={cn(
                      "text-xs flex-shrink-0",
                      user?.is_vip === 1
                        ? "text-vip-text-secondary"
                        : "text-muted-foreground",
                    )}
                  >
                    {formattedVipTime(user?.vip_end_time)}
                  </span>
                )}
              </div>
            </div>
          </div>

          <div className="mt-2 px-2 py-1.5">
            <VipButton
              onClick={() =>
                handleNavigateToPlans(PLAN_STACKED_SECTION_IDS.vip)
              }
              sheenImage="/vip_btn_bg.png"
              className="w-full text-base"
            >
              {t("navbar.plans")}
            </VipButton>
          </div>
        </div>
      )}
    </SidebarMenuItem>
  );
};
