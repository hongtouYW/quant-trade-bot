import { useEffect } from "react";
import { Button } from "@/components/ui/button.tsx";
import { RefreshCw } from "lucide-react";
import { useInfiniteRecommendedVideoList } from "@/hooks/video/useInfiniteRecommendedVideoList";
import { RecommendedHorizontalListSkeleton } from "@/components/skeletons";
import { VideoListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { EndOfContent } from "@/components/EndOfContent";
import { useInView } from "react-intersection-observer";
import recommendedIcon from "/recommended.svg";

export const RecommendedHorizontalList = () => {
  const { t } = useTranslation();
  const {
    data: infiniteData,
    isPending,
    isError,
    isFetching,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteRecommendedVideoList(10);

  const { ref: inViewRef, inView } = useInView({
    threshold: 1,
    rootMargin: "200px",
  });

  useEffect(() => {
    if (inView && hasNextPage && !isFetchingNextPage) {
      void fetchNextPage();
    }
  }, [inView, hasNextPage, isFetchingNextPage, fetchNextPage]);

  if (isPending) {
    return (
      <RecommendedHorizontalListSkeleton />
    );
  }

  if (isError) return <div>Error loading videos</div>;

  const videoList = infiniteData?.pages?.flatMap((page) => page.data) ?? [];

  if (videoList.length === 0) {
    return (
      <VideoListEmpty
        title={t("empty.no_videos")}
        description={t("empty.no_videos_desc")}
      />
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={recommendedIcon}
            className="size-6"
            alt="recommended icon"
          />
          <span className="font-bold">{t("home.recommended")}</span>
        </div>
        <Button
          variant="ghost"
          size="sm"
          className="rounded-2xl text-primary hover:text-primary"
          onClick={() => refetch()}
          disabled={isFetching}
        >
          <RefreshCw className={isFetching ? "animate-spin" : ""} />
          {t("common.random")}
        </Button>
      </div>

      <div className="mt-3.5 w-full overflow-hidden grid grid-cols-1 gap-2">
        {videoList.map((item) => (
          <div key={item.id}>
            <EnhancedVideoCard
              video={item}
              layout="horizontal-compact"
              size="sm"
              linkState={{
                from: "recommended",
                categoryName: t("home.recommended"),
              }}
              linkPrefix="/watch"
              showBadges={true}
              showRating={true}
              showActor={true}
              className="m-1"
            />
          </div>
        ))}
      </div>

      {hasNextPage && (
        <div ref={inViewRef} className="h-16 flex items-center justify-center">
          {isFetchingNextPage && (
            <span className="text-sm text-gray-500">
              {t("common.loading")}
            </span>
          )}
        </div>
      )}

      {!hasNextPage && <EndOfContent />}
    </div>
  );
};
