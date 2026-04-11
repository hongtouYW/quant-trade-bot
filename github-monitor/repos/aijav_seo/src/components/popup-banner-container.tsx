import { usePopupBanners } from "@/hooks/banner/usePopupBanners";
import { PopupBanner } from "@/components/popup-banner";

/**
 * PopupBannerContainer manages the display of popup banners (type 4).
 * Renders multiple popups as a stack (each gets its own floating button).
 * Popups are dismissed per-display, and the stack is reset on page reload.
 */
export const PopupBannerContainer = () => {
  const { data } = usePopupBanners();

  const popups = data?.data || [];

  if (popups.length === 0) return null;

  return (
    <>
      {popups.map((banner, index) => (
        <PopupBanner key={banner.id} banner={banner} stackIndex={index} />
      ))}
    </>
  );
};
