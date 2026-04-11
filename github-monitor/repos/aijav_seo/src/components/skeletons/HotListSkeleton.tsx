import { Skeleton } from "@/components/ui/skeleton";

interface HotListSkeletonProps {
  showAnimation?: boolean;
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
}

export const HotListSkeleton = ({
  showAnimation = true,
  sectionTitle,
  sectionIcon,
}: HotListSkeletonProps) => {
  return (
    <div>
      {/* Header Section - Show real header if provided, otherwise skeleton */}
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          {sectionIcon ? (
            sectionIcon
          ) : (
            <Skeleton
              className={`size-6 rounded ${showAnimation ? "animate-pulse" : ""}`}
            />
          )}
          {sectionTitle ? (
            <span className="font-bold">{sectionTitle}</span>
          ) : (
            <Skeleton
              className={`h-6 w-32 ${showAnimation ? "animate-pulse" : ""}`}
            />
          )}
        </div>
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        <div className="embla__viewport">
          <div className="embla__container flex touch-pan-y">
            {Array.from({ length: 4 }).map((_, index) => (
              <div key={index} className="embla__slide flex-[0_0_auto] mr-3.5">
                <div className="sm:h-[170px] h-[100px] min-w-[165px] max-w-[165px] sm:max-w-full sm:w-[280px] relative rounded-xl overflow-hidden">
                  <Skeleton
                    className={`w-full h-full ${showAnimation ? "animate-pulse" : ""}`}
                  />
                  <div className="absolute inset-0 bg-accent/20" />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
