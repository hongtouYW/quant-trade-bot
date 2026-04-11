import { useState, useEffect } from "react";
import { useNavigate } from "react-router";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import { Base64Image } from "@/components/Base64Image";
import type { Banner } from "@/types/banner";
import { cn } from "@/lib/utils";

interface PopupBannerProps {
  banner: Banner;
  stackIndex?: number;
}

const ONE_HOUR_MS = 3600000; // 1 hour in milliseconds

export const PopupBanner = ({ banner, stackIndex = 0 }: PopupBannerProps) => {
  const [isOpen, setIsOpen] = useState(false);
  const navigate = useNavigate();

  const POPUP_STORAGE_KEY = `popupBanner_${banner.id}_lastShown`;

  // Auto-show popup on first visit or after 1 hour
  useEffect(() => {
    const checkAndShowPopup = () => {
      const lastShownTime = localStorage.getItem(POPUP_STORAGE_KEY);
      const now = Date.now();

      // Show if never shown before (first visit) or if 1 hour has passed
      if (!lastShownTime || now - parseInt(lastShownTime) >= ONE_HOUR_MS) {
        // Add delay to avoid lag on page load
        const timer = setTimeout(() => {
          setIsOpen(true);
        }, 1200); // 1.2 second delay

        return () => clearTimeout(timer);
      }
    };

    checkAndShowPopup();

    // Listen for storage changes (from other tabs or login action)
    const handleStorageChange = (e: StorageEvent) => {
      // Check if popup key or token key changed (in either localStorage or sessionStorage)
      if (e.key === POPUP_STORAGE_KEY || e.key === "tokenNew") {
        checkAndShowPopup();
      }
    };

    // Also listen for custom token change event (same-tab login)
    const handleTokenChanged = () => {
      checkAndShowPopup();
    };

    // Listen for popup banner reset event (triggered on login)
    const handlePopupBannerReset = () => {
      checkAndShowPopup();
    };

    window.addEventListener("storage", handleStorageChange);
    window.addEventListener("tokenChanged", handleTokenChanged);
    window.addEventListener("popupBannerReset", handlePopupBannerReset);

    return () => {
      window.removeEventListener("storage", handleStorageChange);
      window.removeEventListener("tokenChanged", handleTokenChanged);
      window.removeEventListener("popupBannerReset", handlePopupBannerReset);
    };
  }, [POPUP_STORAGE_KEY]);

  const handleOpenDialog = () => {
    setIsOpen(true);
  };

  const handleCloseDialog = () => {
    setIsOpen(false);
    // Save timestamp when user closes the popup
    localStorage.setItem(POPUP_STORAGE_KEY, Date.now().toString());
  };

  const handleBannerClick = () => {
    handleCloseDialog();
    // Map p_type to plan section ID: 1=vip, 2=points, 3=diamonds
    const focusPlanId = banner.p_type || null;
    setTimeout(() => {
      navigate("/plans", { state: { focusPlanId } });
    }, 250);
  };

  return (
    <>
      {/* Floating Button - Bottom Right (with stacking offset) */}
      <button
        onClick={handleOpenDialog}
        className={cn(
          "fixed z-40",
          "size-16 rounded-full overflow-hidden",
          "shadow-lg hover:shadow-xl transition-shadow",
          "border border-gray-200 bg-white",
          "flex items-center justify-center",
          "hover:scale-110 transition-transform",
        )}
        style={{
          bottom: `${24 + stackIndex * 72}px`,
          right: "24px",
        }}
        aria-label={`Open popup: ${banner.title}`}
      >
        <div className="size-full">
          <Base64Image
            originalUrl={banner.thumb}
            alt={banner.title}
            className="w-full h-full object-cover"
          />
        </div>
      </button>

      {/* Dialog Modal - Full Image Only */}
      <Dialog
        open={isOpen}
        onOpenChange={(open) => {
          if (!open) {
            handleCloseDialog();
          } else {
            setIsOpen(true);
          }
        }}
      >
        <DialogContent className="border-0 bg-transparent shadow-none p-0 max-w-4xl w-full">
          <div className="w-full cursor-pointer" onClick={handleBannerClick}>
            <Base64Image
              originalUrl={banner.thumb}
              alt={banner.title}
              className="w-full h-auto object-cover"
              forceLoad
            />
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
};
