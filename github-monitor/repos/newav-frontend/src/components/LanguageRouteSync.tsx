import { useEffect } from "react";
import { useLocation } from "react-router";
import { useLanguage } from "@/contexts/LanguageContext";
import { getLangFromPathname } from "@/lib/watch-url";

export function LanguageRouteSync() {
  const location = useLocation();
  const { currentLanguage, changeLanguage } = useLanguage();

  useEffect(() => {
    const langFromPath = getLangFromPathname(location.pathname);
    if (!langFromPath || langFromPath === currentLanguage) return;
    changeLanguage(langFromPath);
  }, [changeLanguage, currentLanguage, location.pathname]);

  return null;
}
