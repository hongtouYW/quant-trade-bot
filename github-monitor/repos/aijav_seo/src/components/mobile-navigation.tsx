import { NavLink, useLocation } from "react-router";
import { useTranslation } from "react-i18next";
import { useAuth } from "@/hooks/auth/useAuth";
import { useAuthDialog } from "@/contexts/AuthDialogContext";

const getMobileNavigationLinks = (t: (key: string) => string) => [
  { href: "/following", label: t("mobile_nav.following") },
  { href: "/latest", label: t("mobile_nav.latest") },
  { href: "/categories", label: t("mobile_nav.categories") },
  { href: "/series", label: t("mobile_nav.series") },
  { href: "/ranking", label: t("mobile_nav.ranking") },
];

export default function MobileNavigation() {
  const { t } = useTranslation();
  const location = useLocation();
  const { isAuthenticated } = useAuth();
  const { showLogin } = useAuthDialog();
  const navigationLinks = getMobileNavigationLinks(t);

  // List of routes that require authentication
  const protectedRoutes = ["/following"];

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
    <div className="grid grid-cols-5 gap-2 px-4 justify-items-center">
      {navigationLinks.map((link, index) => (
        <NavLink key={index} to={link.href}>
          {({ isActive }) => (
            <div
              onClick={(e) => handleNavClick(e, link.href)}
              className={`text-center py-3 px-2 rounded-lg font-medium text-sm transition-colors ${
                isActive || location.pathname === link.href
                  ? "text-primary"
                  : "text-muted-foreground hover:text-primary"
              }`}
            >
              {link.label}
            </div>
          )}
        </NavLink>
      ))}
    </div>
  );
}
