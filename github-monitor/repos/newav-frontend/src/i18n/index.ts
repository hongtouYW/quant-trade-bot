import i18n from "i18next";
import { initReactI18next } from "react-i18next";
import LanguageDetector from "i18next-browser-languagedetector";

// Import translation files
import en from "./locales/en.json";
import zh from "./locales/zh.json";
import ru from "./locales/ru.json";

const resources = {
  en: { translation: en },
  zh: { translation: zh },
  ru: { translation: ru },
};

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources,
    // lng: "zh", // Set default language to Chinese
    fallbackLng: "zh", // Fallback to Chinese when translation is missing

    interpolation: {
      escapeValue: false, // React already handles escaping
    },

    detection: {
      order: ["localStorage"],
      caches: ["localStorage"],
      lookupLocalStorage: "i18nextLng",
    },

    react: {
      useSuspense: false, // Disable suspense mode for better control
    },
  });

export default i18n;
