import { Skeleton } from "@/components/ui/skeleton";

interface PublisherInfoSkeletonProps {
  showAnimation?: boolean;
}

export const PublisherInfoSkeleton = ({ showAnimation = true }: PublisherInfoSkeletonProps) => {
  return (
    <>
      {/* Banner Skeleton */}
      <div className="relative h-[180px] rounded-b-2xl overflow-hidden">
        <Skeleton className={`h-full w-full ${showAnimation ? 'animate-pulse' : ''}`} />
        <div className="absolute inset-0 bg-gradient-to-b from-[rgba(0,0,0,0.1)] from-0% to-[rgba(0,0,0,0.5)] to-[79.5%] rounded-md"></div>
        <div className="absolute inset-0 flex flex-col gap-2 items-center justify-center">
          <Skeleton className={`h-10 w-48 bg-muted/40 ${showAnimation ? 'animate-pulse' : ''}`} />
          <Skeleton className={`h-6 w-64 bg-muted/40 ${showAnimation ? 'animate-pulse' : ''}`} />
        </div>
      </div>

      {/* Publisher Bio Skeleton */}
      <div className="px-4 flex items-center gap-4 flex-wrap md:flex-nowrap md:flex-row">
        {/* Avatar */}
        <Skeleton className={`ring-card -mt-10 md:-mt-20 rounded-full ring-6 size-[90px] md:size-[150px] ${showAnimation ? 'animate-pulse' : ''}`} />
        
        <div className="flex items-start gap-4 w-full justify-between flex-wrap flex-col md:flex-row">
          <div className="flex flex-col gap-2 justify-between">
            {/* Publisher Name */}
            <Skeleton className={`h-8 w-40 ${showAnimation ? 'animate-pulse' : ''}`} />
            {/* Followers */}
            <Skeleton className={`h-5 w-28 ${showAnimation ? 'animate-pulse' : ''}`} />
            
            {/* Avatar stack */}
            <div className="flex -space-x-1">
              {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton 
                  key={i} 
                  className={`size-8 rounded-full border-2 border-background ${showAnimation ? 'animate-pulse' : ''}`} 
                />
              ))}
            </div>
          </div>
          
          {/* Action buttons */}
          <div className="space-x-2">
            <Skeleton className={`h-8 w-20 rounded-full ${showAnimation ? 'animate-pulse' : ''}`} />
            <Skeleton className={`h-8 w-16 rounded-full ${showAnimation ? 'animate-pulse' : ''}`} />
          </div>
        </div>
      </div>

      {/* Publisher Content Skeleton */}
      <div className="px-4">
        <div className="w-full">
          {/* Tabs skeleton */}
          <div className="-mx-4">
            <div className="text-foreground justify-start w-full h-auto gap-2 rounded-none border-y border-x-0 bg-transparent px-0 py-1">
              <div className="flex gap-4 px-4">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton 
                    key={i} 
                    className={`h-8 w-20 ${showAnimation ? 'animate-pulse' : ''}`} 
                  />
                ))}
              </div>
            </div>
          </div>
          
          {/* Content grid skeleton */}
          <div className="mt-6 grid lg:grid-cols-4 grid-cols-2 gap-2.5">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="relative bg-card w-full h-full rounded-lg overflow-hidden">
                <Skeleton className={`aspect-video rounded-lg ${showAnimation ? 'animate-pulse' : ''}`} />
                <div className="pt-1.5 px-1.5 flex justify-between items-center">
                  <Skeleton className={`h-4 w-3/4 ${showAnimation ? 'animate-pulse' : ''}`} />
                  <div className="flex items-center">
                    <Skeleton className={`h-3 w-3 ${showAnimation ? 'animate-pulse' : ''}`} />
                    <Skeleton className={`h-3 w-8 ml-1 ${showAnimation ? 'animate-pulse' : ''}`} />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </>
  );
};
