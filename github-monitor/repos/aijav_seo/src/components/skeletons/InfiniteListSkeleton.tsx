import { Skeleton } from "@/components/ui/skeleton";

interface InfiniteListSkeletonProps {
  showAnimation?: boolean;
  categoryCount?: number;
  videosPerCategory?: number;
}

export const InfiniteListSkeleton = ({
  showAnimation = true,
  categoryCount = 3,
  videosPerCategory = 6,
}: InfiniteListSkeletonProps) => {
  return (
    <div className="space-y-4 sm:space-y-6">
      {Array.from({ length: categoryCount }).map((_, categoryIndex) => (
        <div key={categoryIndex}>
          {/* Category Header */}
          <div className="flex justify-between items-center">
            <div className="flex items-center space-x-2">
              <div className="size-5 bg-[#F54336] rounded-r-2xl" />
              <Skeleton
                className={`h-6 w-32 ${showAnimation ? "animate-pulse" : ""}`}
              />
            </div>
            <Skeleton
              className={`h-9 w-16 rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
            />
          </div>

          {/* Carousel with Video Cards */}
          <div className="embla mt-3.5 w-full overflow-hidden">
            <div className="embla__viewport">
              <div className="embla__container flex">
                {Array.from({ length: videosPerCategory }).map(
                  (_, videoIndex) => (
                    <div
                      key={videoIndex}
                      className="embla__slide flex-[0_0_auto] min-w-0 mr-3"
                      style={{ minWidth: 150 }}
                    >
                      <div className="relative bg-card w-full h-full group">
                        {/* Video Thumbnail */}
                        <div className="relative h-[221px] rounded-[8px] overflow-hidden">
                          <Skeleton
                            className={`absolute inset-0 w-full h-full ${showAnimation ? "animate-pulse" : ""}`}
                          />
                        </div>

                        {/* Card Content */}
                        <div className="py-2 px-1 relative">
                          {/* Title - 2 lines */}
                          <Skeleton
                            className={`h-4 w-full mb-1 ${showAnimation ? "animate-pulse" : ""}`}
                          />
                          <Skeleton
                            className={`h-4 w-3/4 mb-1 ${showAnimation ? "animate-pulse" : ""}`}
                          />

                          {/* Bottom row: Actor name + Star rating */}
                          <div className="flex justify-between items-center">
                            <Skeleton
                              className={`h-5 w-16 ${showAnimation ? "animate-pulse" : ""}`}
                            />
                            {/* Star rating */}
                            <div className="flex items-center">
                              <Skeleton
                                className={`size-3 ${showAnimation ? "animate-pulse" : ""}`}
                              />
                              <Skeleton
                                className={`h-3 w-6 ml-1 ${showAnimation ? "animate-pulse" : ""}`}
                              />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ),
                )}
              </div>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};
