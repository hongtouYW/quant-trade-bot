import useEmblaCarousel from "embla-carousel-react";
import { Button } from "@/components/ui/button.tsx";
import { RefreshCw } from "lucide-react";
import { cn } from "@/lib/utils";
import { useRecommendedVideoList } from "@/hooks/video/useRecommendedVideoList";
import { LatestVideoListSkeleton } from "@/components/skeletons";
import { VideoListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import recommendedIcon from "/recommended.svg";

export const RecommendedList = () => {
  const { t } = useTranslation();
  const { data, isPending, isError, isFetching, refetch } =
    useRecommendedVideoList(10);

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
                    from: "recommended",
                    categoryName: t("home.recommended"),
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
