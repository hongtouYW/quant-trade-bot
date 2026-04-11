import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils.ts";

interface SearchResultSkeletonProps {
  showAnimation?: boolean;
}

export const SearchResultSkeleton = ({
  showAnimation = true,
}: SearchResultSkeletonProps) => {
  return (
    <div className="p-4 space-y-4">
      {/* Profile Result Section Skeleton */}
      <ProfileResultSectionSkeleton showAnimation={showAnimation} />

      {/* Another Profile Result Section Skeleton */}
      <ProfileResultSectionSkeleton showAnimation={showAnimation} />

      {/* Video Result Section Skeleton */}
      <VideoResultSkeleton showAnimation={showAnimation} />
    </div>
  );
};

interface ProfileResultSectionSkeletonProps {
  showAnimation?: boolean;
}

export const ProfileResultSectionSkeleton = ({
  showAnimation = true,
}: ProfileResultSectionSkeletonProps) => {

  return (
    <div>
      {/* Header */}
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <Skeleton
            className={`size-6 ${showAnimation ? "animate-pulse" : ""}`}
          />
          <Skeleton
            className={`h-6 w-32 ${showAnimation ? "animate-pulse" : ""}`}
          />
        </div>
        <Skeleton
          className={`h-8 w-16 rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
        />
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        <div className={cn("embla__viewport")}>
          <div className="embla__container flex touch-pan-y">
            {Array.from({ length: 6 }).map((_, index) => (
              <div
                key={index}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-2.5"
              >
                <div className="border border-transparent rounded-xl overflow-hidden p-2.5 w-[120px]">
                  {/* Profile Avatar */}
                  <Skeleton
                    className={`w-[100px] h-[100px] rounded-full mx-auto ${showAnimation ? "animate-pulse" : ""}`}
                  />

                  {/* Profile Info */}
                  <div className="flex flex-col items-center gap-1 justify-center mt-3">
                    {/* Name */}
                    <Skeleton
                      className={`h-4 w-20 ${showAnimation ? "animate-pulse" : ""}`}
                    />

                    {/* Subscribe Button */}
                    <Skeleton
                      className={`h-6 w-16 rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

interface VideoResultSkeletonProps {
  showAnimation?: boolean;
}

export const VideoResultSkeleton = ({
  showAnimation = true,
}: VideoResultSkeletonProps) => {

  return (
    <div>
      {/* Header */}
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <Skeleton
            className={`size-6 ${showAnimation ? "animate-pulse" : ""}`}
          />
          <Skeleton
            className={`h-6 w-32 ${showAnimation ? "animate-pulse" : ""}`}
          />
        </div>
        <Skeleton
          className={`h-8 w-16 rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
        />
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        <div className={cn("embla__viewport")}>
          <div className="embla__container flex touch-pan-y">
            {Array.from({ length: 8 }).map((_, index) => (
              <div
                key={index}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-3"
              >
                <div className="w-[150px]">
                  {/* Video Thumbnail */}
                  <div className="relative h-[221px] rounded-[8px] overflow-hidden mb-2">
                    <Skeleton
                      className={`absolute inset-0 w-full h-full ${showAnimation ? "animate-pulse" : ""}`}
                    />
                  </div>

                  {/* Video Info */}
                  <div className="px-1">
                    {/* Title - 2 lines */}
                    <Skeleton
                      className={`h-4 w-full mb-1 ${showAnimation ? "animate-pulse" : ""}`}
                    />
                    <Skeleton
                      className={`h-4 w-3/4 mb-2 ${showAnimation ? "animate-pulse" : ""}`}
                    />

                    {/* Bottom row: Actor name + Star rating */}
                    <div className="flex justify-between items-center">
                      <Skeleton
                        className={`h-4 w-16 ${showAnimation ? "animate-pulse" : ""}`}
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
    </div>
  );
};
