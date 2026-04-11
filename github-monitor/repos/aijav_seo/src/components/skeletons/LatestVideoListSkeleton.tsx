import { Skeleton } from "@/components/ui/skeleton";

interface LatestVideoListSkeletonProps {
  showAnimation?: boolean;
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
}

export const LatestVideoListSkeleton = ({
  showAnimation = true,
  sectionTitle,
  sectionIcon,
}: LatestVideoListSkeletonProps) => {
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
              className={`h-6 w-24 ${showAnimation ? "animate-pulse" : ""}`}
            />
          )}
        </div>
      </div>

      {/* Horizontal Scrolling Video Cards */}
      <div className="mt-3.5 keen-slider w-full">
        <div className="flex gap-4 overflow-hidden">
          {/* Generate multiple video card skeletons matching actual layout */}
          {Array.from({ length: 8 }).map((_, index) => (
            <div
              key={index}
              className="keen-slider__slide my-1 mx-0.5 hover:bg-primary/10 transition-colors overflow-hidden ring ring-transparent duration-300 rounded-lg"
              style={{ maxWidth: 150, minWidth: 150 }}
            >
              <div className="relative bg-card w-full h-full group flex flex-col">
                {/* Video Thumbnail - tall portrait like actual */}
                <div className="relative h-[221px] rounded-[8px] overflow-hidden flex-shrink-0">
                  <Skeleton
                    className={`w-full h-full ${showAnimation ? "animate-pulse" : ""}`}
                  />
                </div>

                {/* Card Content - matches actual layout */}
                <div className="py-2 px-1 relative flex-1 flex flex-col">
                  {/* Title - 2 lines with fixed height */}
                  <div className="h-9 flex flex-col justify-between mb-1 flex-1">
                    <Skeleton
                      className={`h-4 w-full ${showAnimation ? "animate-pulse" : ""}`}
                    />
                    <Skeleton
                      className={`h-4 w-3/4 ${showAnimation ? "animate-pulse" : ""}`}
                    />
                  </div>

                  {/* Bottom row: Actor name + Star rating */}
                  <div className="flex justify-between items-center h-5 flex-shrink-0">
                    <Skeleton
                      className={`h-5 w-16 ${showAnimation ? "animate-pulse" : ""}`}
                    />
                    {/* Star rating */}
                    <div className="flex items-center gap-1">
                      <Skeleton
                        className={`size-3 ${showAnimation ? "animate-pulse" : ""}`}
                      />
                      <Skeleton
                        className={`h-3 w-6 ${showAnimation ? "animate-pulse" : ""}`}
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
