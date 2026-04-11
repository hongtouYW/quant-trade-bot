import { Skeleton } from "@/components/ui/skeleton";

interface ActorListSkeletonProps {
  showAnimation?: boolean;
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
}

export const ActorListSkeleton = ({
  showAnimation = true,
  sectionTitle,
  sectionIcon,
  sectionAction,
}: ActorListSkeletonProps) => {
  return (
    <div>
      {/* Header Section */}
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          {sectionIcon}
          {sectionTitle ? (
            <span className="font-bold">{sectionTitle}</span>
          ) : (
            <Skeleton
              className={`h-6 w-24 ${showAnimation ? "animate-pulse" : ""}`}
            />
          )}
        </div>
        {sectionAction && sectionAction}
      </div>

      {/* Grid Layout - matching my-actor-list.tsx grid structure */}
      <div className="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-9 gap-1.5 mt-3.5">
        {Array.from({ length: 12 }).map((_, index) => (
          <div
            key={index}
            className="flex flex-col items-center rounded-xl overflow-hidden p-[10px]"
          >
            {/* Avatar skeleton - matching ProfileCard size */}
            <div className="size-[76px] sm:size-24">
              <Skeleton
                className={`w-full h-full rounded-full ${showAnimation ? "animate-pulse" : ""}`}
              />
            </div>

            {/* Content skeleton - matching ProfileCard layout */}
            <div className="flex flex-col w-full items-center gap-1 justify-center">
              {/* Name skeleton */}
              <Skeleton
                className={`mt-2 h-3 sm:h-4 w-3/4 ${showAnimation ? "animate-pulse" : ""}`}
              />

              {/* Video count skeleton - hidden in my-actor-list but keeping for consistency */}
              {/* <Skeleton
                className={`h-3 sm:h-4 w-1/2 ${showAnimation ? 'animate-pulse' : ''}`}
              /> */}

              {/* Subscribe button skeleton */}
              <Skeleton
                className={`mt-1 h-7 sm:w-full w-[72px] rounded-2xl ${showAnimation ? "animate-pulse" : ""}`}
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
