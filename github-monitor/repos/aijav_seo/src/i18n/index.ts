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

const isBrowser = typeof window !== "undefined";

const i18nInstance = i18n.use(initReactI18next);

// Only use browser language detection on the client — LanguageDetector reads
// localStorage at init time which crashes Node.js during SSR.
if (isBrowser) {
  i18nInstance.use(LanguageDetector);
}

i18nInstance.init({
  resources,
  // lng: "zh", // Set default language to Chinese
  fallbackLng: "zh", // Fallback to Chinese when translation is missing

  interpolation: {
    escapeValue: false, // React already handles escaping
  },

  ...(isBrowser && {
    detection: {
      order: ["localStorage"],
      caches: ["localStorage"],
      lookupLocalStorage: "i18nextLng",
    },
  }),

  react: {
    useSuspense: false, // Disable suspense mode for better control
  },
});

export default i18n;
