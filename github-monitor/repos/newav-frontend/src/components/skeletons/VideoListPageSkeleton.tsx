import { Skeleton } from "@/components/ui/skeleton";

export const VideoListPageSkeleton = () => {
  return (
    <div>
      {/* Header */}
      {/*<header className="border-b px-4">*/}
      {/*  <div className="flex h-14 items-center justify-between gap-4">*/}
      {/*    <div className="flex items-center gap-4">*/}
      {/*      /!* Back button skeleton *!/*/}
      {/*      <Skeleton className="h-8 w-16 rounded animate-pulse" />*/}
      {/*      */}
      {/*      /!* Toolbar title skeleton *!/*/}
      {/*      <Skeleton className="h-8 w-24 rounded-full animate-pulse" />*/}
      {/*    </div>*/}
      {/*    */}
      {/*    /!* Results count skeleton *!/*/}
      {/*    <Skeleton className="h-4 w-20 animate-pulse" />*/}
      {/*  </div>*/}
      {/*</header>*/}

      {/* Content */}
      <div className="p-4">
        {/* Video Grid Skeleton */}
        <div className="grid lg:grid-cols-4 grid-cols-2 gap-2.5">
          {Array.from({ length: 20 }).map((_, index) => (
            <div
              key={index}
              className="relative bg-card w-full h-full overflow-hidden rounded-lg"
            >
              {/* Video Thumbnail Skeleton */}
              <div className="relative aspect-video rounded-lg overflow-hidden">
                <Skeleton className="absolute inset-0 w-full h-full animate-pulse" />

                {/* Badge skeleton */}
                <Skeleton className="absolute top-0 left-0 h-6 w-12 rounded-br-lg animate-pulse" />
              </div>

              {/* Card Content Skeleton */}
              <div className="pt-1.5 px-1.5 pb-2 flex items-center justify-between">
                <div className="flex-1 mr-2">
                  {/* Title skeleton - 2 lines */}
                  <Skeleton className="h-4 w-full mb-1 animate-pulse" />
                  <Skeleton className="h-4 w-3/4 animate-pulse" />
                </div>

                {/* Rating skeleton */}
                <div className="flex items-center">
                  <Skeleton className="h-3 w-3 mr-1 animate-pulse" />
                  <Skeleton className="h-3 w-4 animate-pulse" />
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
