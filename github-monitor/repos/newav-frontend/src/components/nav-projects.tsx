import { type LucideIcon } from "lucide-react";
import {
  SidebarGroup,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import type { ReactElement } from "react";
import { useIdentityCard } from "@/contexts/IdentityCardContext";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { useAuth } from "@/hooks/auth/useAuth";
import { useAuthDialog } from "@/contexts/AuthDialogContext";

export function NavProjects({
  projects,
  children,
}: {
  projects: {
    name: string;
    url: string;
    icon: LucideIcon;
    onClick?: () => void;
  }[];
  children?: ReactElement;
}) {
  const { openIdentityCard } = useIdentityCard();
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { showLogin } = useAuthDialog();
  const { isMobile, setOpenMobile } = useSidebar();

  // List of routes that require authentication
  const protectedRoutes = [
    "/following",
    "/my-channels", 
    "/my-favorites",
    "/collected-videos",
    "/watch-history",
    "/purchase-history"
  ];

  const handleItemClick = (item: {
    name: string;
    url: string;
    icon: LucideIcon;
    onClick?: () => void;
  }) => {
    // If item has custom onClick handler, use it
    if (item.onClick) {
      item.onClick();
      return;
    }

    // Check if this is the identity card button
    if (item.name === t("sidebar.name_card")) {
      openIdentityCard();
      return;
    }

    // For other items, check if it requires authentication
    if (item.url !== "#") {
      // Check if this is a protected route
      if (protectedRoutes.includes(item.url)) {
        if (!isAuthenticated) {
          // Store the intended route and show login dialog
          localStorage.setItem("intendedRoute", item.url);
          showLogin();
          return;
        }
      }
      
      // Navigate normally (either not protected or user is authenticated)
      navigate(item.url);

      // Close sidebar on mobile after navigation
      if (isMobile) {
        setOpenMobile(false);
      }
    }
  };

  return (
    <SidebarGroup>
      <SidebarMenu>
        {children}
        {projects.map((item) => (
          <SidebarMenuItem key={item.name}>
            <SidebarMenuButton
              size="lg"
              tooltip={item.name}
              onClick={() => handleItemClick(item)}
              className="cursor-pointer"
            >
              <div className="w-6 h-6">{item.icon && <item.icon />}</div>
              <span className="font-medium text-base">{item.name}</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  );
}
