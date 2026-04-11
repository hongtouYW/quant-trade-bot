import type { TFunction } from "i18next";

export const getSubtitleLabel = (langCode: string, t: TFunction) => {
  const langMap: Record<string, string> = {
    en: "language.english",
    zh: "language.chinese", 
    ru: "language.russian",
    ms: "language.malay",
    th: "language.thai",
    es: "language.spanish",
  };
  return t(langMap[langCode] || langCode.toUpperCase());
};

export const getVidstackTranslations = (t: TFunction) => {
  const vidstackKeys = [
    "Announcements",
    "Accessibility",
    "AirPlay",
    "Audio",
    "Auto",
    "Boost",
    "Captions",
    "Caption Styles",
    "Captions look like this",
    "Chapters",
    "Closed-Captions Off",
    "Closed-Captions On",
    "Connected",
    "Continue",
    "Connecting",
    "Default",
    "Disabled",
    "Disconnected",
    "Display Background",
    "Download",
    "Enter Fullscreen",
    "Enter PiP",
    "Exit Fullscreen",
    "Exit PiP",
    "Font",
    "Family",
    "Fullscreen",
    "Google Cast",
    "Keyboard Animations",
    "LIVE",
    "Loop",
    "Mute",
    "Normal",
    "Off",
    "Pause",
    "Play",
    "Playback",
    "PiP",
    "Quality",
    "Replay",
    "Reset",
    "Seek Backward",
    "Seek Forward",
    "Seek",
    "Settings",
    "Skip To Live",
    "Speed",
    "Size",
    "Color",
    "Opacity",
    "Shadow",
    "Text",
    "Text Background",
    "Track",
    "Unmute",
    "Volume",
  ];

  const translations: Record<string, string> = {};
  vidstackKeys.forEach((key) => {
    translations[key] = t(`vidstack.${key}`);
  });

  return translations;
};

export const getDefaultSubtitleTrack = (
  zimuEntries: [string, string][],
  currentLang: string,
) => {
  // First, try to find exact match with current app language
  const exactMatch = zimuEntries.findIndex(
    ([langCode]) => langCode === currentLang,
  );
  if (exactMatch !== -1) return exactMatch;

  // Fallback order based on app language
  const fallbackOrder =
    currentLang === "zh"
      ? ["zh", "en", "ru", "ms", "th", "es"]
      : ["en", "zh", "ru", "ms", "th", "es"];

  // Try to find the first available language from fallback order
  for (const fallbackLang of fallbackOrder) {
    const index = zimuEntries.findIndex(
      ([langCode]) => langCode === fallbackLang,
    );
    if (index !== -1) return index;
  }

  // If nothing matches, default to first available
  return 0;
};