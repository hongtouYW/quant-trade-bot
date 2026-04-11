import { Skeleton } from "@/components/ui/skeleton";

interface PlaylistCollectionSkeletonProps {
  showAnimation?: boolean;
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
}

export const PlaylistCollectionSkeleton = ({
  showAnimation = true,
  sectionTitle,
  sectionIcon,
  sectionAction,
}: PlaylistCollectionSkeletonProps) => {
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
        {sectionAction ? (
          sectionAction
        ) : (
          <Skeleton
            className={`h-8 w-16 rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
          />
        )}
      </div>

      {/* Horizontal Scrolling Grid */}
      <div className="mt-3.5 keen-slider w-full max-w-full">
        <div className="flex gap-4 overflow-hidden">
          {/* Generate multiple recommendation card skeletons matching actual layout */}
          {Array.from({ length: 6 }).map((_, index) => (
            <div
              key={index}
              className="keen-slider__slide my-1 mx-0.5 transition-colors overflow-hidden ring ring-transparent duration-300 rounded-lg"
              style={{ maxWidth: 180, minWidth: 180 }}
            >
              <div className="flex flex-col items-start w-full h-full">
                {/* Square thumbnail like actual */}
                <div className="h-[180px] w-[180px] rounded-[8px] overflow-hidden">
                  <Skeleton
                    className={`w-full h-full ${showAnimation ? "animate-pulse" : ""}`}
                  />
                </div>

                {/* Card Content - matches actual layout */}
                <div className="pt-2 px-1.5 relative w-full">
                  {/* Title - 2 lines */}
                  <Skeleton
                    className={`h-4 w-full mb-1 ${showAnimation ? "animate-pulse" : ""}`}
                  />
                  {/*<Skeleton*/}
                  {/*  className={`h-4 w-3/4 mb-1 ${showAnimation ? "animate-pulse" : ""}`}*/}
                  {/*/>*/}

                  {/* Bottom row: Video count + Diamond price */}
                  <div className="flex justify-between items-baseline">
                    <Skeleton
                      className={`h-5 w-8 ${showAnimation ? "animate-pulse" : ""}`}
                    />
                    {/* Diamond price */}
                    <div className="flex items-center">
                      <Skeleton
                        className={`size-3 ${showAnimation ? "animate-pulse" : ""}`}
                      />
                      <Skeleton
                        className={`h-3 w-8 ml-1 ${showAnimation ? "animate-pulse" : ""}`}
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
