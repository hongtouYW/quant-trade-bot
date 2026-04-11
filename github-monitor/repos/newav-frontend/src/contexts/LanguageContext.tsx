import React, { createContext, useContext } from "react";
import { useTranslation } from "react-i18next";
import { useQueryClient } from "@tanstack/react-query";
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

export const LanguageProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const { i18n } = useTranslation();
  const queryClient = useQueryClient();

  const changeLanguage = (language: string) => {
    i18n.changeLanguage(language);

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
