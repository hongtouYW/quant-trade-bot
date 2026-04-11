import NavBar from "@/components/NavBar.tsx";
import { AppSidebar } from "@/components/app-sidebar.tsx";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar.tsx";
import { Outlet, useLocation } from "react-router";
import { IdentityCardModal } from "@/components/identity-card-modal";
import MobileNavigation from "@/components/mobile-navigation.tsx";
import { useState, useEffect } from "react";

export default function MainLayout() {
  const location = useLocation();
  const [isScrolled, setIsScrolled] = useState(false);

  // Read sidebar state from cookie
  const getSidebarState = () => {
    if (typeof document === "undefined") return true;
    const cookies = document.cookie.split(";");
    const sidebarCookie = cookies.find((cookie) =>
      cookie.trim().startsWith("sidebar_state="),
    );
    return sidebarCookie ? sidebarCookie.split("=")[1] === "true" : true;
  };

  // Routes where mobile navigation should appear (matching the navigation links)
  const mobileNavRoutes = [
    "/following",
    "/latest",
    "/categories",
    "/series",
    "/ranking",
    "/",
  ];
  const showMobileNav = mobileNavRoutes.includes(location.pathname);

  // Detect scroll to add shadow
  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 0);
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  return (
    <div className="font-pingFang antialiased bg-background md:h-screen [--header-height:calc(--spacing(15))] transition-colors">
      <SidebarProvider
        defaultOpen={getSidebarState()}
        className="flex flex-col h-full"
      >
        <div
          className={`sticky top-0 z-50 transition-shadow duration-200 ${isScrolled ? "shadow-md" : ""}`}
        >
          <NavBar />
          {showMobileNav && (
            <div className="md:hidden py-1 bg-background">
              <MobileNavigation />
            </div>
          )}
        </div>
        <div className="flex flex-1 min-h-0 relative">
          <AppSidebar />
          <SidebarInset className="md:overflow-auto">
            <div className="flex flex-col gap-4">
              <Outlet />
            </div>
          </SidebarInset>
        </div>
        {/* Identity Card Modal */}
        <IdentityCardModal />
      </SidebarProvider>
    </div>
  );
}
