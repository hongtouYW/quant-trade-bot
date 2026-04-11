import { Button } from "@/components/ui/button";
import { ChevronsDown, RefreshCw } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useInfiniteVideoList } from "@/hooks/video/useInfiniteVideoList";
import { InlineError } from "@/components/error-states";
import { cn } from "@/lib/utils";
import { Skeleton } from "@/components/ui/skeleton";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";

const RecommendedListSkeleton = () => (
  <div className="flex flex-col gap-4">
    {Array.from({ length: 6 }).map((_, index) => (
      <div key={index} className={index >= 3 ? "sm:block hidden" : ""}>
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
);

export function RecommendedList() {
  const { t } = useTranslation();
  const {
    data: infiniteData,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
    refetch,
    isLoading,
    isError,
    isFetching: isRefetching,
  } = useInfiniteVideoList();

  // Get all videos from infinite query pages
  const allVideos = infiniteData?.pages?.flatMap((page) => page.data) || [];
  const isFetching = isFetchingNextPage || isRefetching;

  if (isLoading) {
    return (
      <div>
        <div className="flex justify-between items-center mb-4">
          <span className="font-bold">
            {t("video_player.recommended_videos")}
          </span>
          <Button
            variant="ghost"
            size="sm"
            className="rounded-2xl text-primary hover:text-primary"
            disabled
          >
            <RefreshCw />
            {t("video_player.random")}
          </Button>
        </div>
        <RecommendedListSkeleton />
      </div>
    );
  }
  if (isError)
    return (
      <InlineError
        onRetry={() => {
          void refetch();
        }}
      />
    );

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <span className="font-bold">
          {t("video_player.recommended_videos")}
        </span>

        <Button
          variant="ghost"
          size="sm"
          className="rounded-2xl text-primary hover:text-primary"
          onClick={() => {
            void refetch();
          }}
          disabled={isFetching}
        >
          <RefreshCw className={cn(isFetching ? "-ms-1 animate-spin" : null)} />
          {t("video_player.random")}
        </Button>
      </div>

      <>
        <div className="flex flex-col gap-4">
          {allVideos.map((item) => (
            <div key={item.id}>
              <EnhancedVideoCard
                video={item}
                layout="horizontal-compact"
                linkPrefix="/watch"
                showActor={true}
                showRating={true}
                showBadges={true}
              />
            </div>
          ))}
        </div>

        {hasNextPage && (
          <Button
            className="w-full mt-4 py-3.5 px-5 h-10 hidden sm:flex items-center justify-center gap-2"
            onClick={() => fetchNextPage()}
            disabled={isFetchingNextPage}
          >
            {isFetchingNextPage ? (
              <RefreshCw className="animate-spin size-4" />
            ) : (
              <ChevronsDown className="size-4" />
            )}
            {isFetchingNextPage ? t("common.loading") : t("common.more")}
          </Button>
        )}
      </>
    </div>
  );
}
