import Logo from "@/components/logo";
import { Button } from "@/components/ui/button";
import {
  NavigationMenu,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
} from "@/components/ui/navigation-menu";
import { Menu, SearchIcon } from "lucide-react";
import { Input } from "@/components/ui/input.tsx";
import LanguageSwitcher from "@/components/language-switcher.tsx";
import { useSidebar } from "@/components/ui/sidebar.tsx";
import { Link, NavLink, useLocation, useNavigate } from "react-router";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useAuth } from "@/hooks/auth/useAuth";
import { useAuthDialog } from "@/contexts/AuthDialogContext";

// Navigation links array to be used in both desktop and mobile menus
const getNavigationLinks = (t: (key: string) => string) => [
  { href: "/following", label: t("navbar.following") },
  { href: "/latest", label: t("navbar.latest") },
  { href: "/series", label: t("navbar.series") },
  { href: "/ranking", label: t("navbar.ranking") },
  { href: "/categories", label: t("navbar.categories") },
  { href: "/plans", label: t("navbar.plans") },
];

export default function NavBar() {
  const { t } = useTranslation();
  const { toggleSidebar } = useSidebar();
  const location = useLocation();
  const params = new URLSearchParams(location.search);

  const keyword = params.get("keyword") ?? "";
  const navigate = useNavigate();
  const [searchValue, setSearchValue] = useState(keyword);
  const { isAuthenticated } = useAuth();
  const { showLogin } = useAuthDialog();

  // List of routes that require authentication
  const protectedRoutes = ["/following"];

  const navigationLinks = getNavigationLinks(t);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter" && searchValue.trim() !== "") {
      navigate(`/search?keyword=${encodeURIComponent(searchValue.trim())}`);
    }
  };

  const handleNavClick = (e: React.MouseEvent, href: string) => {
    // Check if this is a protected route
    if (protectedRoutes.includes(href)) {
      if (!isAuthenticated) {
        e.preventDefault(); // Prevent navigation
        localStorage.setItem("intendedRoute", href);
        showLogin();
        return;
      }
    }
    // For non-protected routes or authenticated users, navigation happens naturally
  };

  return (
    <header className="bg-background dark:bg-[#1A0626] sticky top-0 z-50 flex w-full items-center border-b border-border/60 dark:border-transparent transition-colors">
      <div className="flex h-(--header-height) w-full items-center justify-between gap-4 px-4 md:relative">
        {/* Left side */}
        <div className="flex gap-2">
          <div className="flex items-center">
            {/* Mobile menu trigger */}
            <Button
              className="size-10"
              variant="ghost"
              size="icon"
              onClick={toggleSidebar}
            >
              <Menu className="size-8" />
            </Button>
          </div>
          {/* Main nav */}
          <div className="flex items-center gap-6 h-(--header-height)">
            <Link
              to="/"
              className="text-primary hover:text-primary/90 max-md:hidden"
            >
              <Logo className="size-14" />
            </Link>
            {/* Navigation menu */}
            <NavigationMenu className="h-full *:h-full max-md:hidden">
              <NavigationMenuList className="h-full gap-2">
                {navigationLinks.map((link, index) => (
                  <NavigationMenuItem key={index} className="h-full">
                    <NavLink to={link.href}>
                      {({ isActive }) => (
                        <NavigationMenuLink
                          asChild
                          active={isActive}
                          onClick={(e) => handleNavClick(e, link.href)}
                          className="font-bold text-base text-muted-foreground hover:text-primary border-b-primary hover:border-b-primary data-[active]:border-b-primary data-[active]:text-primary h-full justify-center rounded-none border-y-2 border-transparent py-1.5 hover:bg-transparent data-[active]:bg-transparent!"
                        >
                          <span>{link.label}</span>
                        </NavigationMenuLink>
                      )}
                    </NavLink>
                  </NavigationMenuItem>
                ))}
              </NavigationMenuList>
            </NavigationMenu>
          </div>
        </div>

        {/* Mobile logo in center */}
        <div className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 md:hidden">
          <Link to="/" className="text-primary hover:text-primary/90">
            <Logo className="size-14" />
          </Link>
        </div>

        {/* Right side */}
        <div className="flex flex-1 items-center justify-end gap-2">
          {/* Desktop search */}
          <div className=" relative hidden md:block">
            <Input
              id="id"
              className="peer h-8 pe-9 ps-3 rounded-full bg-muted/80 text-foreground placeholder:text-muted-foreground/70 dark:bg-muted/30 transition-colors"
              placeholder={t("navbar.search_placeholder")}
              type="search"
              value={searchValue}
              onChange={(e) => setSearchValue(e.target.value)}
              onKeyDown={handleKeyDown}
            />
            <button
              type="button"
              aria-label={t("navbar.search_placeholder")}
              onClick={() => {
                if (searchValue.trim() !== "") {
                  navigate(`/search?keyword=${encodeURIComponent(searchValue.trim())}`);
                }
              }}
              className="text-muted-foreground absolute inset-y-0 end-0 flex items-center justify-center pe-3 hover:text-foreground focus:text-foreground focus:outline-none cursor-pointer transition-colors"
            >
              <SearchIcon size={16} />
            </button>
          </div>

          {/* Mobile search icon */}
          <Button
            className="size-10 md:hidden"
            variant="ghost"
            size="icon"
            onClick={() => navigate("/search")}
          >
            <SearchIcon className="size-6" />
          </Button>

          <div className="flex items-center gap-1">
            <LanguageSwitcher />
          </div>
        </div>
      </div>
    </header>
  );
}
