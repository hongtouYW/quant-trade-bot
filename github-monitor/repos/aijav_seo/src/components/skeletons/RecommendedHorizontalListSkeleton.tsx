import { Skeleton } from "@/components/ui/skeleton";

interface RecommendedHorizontalListSkeletonProps {
  showAnimation?: boolean;
}

export const RecommendedHorizontalListSkeleton = ({
  showAnimation = true,
}: RecommendedHorizontalListSkeletonProps) => {
  return (
    <div>
      {/* Header with title and refresh button */}
      <div className="flex justify-between items-center mb-3.5">
        <div className="flex items-center space-x-2">
          <Skeleton
            className={`size-6 rounded ${showAnimation ? "animate-pulse" : ""}`}
          />
          <Skeleton
            className={`h-5 w-32 ${showAnimation ? "animate-pulse" : ""}`}
          />
        </div>
        <Skeleton
          className={`h-9 w-20 rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
        />
      </div>

      {/* Horizontal list of cards - matches EnhancedVideoCard horizontal-compact layout */}
      <div className="mt-3.5 w-full overflow-hidden grid grid-cols-1 gap-2">
        {Array.from({ length: 4 }).map((_, index) => (
          <div
            key={index}
            className="flex gap-2 hover:bg-primary/10 ring ring-transparent duration-300 hover:ring-primary h-[120px] mr-1 rounded-lg"
          >
            {/* Image container - min-w-[200px] max-w-[200px] h-full */}
            <div className="flex items-center justify-center sm:min-w-[200px] sm:max-w-[200px] h-full rounded-[8px] flex-shrink-0">
              <Skeleton
                className={`w-full h-full rounded-lg ${
                  showAnimation ? "animate-pulse" : ""
                }`}
              />
            </div>

            {/* Content area - flex flex-col justify-between gap-2 py-1 h-full min-w-0 flex-1 */}
            <div className="flex flex-col justify-between gap-2 py-1 h-full min-w-0 flex-1">
              {/* Badges and Title section */}
              <div className="space-y-2 flex-1 min-h-0 min-w-0">
                {/* Badges */}
                <div className="flex gap-1 mb-2">
                  <Skeleton
                    className={`h-5 w-12 rounded-full ${
                      showAnimation ? "animate-pulse" : ""
                    }`}
                  />
                </div>
                {/* Title - 2 lines with text-sm font-medium */}
                <div className="space-y-1">
                  <Skeleton
                    className={`h-4 w-full ${
                      showAnimation ? "animate-pulse" : ""
                    }`}
                  />
                  <Skeleton
                    className={`h-4 w-3/4 ${
                      showAnimation ? "animate-pulse" : ""
                    }`}
                  />
                </div>
              </div>

              {/* Actor and rating section */}
              <div className="flex flex-col gap-1 min-w-0">
                {/* Actor avatars and names */}
                <div className="flex items-center gap-2 overflow-hidden">
                  <div className="flex items-center gap-1 overflow-hidden">
                    <Skeleton
                      className={`size-5 rounded-full flex-shrink-0 ${
                        showAnimation ? "animate-pulse" : ""
                      }`}
                    />
                    <Skeleton
                      className={`h-3 w-16 ${
                        showAnimation ? "animate-pulse" : ""
                      }`}
                    />
                  </div>
                </div>

                {/* Rating */}
                <div className="flex items-center text-yellow-400 h-[16px]">
                  <Skeleton
                    className={`w-3 h-3 ${
                      showAnimation ? "animate-pulse" : ""
                    }`}
                  />
                  <Skeleton
                    className={`h-3 w-6 ml-1 ${
                      showAnimation ? "animate-pulse" : ""
                    }`}
                  />
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
