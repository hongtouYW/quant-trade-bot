import React, { createContext, useContext } from "react";
import { useTranslation } from "react-i18next";
import { useQueryClient } from "@tanstack/react-query";
import { useLocation, useNavigate } from "react-router";
import {
  LANG_COOKIE_MAX_AGE,
  LANG_COOKIE_NAME,
  SUPPORTED_LANGS,
  getLangFromPathname,
  normalizeLang,
  swapLangPrefix,
} from "@/lib/i18n-routing";
// Import language icons
import enIcon from "@/assets/language/eng-icon.png";
import zhIcon from "@/assets/language/zh-icon.png";

interface LanguageContextType {
  currentLanguage: string;
  changeLanguage: (language: string) => void;
  supportedLanguages: LanguageInfo[];
}

interface LanguageInfo {
  code: string;
  name: string;
  icon: string;
}

const LanguageContext = createContext<LanguageContextType | undefined>(
  undefined,
);

const supportedLanguages: LanguageInfo[] = [
  { code: "en", name: "English", icon: enIcon },
  { code: "zh", name: "中文", icon: zhIcon },
];

function writeLangCookie(lang: string) {
  if (typeof document === "undefined") return;
  document.cookie = `${LANG_COOKIE_NAME}=${lang}; Path=/; Max-Age=${LANG_COOKIE_MAX_AGE}; SameSite=Lax`;
}

export const LanguageProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const { i18n } = useTranslation();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const location = useLocation();

  const changeLanguage = (language: string) => {
    i18n.changeLanguage(language);

    // Persist to cookie so SSR redirects for this user default to their
    // preferred lang next time they visit an unprefixed URL.
    writeLangCookie(language);

    // If the current URL has a supported lang prefix, rewrite it to the
    // new language so the URL stays the source of truth. Paths without a
    // lang prefix (SPA catchall routes like /latest, /search) are left
    // alone — they don't participate in the lang-prefix scheme.
    const normalized = normalizeLang(language);
    const currentUrlLang = getLangFromPathname(location.pathname);
    if (normalized && currentUrlLang && currentUrlLang !== normalized) {
      const nextPath = swapLangPrefix(location.pathname, normalized);
      navigate(`${nextPath}${location.search}${location.hash}`, {
        replace: false,
      });
    }

    // Refetch only the active queries on the current page
    // This will only invalidate queries that are currently mounted/active
    queryClient.refetchQueries({
      type: "active",
    });

    // Dispatch custom event for API header updates
    window.dispatchEvent(
      new CustomEvent("languageChanged", {
        detail: { language },
      }),
    );
  };

  // Let i18next handle language detection and persistence automatically
  // First-time visitors will get Chinese (lng: 'zh' in i18n config)
  // Return visitors will get their saved preference from localStorage

  return (
    <LanguageContext.Provider
      value={{
        currentLanguage: i18n.language,
        changeLanguage,
        supportedLanguages,
      }}
    >
      {children}
    </LanguageContext.Provider>
  );
};

export const useLanguage = () => {
  const context = useContext(LanguageContext);
  if (context === undefined) {
    throw new Error("useLanguage must be used within a LanguageProvider");
  }
  return context;
};

// Re-export for callers that need the canonical list.
export { SUPPORTED_LANGS };
