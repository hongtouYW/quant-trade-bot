import { useState, useCallback, useEffect } from "react";
import useEmblaCarousel from "embla-carousel-react";
import { useTranslation } from "react-i18next";
import { Button } from "@/components/ui/button.tsx";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import { Base64Image } from "@/components/Base64Image";

interface ImageViewerProps {
  images: string[];
  initialIndex?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function ImageViewer({
  images,
  initialIndex = 0,
  open,
  onOpenChange,
}: ImageViewerProps) {
  const { t } = useTranslation();
  const [selectedIndex, setSelectedIndex] = useState(initialIndex);
  const [emblaRef, emblaApi] = useEmblaCarousel({
    align: "center",
    loop: true,
    skipSnaps: false,
  });

  const handlePrevious = useCallback(() => {
    if (emblaApi) emblaApi.scrollPrev();
  }, [emblaApi]);

  const handleNext = useCallback(() => {
    if (emblaApi) emblaApi.scrollNext();
  }, [emblaApi]);

  // Update selected index when carousel changes
  const handleSelect = useCallback(() => {
    if (emblaApi) {
      setSelectedIndex(emblaApi.selectedScrollSnap());
    }
  }, [emblaApi]);

  // Set up event listener for carousel changes
  useEffect(() => {
    if (emblaApi) {
      emblaApi.on("select", handleSelect);
      return () => {
        emblaApi.off("select", handleSelect);
      };
    }
  }, [emblaApi, handleSelect]);

  // Handle body scroll and keyboard navigation
  useEffect(() => {
    if (!open) {
      // Reset body overflow when lightbox closes
      document.body.style.overflow = "unset";
      return;
    }

    // Prevent body scroll when lightbox is open
    document.body.style.overflow = "hidden";

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "ArrowLeft") {
        handlePrevious();
      } else if (e.key === "ArrowRight") {
        handleNext();
      } else if (e.key === "Escape") {
        onOpenChange(false);
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => {
      window.removeEventListener("keydown", handleKeyDown);
      document.body.style.overflow = "unset";
    };
  }, [open, handlePrevious, handleNext, onOpenChange]);

  if (images.length === 0 || !open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/95"
      onClick={() => onOpenChange(false)}
    >
      {/* Main Content */}
      <div
        className="relative w-full h-full flex items-center justify-center"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Close Button */}
        <button
          onClick={() => onOpenChange(false)}
          className="absolute top-6 right-6 z-50 text-white hover:text-gray-300 transition-colors"
          aria-label={t("image_viewer.close_gallery")}
        >
          <X className="w-8 h-8" />
        </button>

        {/* Image Carousel */}
        <div className="w-full" ref={emblaRef}>
          <div className="flex">
            {images.map((url, index) => (
              <div
                key={index}
                className="flex-shrink-0 w-full flex items-center justify-center"
              >
                <Base64Image
                  originalUrl={url}
                  alt={`Image ${index + 1}`}
                  className="max-w-full max-h-[90vh] w-auto h-auto object-contain"
                />
              </div>
            ))}
          </div>
        </div>

        {/* Left Arrow */}
        <Button
          variant="ghost"
          size="icon"
          onClick={handlePrevious}
          className="absolute left-6 top-1/2 -translate-y-1/2 h-12 w-12 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
          aria-label={t("image_viewer.previous_image")}
        >
          <ChevronLeft className="w-6 h-6" />
        </Button>

        {/* Right Arrow */}
        <Button
          variant="ghost"
          size="icon"
          onClick={handleNext}
          className="absolute right-6 top-1/2 -translate-y-1/2 h-12 w-12 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
          aria-label={t("image_viewer.next_image")}
        >
          <ChevronRight className="w-6 h-6" />
        </Button>

        {/* Counter */}
        <div className="absolute bottom-6 left-1/2 -translate-x-1/2 bg-black/70 text-white px-4 py-2 rounded-full text-sm font-medium">
          {selectedIndex + 1} / {images.length}
        </div>
      </div>
    </div>
  );
}
