import useEmblaCarousel from "embla-carousel-react";
import { Button } from "@/components/ui/button.tsx";
import { RefreshCw } from "lucide-react";
import { cn } from "@/lib/utils";
import { useYouMayLikeVideoList } from "@/hooks/video/useYouMayLikeVideoList";
import { LatestVideoListSkeleton } from "@/components/skeletons";
import { VideoListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import youMayLikeIcon from "/you_may_like_it.png";

export const YouMayLikeList = () => {
  const { t } = useTranslation();
  const { data, isPending, isError, isFetching, refetch } =
    useYouMayLikeVideoList(10);

  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  if (isPending) {
    return (
      <LatestVideoListSkeleton />
    );
  }

  if (isError) return <div>Error loading videos</div>;

  const videoList = data?.data?.data || [];

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
            src={youMayLikeIcon}
            className="size-6"
            alt="you may like icon"
          />
          <span className="font-bold">{t("home.you_may_like")}</span>
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

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        <div ref={emblaRef} className={cn("embla__viewport")}>
          <div className="embla__container flex touch-pan-y">
            {videoList.map((item) => (
              <div
                key={item.id}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-3"
              >
                <EnhancedVideoCard
                  video={item}
                  layout="vertical"
                  size="sm"
                  linkState={{
                    from: "you-may-like",
                    categoryName: t("home.you_may_like"),
                  }}
                  linkPrefix="/watch"
                  showBadges={true}
                  showRating={true}
                  showActor={true}
                />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
