import { useTranslation } from "react-i18next";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Bell } from "lucide-react";
import { SidebarMenuButton, SidebarMenuItem, useSidebar } from "@/components/ui/sidebar";
import { useNotices } from "@/hooks/notice/useNotices";
import type { Notice } from "@/types/notice.types.ts";
import { useNavigate } from "react-router";

export function NotificationDropdown() {
  const { t } = useTranslation();
  const { isMobile, setOpenMobile } = useSidebar();
  const navigate = useNavigate();
  const { data: noticesData } = useNotices();

  // Use actual notices data but return empty array for now as requested
  const notices = (noticesData?.data || []) as Notice[];
  // Override to empty array as requested
  const displayNotices = notices.length > 0 ? [] : [] as Notice[];

  const handleClick = () => {
    if (isMobile) {
      navigate("/notifications");
      setOpenMobile(false);
    }
  };

  // On mobile, just render the button that navigates to the page
  if (isMobile) {
    return (
      <SidebarMenuItem className="items-center">
        <div className="cursor-pointer" onClick={handleClick}>
          <SidebarMenuButton size="lg" asChild tooltip={t("sidebar.notifications")}>
            <div>
              <div className="w-6 h-6">
                <Bell />
              </div>
              <span className="font-medium text-base">
                {t("sidebar.notifications")}
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
            <SidebarMenuButton size="lg" asChild tooltip={t("sidebar.notifications")}>
              <div>
                <div className="w-6 h-6">
                  <Bell />
                </div>
                <span className="font-medium text-base">
                  {t("sidebar.notifications")}
                </span>
              </div>
            </SidebarMenuButton>
          </div>
        </PopoverTrigger>
        <PopoverContent
          side="right"
          sideOffset={40}
          className="relative w-80 p-0"
        >
          {/* Arrow */}
          <div
            className="absolute left-[-12px] top-1/2 -translate-y-1/2 w-0 h-0 border-y-12 border-y-transparent border-r-12"
            style={{ borderRightColor: "#BA12D3" }}
          />

          {/* Content */}
          <div className="space-y-4">
            {/* Title */}
            <div
              className="text-base font-medium text-white px-4 py-2 rounded-t-lg"
              style={{ backgroundColor: "#BA12D3" }}
            >
              {t("notifications.title")}
            </div>

            {/* Notices List */}
            <div className="px-4 pb-4">
              {displayNotices.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  <div className="text-sm">{t("common.no_data")}</div>
                </div>
              ) : (
                <div className="space-y-3">
                  {displayNotices.map((notice, index) => (
                    <div
                      key={index}
                      className="border-b border-border pb-3 last:border-b-0"
                    >
                      <h4 className="font-medium text-sm text-foreground mb-1">
                        {notice.title}
                      </h4>
                      <div
                        className="text-xs text-muted-foreground prose prose-sm max-w-none"
                        dangerouslySetInnerHTML={{ __html: notice.content }}
                      />
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </PopoverContent>
      </Popover>
    </SidebarMenuItem>
  );
}
