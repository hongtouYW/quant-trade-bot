import { Skeleton } from "@/components/ui/skeleton";
import { useSidebar } from "@/components/ui/sidebar";
import { cn } from "@/lib/utils";

interface LatestPageSkeletonProps {
  showAnimation?: boolean;
}

export const LatestPageSkeleton = ({
  showAnimation = true,
}: LatestPageSkeletonProps) => {
  const { state: sidebarState } = useSidebar();

  // Match the responsive grid layout from Latest.tsx
  const gridClassName = cn(
    "grid gap-3.5 md:gap-2.5",
    sidebarState === "expanded"
      ? "grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-7 2xl:grid-cols-10"
      : "grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-8 2xl:grid-cols-10",
  );

  // Generate skeleton items based on screen size
  // Mobile: 3 columns × 4 rows = 12 items
  // Tablet: 4 columns × 3 rows = 12 items
  // Desktop: varies based on sidebar state
  const skeletonCount = 12;

  return (
    <div className="mt-3.5 w-full">
      <div className={gridClassName}>
        {Array.from({ length: skeletonCount }).map((_, index) => (
          <div key={index} className="flex flex-col">
            {/* Card Container - matches EnhancedVideoCard vertical layout */}
            <div className="overflow-hidden rounded-lg text-card-foreground transition-colors my-1 mx-0.5">
              {/* Image Container - h-[156px] on sm, h-[221px] on sm:up */}
              <div className="relative overflow-hidden rounded-[8px] h-[156px] sm:h-[221px] w-full md:min-w-[123px] flex-shrink-0">
                <Skeleton
                  className={`absolute inset-0 w-full h-full ${showAnimation ? "animate-pulse" : ""}`}
                />
              </div>

              {/* Content Container - matches vertical layout */}
              <div className="relative py-1 sm:py-2 px-1 gap-1 sm:gap-2 justify-between flex flex-col h-[4.5rem]">
                {/* Title - 2 lines */}
                <div className="text-card-foreground leading-tight flex-shrink-0">
                  <Skeleton
                    className={`h-4 w-full mb-1 ${showAnimation ? "animate-pulse" : ""}`}
                  />
                  <Skeleton
                    className={`h-4 w-3/4 ${showAnimation ? "animate-pulse" : ""}`}
                  />
                </div>

                {/* Bottom row: Actor name + Star rating */}
                <div className="flex justify-between items-center h-[20px]">
                  {/* Actor name */}
                  <Skeleton
                    className={`h-5 w-16 ${showAnimation ? "animate-pulse" : ""}`}
                  />
                  {/* Star rating */}
                  <div className="flex items-center text-yellow-400">
                    <Skeleton
                      className={`w-3 h-3 ${showAnimation ? "animate-pulse" : ""}`}
                    />
                    <Skeleton
                      className={`h-3 w-6 ml-1 ${showAnimation ? "animate-pulse" : ""}`}
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};
