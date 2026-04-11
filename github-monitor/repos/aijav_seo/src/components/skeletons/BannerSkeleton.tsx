import { Skeleton } from "@/components/ui/skeleton";

interface BannerSkeletonProps {
  showAnimation?: boolean;
  showDots?: boolean;
  slidesPerView?: "multiple" | "single";
  aspectRatio?: string;
  mobileAspectRatio?: string;
  desktopHeight?: string;
}

export const BannerSkeleton = ({
  showAnimation = true,
  showDots = true,
  slidesPerView = "multiple",
  aspectRatio = "aspect-[1060/510]",
  mobileAspectRatio,
  desktopHeight,
}: BannerSkeletonProps) => {
  const slideClass =
    slidesPerView === "single"
      ? "embla__slide flex-[0_0_100%] min-w-0"
      : "embla__slide flex-[0_0_100%] min-w-0 sm:flex-[0_0_50%] lg:flex-[0_0_33.333%]";

  const slideCount = slidesPerView === "single" ? 1 : 3;
  // For multiple slides: 1 dot on mobile, 2 on sm, 1 on lg (matching actual scroll snaps)
  const dotCount = slidesPerView === "single" ? 3 : 1;

  // Match the actual carousel's height and aspect ratio logic
  const heightClass = desktopHeight
    ? `h-[160px] sm:${desktopHeight}`
    : "h-[160px]";

  const wrapperAspectRatio = mobileAspectRatio
    ? `${mobileAspectRatio} sm:${aspectRatio}`
    : aspectRatio;

  const wrapperSizeClass = `${heightClass} ${wrapperAspectRatio}`;

  return (
    <div className="embla w-full overflow-hidden">
      {/* Carousel container - matches embla structure */}
      <div className="embla__viewport">
        <div className="embla__container flex gap-4">
        {Array.from({ length: slideCount }).map((_, index) => (
          <div key={index} className={slideClass}>
            <div className={`relative bg-muted/50 h-auto sm:h-full overflow-hidden rounded-xl shadow-lg shadow-[#E0E0E0] dark:shadow-none dark:border-none border border-[#E0E0E0] ${wrapperSizeClass}`}>
              <Skeleton
                className={`w-full h-full ${showAnimation ? "animate-pulse" : ""}`}
              />
            </div>
          </div>
        ))}
        </div>
      </div>

      {/* Pagination dots - matches actual carousel */}
      {showDots && (
        <div className="flex justify-center mt-4 space-x-2">
          {Array.from({ length: dotCount }).map((_, index) => (
            <div
              key={index}
              className={`w-2 h-2 rounded-full bg-muted-foreground/30 ${showAnimation ? "animate-pulse" : ""}`}
            />
          ))}
        </div>
      )}
    </div>
  );
};
