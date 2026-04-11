import * as React from "react";
import { useState } from "react";
import {
  BookMarked,
  Headset,
  History,
  ListTodo,
  LogOut,
  MonitorPlay,
  Settings,
  UserRoundCheck,
} from "lucide-react";
import { NavProjects } from "@/components/nav-projects";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import { Separator } from "@/components/ui/separator.tsx";
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar.tsx";
import Logo from "@/components/logo.tsx";
import guestIcon from "@/assets/guest-icon.png";
import userAvatar from "@/assets/user-avatar.png";
import { useUserInfo } from "@/hooks/user/useUserInfo.ts";
import { getYear } from "date-fns";
import { useAuth } from "@/hooks/auth/useAuth";
import { useUser } from "@/contexts/UserContext";
import { useTranslation } from "react-i18next";
import { removeAuthToken } from "@/utils/auth";
import { useQueryClient } from "@tanstack/react-query";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu.tsx";
import LanguageSwitcher from "@/components/language-switcher.tsx";
import { Button } from "@/components/ui/button.tsx";
import { ExchangeCodeDropdown } from "@/components/ExchangeCodeDropdown";
import { APP_VERSION } from "@/version";
import { NotificationDropdown } from "@/components/NotificationDropdown";
import { LogoutConfirmationDialog } from "@/components/LogoutConfirmationDialog";
import { UserStatsSection } from "@/components/UserStatsSection";
import { useAuthDialog } from "@/contexts/AuthDialogContext";
import { useConfigContext } from "@/contexts/ConfigContext";
import { useTheme } from "@/components/theme-provider";
import type { TFunction } from "i18next";

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { isAuthenticated } = useAuth();
  const { user } = useUser();
  const { t } = useTranslation();
  const { state, isMobile } = useSidebar();
  const queryClient = useQueryClient();
  const { configList } = useConfigContext();
  const [showLogoutDialog, setShowLogoutDialog] = useState(false);
  useUserInfo(); // This hook handles updating user context automatically

  // Create sidebar navigation data using translations
  const data = {
    projects: [
      {
        name: t("sidebar.name_card"),
        url: "",
        icon: UserRoundCheck,
      },
    ],
    projects2: [
      {
        name: t("sidebar.my_channels"),
        url: "/my-channels",
        icon: MonitorPlay,
      },
      {
        name: t("sidebar.following"),
        url: "/following",
        icon: UserRoundCheck,
      },
      {
        name: t("sidebar.my_favorites"),
        url: "/my-favorites",
        icon: BookMarked,
      },
      {
        name: t("sidebar.watch_history"),
        url: "/watch-history",
        icon: History,
      },
    ],
    projects3: [
      {
        name: t("sidebar.purchased_list"),
        url: "/purchase-history",
        icon: ListTodo,
      },
    ],
  };

  const handleLogout = () => {
    setShowLogoutDialog(true);
  };

  const confirmLogout = () => {
    removeAuthToken();
    // Invalidate all active queries to refresh page data after logout
    queryClient.invalidateQueries({ type: "active" });
  };

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <div className="flex items-center">
                <Avatar className="size-8 rounded-full ring-2 ring-border">
                  <AvatarImage
                    src={isAuthenticated ? userAvatar : guestIcon}
                    alt="placeholder"
                  />
                  <AvatarFallback className="rounded-lg">CN</AvatarFallback>
                </Avatar>
                <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                  <p className="truncate font-medium select-none">
                    {isAuthenticated ? user?.username : t("sidebar.guest")}
                  </p>
                </div>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
          {isAuthenticated ? (
            <UserStatsSection user={user} isCollapsed={state === "collapsed"} />
          ) : null}
        </SidebarMenu>
      </SidebarHeader>
      <Separator className="my-1" />
      <SidebarContent>
        {isAuthenticated ? (
          <>
            <NavProjects projects={data.projects}>
              <>
                <ExchangeCodeDropdown />
                <NotificationDropdown />
              </>
            </NavProjects>
            <Separator className="my-1" />
            <NavProjects projects={data.projects2} />
            <Separator className="my-1" />
            <NavProjects projects={data.projects3} />
          </>
        ) : (
          <NoLoginView isCollapsed={state === "collapsed"} />
        )}

        <Separator className="my-1" />
        <SidebarGroup>
          <SidebarMenu>
            <SidebarMenuItem>
              <DropdownMenu modal={isMobile}>
                <DropdownMenuTrigger asChild>
                  <SidebarMenuButton size="lg" tooltip={t("sidebar.settings")}>
                    <div className="w-6 h-6">
                      <Settings />
                    </div>
                    <span className="font-medium text-base">
                      {t("sidebar.settings")}
                    </span>
                  </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                  className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                  side={isMobile ? "bottom" : "right"}
                  sideOffset={30}
                  align="start"
                >
                  <div className="absolute left-[-8px] top-1/2 -translate-y-1/2 w-0 h-0 border-y-8 border-y-transparent border-r-8 border-r-grey" />
                  <DropdownMenuLabel className="flex justify-between items-center">
                    {t("sidebar.settings")}
                    <span className="text-xs text-muted-foreground">
                      v{APP_VERSION}
                    </span>
                  </DropdownMenuLabel>
                  <DropdownMenuItem
                    className="cursor-default focus:bg-transparent"
                    onSelect={(event) => event.preventDefault()}
                  >
                    <ThemeSettingsItem t={t} />
                  </DropdownMenuItem>
                  <DropdownMenuItem className="cursor-pointer">
                    <LanguageSwitcher
                      className="w-full justify-start"
                      showLabel={true}
                    />
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    className="cursor-pointer"
                    onClick={() => {
                      if (configList?.contact_us) {
                        window.open(
                          configList.contact_us,
                          "_blank",
                          "noopener,noreferrer",
                        );
                      }
                    }}
                  >
                    <Button
                      size="sm"
                      variant="ghost"
                      className="px-0! p-0 w-full justify-start"
                    >
                      <div className="flex w-full items-center gap-2">
                        <Headset className="shrink-0" />
                        <span className="flex-1 whitespace-nowrap">
                          {t("sidebar.contact_us")}
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground whitespace-nowrap">
                          {t("sidebar.contact_hours")}
                        </span>
                      </div>
                    </Button>
                  </DropdownMenuItem>

                  {isAuthenticated && (
                    <DropdownMenuItem className="cursor-pointer">
                      <Button
                        size="sm"
                        variant="ghost"
                        className="px-0! p-0 w-full justify-start text-destructive hover:text-destructive"
                        onClick={handleLogout}
                      >
                        <LogOut /> {t("sidebar.logout")}
                      </Button>
                    </DropdownMenuItem>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroup>
        <Separator className="my-1" />
      </SidebarContent>
      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            {state === "collapsed" ? (
              // Collapsed state: Logo-only icon centered
              <div className="flex justify-center">
                <img src="/logo-only.svg" alt="Ins" className="w-8 h-8" />
              </div>
            ) : (
              // Expanded state: Full logo with copyright
              <div className="flex flex-col gap-2 justify-start text-sm leading-tight">
                <Logo className="w-16" />
                <span className="font-normal text-muted-foreground">
                  Ins © {getYear(new Date())} All Right Reserved
                </span>
              </div>
            )}
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>

      <LogoutConfirmationDialog
        open={showLogoutDialog}
        onOpenChange={setShowLogoutDialog}
        onConfirm={confirmLogout}
      />
    </Sidebar>
  );
}

