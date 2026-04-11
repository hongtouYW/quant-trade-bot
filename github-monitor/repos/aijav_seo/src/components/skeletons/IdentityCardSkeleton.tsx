import { Skeleton } from '@/components/ui/skeleton';

export function IdentityCardSkeleton() {
  return (
    <div
      className="relative w-[260px] h-[529px] rounded-[20px] overflow-hidden shadow-lg"
      style={{
        background:
          "linear-gradient(135deg, hsl(var(--card)) 0%, hsl(var(--brand-light-purple) / 0.4) 100%)",
      }}
    >
      {/* Title Skeleton */}
      <div className="absolute top-[3px] left-[13px]">
        <Skeleton className="w-[60px] h-[28px]" />
      </div>
      
      {/* Close Button Skeleton */}
      <div className="absolute top-4 right-4">
        <Skeleton className="w-[26px] h-[26px] rounded-md" />
      </div>
      
      {/* Content Area Skeleton */}
      <div className="absolute top-[46px] left-[21px] w-[218px] flex flex-col items-center gap-5">
        {/* Profile Section Skeleton */}
        <div className="w-full flex flex-col items-center gap-5">
          {/* Avatar and Status Frames */}
          <div className="w-full flex flex-col items-center gap-[11px]">
            {/* Avatar Skeleton */}
            <Skeleton className="w-full h-[60px] rounded-lg" />
            
            {/* Status Frame Skeleton */}
            <Skeleton className="w-[120px] h-[32px] rounded-[8px]" />
            
            {/* Additional Status Frame Skeleton */}
            <Skeleton className="w-full h-[60px] rounded-lg" />
          </div>
          
          {/* Information Section Skeleton */}
          <div className="w-full flex flex-col gap-2.5">
            {/* Info rows */}
            <div className="w-full flex flex-col gap-[3px]">
              <Skeleton className="w-full h-4" />
              <Skeleton className="w-3/4 h-3" />
            </div>
            
            <div className="w-full flex flex-col gap-[3px]">
              <Skeleton className="w-full h-4" />
              <Skeleton className="w-2/3 h-3" />
            </div>
            
            {/* Warning Text Skeleton */}
            <Skeleton className="w-full h-[14px]" />
          </div>
        </div>
      </div>
      
      {/* Decorative Elements remain as visual elements, not skeletons */}
      <div
        className="absolute bottom-[388px] left-0 w-[193px] h-[141px] rounded-[20px]"
        style={{
          background:
            "linear-gradient(180deg, hsl(var(--brand-light-purple) / 0.3) 0%, hsl(var(--brand-purple) / 0.3) 100%)",
        }}
      />
      
      <div
        className="absolute bottom-0 right-[67px] w-[193px] h-[132px] rounded-[20px]"
        style={{
          background:
            "linear-gradient(180deg, hsl(var(--brand-light-purple) / 0.3) 0%, hsl(var(--brand-purple) / 0.3) 100%)",
        }}
      />
    </div>
  );
}

export default IdentityCardSkeleton;
