import { Separator } from "@/components/ui/separator.tsx";
import { Skeleton } from "@/components/ui/skeleton";
import { VideoPlayerHeader } from "@/components/VideoPlayerHeader";

export const VideoPlayerSkeleton = () => {
  return (
    <div className="min-h-screen">
      <VideoPlayerHeader />

      <div className="flex flex-col xl:flex-row gap-4 xl:h-[calc(100vh-3.5rem)] xl:p-4 md:p-4 sm:p-0 xs:p-0">
        {/* Main Content */}
        <div className="flex-1 xl:pr-2 xl:overflow-y-auto xl:h-full scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
          {/* Video Player Area */}
          <div className="relative">
            <Skeleton className="w-full aspect-video rounded-lg" />

            {/* Video Title and Info */}
            <div className="w-full mt-2 px-4 md:px-4 sm:px-4 xs:px-2">
              <Skeleton className="h-7 w-3/4 mb-3" />
              <div className="flex items-center gap-2">
                <Skeleton className="h-4 w-20" />
                <Separator
                  className="data-[orientation=vertical]:h-4 data-[orientation=vertical]:w-[2px]"
                  orientation="vertical"
                />
                <Skeleton className="h-4 w-16" />
                <Separator
                  className="data-[orientation=vertical]:h-4 data-[orientation=vertical]:w-[2px]"
                  orientation="vertical"
                />
                <Skeleton className="h-4 w-24" />
              </div>
            </div>
          </div>

          <Separator className="my-6 mb-4 h-4" />

          {/* Video Metadata */}
          <div className="p-4 md:px-4 sm:px-4 xs:px-2">
            <div className="flex items-center justify-between gap-4 xs:gap-2 overflow-hidden">
              {/* Actor Info */}
              <div className="flex items-center gap-3 xs:gap-2 flex-1 min-w-0">
                <Skeleton className="size-6 md:size-10 xs:size-8 rounded-full flex-shrink-0" />
                <Skeleton className="h-5 w-32" />
              </div>

              {/* Action Buttons */}
              <div className="flex-shrink-0 flex gap-2">
                {Array.from({ length: 3 }).map((_, i) => (
                  <Skeleton
                    key={i}
                    className="h-9 w-9 rounded-full"
                  />
                ))}
              </div>
            </div>

            {/* Video Details Section */}
            <div className="flex pt-4 flex-col gap-3">
              {/* Thumbnail Gallery Skeleton */}
              <div className="flex gap-2 overflow-hidden">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton
                    key={i}
                    className="min-w-[120px] h-[80px] rounded-lg"
                  />
                ))}
              </div>

              {/* Publisher, Serial, Director */}
              <div className="space-y-2 mt-2">
                <div className="flex items-center gap-4">
                  <Skeleton className="h-5 w-16" />
                  <Skeleton className="h-5 w-24" />
                </div>
                <div className="flex items-center gap-4">
                  <Skeleton className="h-5 w-16" />
                  <Skeleton className="h-5 w-32" />
                </div>
                <div className="flex items-center gap-4">
                  <Skeleton className="h-5 w-16" />
                  <Skeleton className="h-5 w-20" />
                </div>
              </div>

              {/* Tags */}
              <div className="flex gap-2 mt-2">
                {Array.from({ length: 5 }).map((_, i) => (
                  <Skeleton key={i} className="h-8 w-16 rounded-full" />
                ))}
              </div>
            </div>
          </div>

          {/* Info Card Skeleton */}
          <div className="px-4 md:px-4 sm:px-4 xs:px-2">
            <Skeleton className="h-40 w-full rounded-lg" />
          </div>

          <Separator className="my-6 h-4" />

          {/* Comment Section */}
          <div className="px-4 md:px-4 sm:px-4 xs:px-2">
            <Skeleton className="h-10 w-32 rounded-lg" />
          </div>
        </div>

        {/* Sidebar */}
        <div className="flex flex-col gap-2 w-full xl:w-auto xl:min-w-[400px] xl:max-w-[400px] mt-6 xl:mt-0 xl:overflow-y-auto xl:h-full px-4 sm:px-0 pb-4 sm:pb-0 scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none]">
          {/* VIP Section */}
          <div className="mb-3 flex justify-between items-center bg-card border border-border rounded-[14px] py-2.5 px-4 transition-colors">
            <div className="flex flex-col gap-1">
              <div className="flex gap-1.5">
                <Skeleton className="h-4 w-4 bg-muted-foreground/40" />
                <Skeleton className="h-4 w-12 bg-muted-foreground/40" />
              </div>
              <Skeleton className="h-3 w-24 bg-muted-foreground/30" />
            </div>
            <Skeleton className="h-8 w-20 rounded-[20px] bg-muted-foreground/30" />
          </div>

          {/* Recommended Videos */}
          <div>
            <div className="flex justify-between items-center mb-4">
              <Skeleton className="h-5 w-28" />
              <Skeleton className="h-8 w-20 rounded-2xl" />
            </div>

            <div className="flex flex-col gap-4">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className={i >= 3 ? "sm:block hidden" : ""}>
                  <div className="flex gap-2 bg-card overflow-hidden ring ring-transparent duration-300 rounded-lg h-[120px]">
                    <div className="min-w-[200px] max-w-[200px] h-full rounded-[8px] overflow-hidden flex-shrink-0 bg-muted">
                      <Skeleton className="w-full h-full" />
                    </div>
                    <div className="flex flex-col justify-between gap-2 py-1 h-full min-w-0 flex-1">
                      <div className="space-y-2 flex-1 min-h-0">
                        <Skeleton className="h-4 w-full bg-muted-foreground/20" />
                        <Skeleton className="h-4 w-3/4 bg-muted-foreground/20" />
                      </div>
                      <div className="flex flex-col gap-1 flex-shrink-0">
                        <div className="flex gap-2 items-center h-[20px]">
                          <Skeleton className="size-5 rounded-full bg-muted-foreground/20" />
                          <Skeleton className="h-3 w-20 bg-muted-foreground/20" />
                        </div>
                        <div className="flex items-center h-[16px]">
                          <Skeleton className="w-3 h-3 bg-muted-foreground/20" />
                          <Skeleton className="h-3 w-6 ml-1 bg-muted-foreground/20" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            <Skeleton className="w-full mt-4 h-10 hidden sm:block" />
          </div>
        </div>
      </div>
    </div>
  );
};