type NoLoginViewProps = {
  isCollapsed: boolean;
};

const NoLoginView = ({ isCollapsed }: NoLoginViewProps) => {
  const { t } = useTranslation();
  const { showLogin } = useAuthDialog();

  if (isCollapsed) {
    return (
      <SidebarGroup>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              tooltip={t("auth.login_now")}
              className="justify-center"
              aria-label={t("auth.login_now")}
              onClick={showLogin}
            >
              <LogOut className="size-8 stroke-2" aria-hidden="true" />
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarGroup>
    );
  }

  return (
    <SidebarGroup>
      <SidebarMenu>
        <SidebarMenuItem>
          <div className="mt-4 flex flex-col gap-2.5 justify-center items-center w-full">
            <Button
              type="button"
              className="bg-[#EA1E61] rounded-2xl py-1.5 px-10 size-8 hover:bg-[#EA1E61] w-full"
              size="sm"
              onClick={showLogin}
            >
              {t("auth.login_now")}
            </Button>
            <span className="text-muted-foreground text-sm">
              {t("auth.login_to_watch")}
            </span>
          </div>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarGroup>
  );
};

type ThemeSettingsItemProps = {
  t: TFunction;
};

const ThemeSettingsItem = ({ t }: ThemeSettingsItemProps) => {
  const { theme, resolvedTheme, setTheme } = useTheme();
  const themeOptions: Array<{
    value: "auto" | "light" | "dark";
    label: string;
  }> = [
    { value: "auto", label: t("sidebar.theme_auto") },
    { value: "light", label: t("sidebar.theme_day") },
    { value: "dark", label: t("sidebar.theme_night") },
  ];

  const currentLabel =
    resolvedTheme === "dark"
      ? t("sidebar.theme_current_night")
      : t("sidebar.theme_current_day");

  return (
    <div className="flex w-full flex-col gap-2">
      <div className="flex items-center justify-between gap-4">
        <div className="flex flex-col">
          <span className="text-sm font-medium">
            {t("sidebar.theme_label")}
          </span>
          <span className="text-xs text-muted-foreground leading-snug">
            {t("sidebar.theme_auto_hint")}
          </span>
        </div>
        <span className="text-xs font-medium text-muted-foreground">
          {t("sidebar.theme_current_label")}: {currentLabel}
        </span>
      </div>
      <div className="grid w-full grid-cols-3 gap-1.5">
        {themeOptions.map((option) => {
          const isActive = theme === option.value;
          return (
            <Button
              key={option.value}
              type="button"
              size="sm"
              variant={isActive ? "default" : "outline"}
              aria-pressed={isActive}
              className="h-8 justify-center text-xs"
              onClick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                setTheme(option.value);
              }}
            >
              {option.label}
            </Button>
          );
        })}
      </div>
    </div>
  );
};
