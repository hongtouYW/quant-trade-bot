import { useEffect } from "react";
import { useLocation } from "react-router";
import { useLanguage } from "@/contexts/LanguageContext";
import {
  LANG_COOKIE_MAX_AGE,
  LANG_COOKIE_NAME,
  getLangFromPathname,
} from "@/lib/i18n-routing";

/**
 * Keep the app language in sync with the URL's lang prefix.
 *
 * When a user arrives from an external link like /en/actress/5, this
 * component detects the `en` segment and switches the app to English.
 * It also writes the lang cookie so future unprefixed visits land on
 * the correct language via the SSR redirect.
 */
export function LanguageRouteSync() {
  const location = useLocation();
  const { currentLanguage, changeLanguage } = useLanguage();

  useEffect(() => {
    const langFromPath = getLangFromPathname(location.pathname);
    if (!langFromPath) return;

    // Persist the URL-detected lang to the cookie even if it already
    // matches currentLanguage — this ensures returning users keep their
    // preference fresh (renewing the 1-year expiry).
    if (typeof document !== "undefined") {
      document.cookie = `${LANG_COOKIE_NAME}=${langFromPath}; Path=/; Max-Age=${LANG_COOKIE_MAX_AGE}; SameSite=Lax`;
    }

    if (langFromPath === currentLanguage) return;
    changeLanguage(langFromPath);
  }, [changeLanguage, currentLanguage, location.pathname]);

  return null;
}
