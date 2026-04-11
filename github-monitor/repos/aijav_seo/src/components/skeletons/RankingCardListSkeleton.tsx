import { Skeleton } from "@/components/ui/skeleton";

interface RankingCardListSkeletonProps {
  showAnimation?: boolean;
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
}

export const RankingCardListSkeleton = ({
  showAnimation = true,
}: RankingCardListSkeletonProps) => {
  return (
    <div>
      {/* Horizontal Card Grid Layout - matching actual card structure */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5">
        {Array.from({ length: 8 }).map((_, index) => (
          <div
            key={index}
            className="bg-card text-card-foreground w-full relative mb-2 flex gap-1.5 md:gap-3 items-stretch border border-border rounded-2xl px-1.5 py-2.5 md:p-4 transition-colors isolate"
          >
            {/* Ranking Badge Skeleton - positioned at top-left */}
            <div className="absolute -top-3 left-4 z-20">
              <div className="py-1.5 px-3 sm:px-4 border border-primary dark:border-primary/60 rounded-full flex items-center gap-2 text-xs font-medium shadow-sm bg-brand-light-purple dark:bg-primary/50">
                {/* Badge icon skeleton */}
                <Skeleton
                  className={`size-3 rounded-full ${showAnimation ? "animate-pulse" : ""}`}
                />
                {/* Badge text skeleton */}
                <Skeleton
                  className={`h-3 w-8 ${showAnimation ? "animate-pulse" : ""}`}
                />
              </div>
            </div>

            {/* Left Section: Avatar + Name + Button */}
            <div
              className="flex-shrink-0 max-w-[80px] md:max-w-full py-2 md:py-4"
              style={{ margin: "auto 0" }}
            >
              <div className="flex flex-col items-center">
                {/* Avatar Skeleton */}
                <Skeleton
                  className={`size-12 md:size-20 rounded-full ${showAnimation ? "animate-pulse" : ""}`}
                />

                {/* Name Text Skeleton */}
                <Skeleton
                  className={`mt-2 h-3 sm:h-4 w-20 ${showAnimation ? "animate-pulse" : ""}`}
                />

                {/* Subscribe Button Skeleton */}
                <Skeleton
                  className={`mt-2 h-6 w-[70px] rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
                />
              </div>
            </div>

            {/* Right Section: Video Carousel Skeleton */}
            <div className="flex-1 min-w-0 overflow-x-auto flex items-center">
              <div className="overflow-hidden w-full">
                <div className="flex gap-2 sm:gap-4 px-2 h-full items-stretch">
                  {Array.from({ length: 6 }).map((_, videoIndex) => (
                    <Skeleton
                      key={videoIndex}
                      className={`flex-shrink-0 rounded-lg w-[90px] h-[128px] md:min-w-[135px] md:h-[190px] ${showAnimation ? "animate-pulse" : ""}`}
                    />
                  ))}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
