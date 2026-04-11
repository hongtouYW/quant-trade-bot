import {
  forwardRef,
  type MutableRefObject,
  useEffect,
  useMemo,
  useState,
} from "react";
import { useTranslation } from "react-i18next";
import { useBase64Image } from "@/hooks/useBase64Image";
import { cn } from "@/lib/utils.ts";
import { useInView } from "react-intersection-observer";

interface Base64ImageProps {
  originalUrl: string;
  alt: string;
  className?: string;
  onError?: (e: React.SyntheticEvent<HTMLImageElement>) => void;
  onLoad?: (e: React.SyntheticEvent<HTMLImageElement>) => void;
  /**
   * When provided, allows parent components to manually control visibility.
   * Deprecated in favor of internal lazy loading but kept for backward compatibility.
   */
  inView?: boolean;
  /** Force the image to load regardless of visibility. */
  forceLoad?: boolean;
  fallbackElement?: React.ReactNode;
  /** Custom root margin passed to the intersection observer. */
  rootMargin?: string;
  /** Custom threshold passed to the intersection observer. */
  threshold?: number;
}

export const Base64Image = forwardRef<HTMLImageElement, Base64ImageProps>(
  (
    {
      originalUrl,
      alt,
      className,
      onError,
      onLoad,
      inView,
      forceLoad = false,
      fallbackElement,
      rootMargin = "0px 0px 200px 0px",
      threshold = 0,
    },
    ref,
  ) => {
    const { t } = useTranslation();
    const [hasBeenVisible, setHasBeenVisible] = useState(false);

    const { ref: observerRef, inView: observerInView } = useInView({
      rootMargin,
      threshold,
      triggerOnce: true,
    });

    useEffect(() => {
      if (observerInView) {
        setHasBeenVisible(true);
      }
    }, [observerInView]);

    useEffect(() => {
      if (typeof inView === "boolean" && inView) {
        setHasBeenVisible(true);
      }
    }, [inView]);

    const mergedRef = useMemo(() => {
      if (!ref) {
        return observerRef;
      }

      return (node: HTMLImageElement | null) => {
        observerRef(node);
        if (typeof ref === "function") {
          ref(node);
        } else if (ref) {
          (ref as MutableRefObject<HTMLImageElement | null>).current = node;
        }
      };
    }, [observerRef, ref]);

    const shouldLoad = forceLoad || hasBeenVisible;

    const {
      data: imageSrc,
      isPending: isLoading,
      isError,
    } = useBase64Image({
      originalUrl,
      enabled: shouldLoad,
    });

    if (!shouldLoad || isLoading) {
      return (
        <div
          className={cn("bg-muted animate-pulse", className)}
          ref={mergedRef}
        >
          <div className="w-full h-full flex items-center justify-center text-muted-foreground text-sm">
            {t("common.loading")}
          </div>
        </div>
      );
    }

    if (isError || !imageSrc) {
      if (fallbackElement) {
        return (
          <div ref={mergedRef} className={className}>
            {fallbackElement}
          </div>
        );
      }
      // Generate SVG with translated text
      const svgText = encodeURIComponent(t("common.image_load_failed"));
      const svgUrl = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='382' height='184' viewBox='0 0 382 184'%3E%3Crect width='382' height='184' fill='%23f3f4f6'/%3E%3Ctext x='191' y='92' text-anchor='middle' fill='%236b7280' font-family='system-ui, Noto Sans SC, sans-serif' font-weight='500' font-size='14'%3E${svgText}%3C/text%3E%3C/svg%3E`;
      return (
        <img ref={mergedRef} src={svgUrl} alt={alt} className={className} />
      );
    }

    return (
      <img
        ref={mergedRef}
        src={imageSrc}
        alt={alt}
        onError={(e) => {
          // Suppress error logging in console
          if (onError) {
            onError(e);
          }
        }}
        onLoad={onLoad}
        className={cn("blur-none", className)}
      />
    );
  },
);

Base64Image.displayName = "Base64Image";
