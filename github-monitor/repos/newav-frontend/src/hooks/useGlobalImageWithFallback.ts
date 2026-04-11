import { useGlobalImageContext } from "@/contexts/GlobalImageContext";
import { useTranslation } from "react-i18next";
import type { GlobalImageThumb } from "@/types/global-image.types";

interface UseGlobalImageWithFallbackProps {
  imageKey: string;
  fallbackImage: string;
  useMobileVersion?: boolean;
}

export function useGlobalImageWithFallback({
  imageKey,
  fallbackImage,
  useMobileVersion = false
}: UseGlobalImageWithFallbackProps): string {
  const { getImageByKey, isLoading, isError } = useGlobalImageContext();
  const { i18n } = useTranslation();

  // If still loading or error, use fallback
  if (isLoading || isError) {
    return fallbackImage;
  }

  const globalImage = getImageByKey(imageKey);

  // If no image found, use fallback
  if (!globalImage) {
    return fallbackImage;
  }

  // Determine which thumb to use (mobile or desktop)
  const thumbSource = useMobileVersion ? globalImage.m_thumb : globalImage.thumb;
  const thumb = thumbSource as GlobalImageThumb;

  if (!thumb || Object.keys(thumb).length === 0) {
    return fallbackImage;
  }

  // Get current language or fallback to 'en'
  const currentLang = i18n.language as keyof GlobalImageThumb;

  // Try current language first, then 'en', then any available language, then fallback
  const imageUrl = thumb[currentLang] ||
                   thumb.en ||
                   Object.values(thumb)[0] ||
                   fallbackImage;

  // If the URL is empty string or invalid, use fallback
  return imageUrl && imageUrl.trim() !== '' ? imageUrl : fallbackImage;
}