import useEmblaCarousel from "embla-carousel-react";
import freeIcon from "../assets/samples/infnite-list/free-icon.png";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight, RefreshCw } from "lucide-react";
import { cn } from "@/lib/utils.ts";
import { useVideoIndexList } from "@/hooks/video/useVideoIndexList.ts";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { ListError } from "@/components/error-states";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { LatestVideoListSkeleton } from "@/components/skeletons";
import { Skeleton } from "@/components/ui/skeleton";

const VideoListSkeleton = () => (
  <div className="flex gap-4 overflow-hidden">
    {Array.from({ length: 8 }).map((_, index) => (
      <div
        key={index}
        className="keen-slider__slide my-1 mx-0.5 hover:bg-[#EC67FF]/20 transition-colors overflow-hidden ring ring-transparent duration-300 rounded-lg"
        style={{ maxWidth: 150, minWidth: 150 }}
      >
        <div className="relative bg-white w-full h-full group">
          <div className="relative h-[221px] rounded-[8px] overflow-hidden">
            <Skeleton className="absolute inset-0 w-full h-full animate-pulse" />
          </div>
          <div className="py-2 px-1 relative">
            <Skeleton className="h-4 w-full mb-1 animate-pulse" />
            <Skeleton className="h-4 w-3/4 mb-1 animate-pulse" />
            <div className="flex justify-between items-center">
              <Skeleton className="h-5 w-16 animate-pulse" />
              <div className="flex items-center">
                <Skeleton className="size-3 animate-pulse" />
                <Skeleton className="h-3 w-6 ml-1 animate-pulse" />
              </div>
            </div>
          </div>
        </div>
      </div>
    ))}
  </div>
);

export const FreeList = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isPending, isError, isFetching, refetch } = useVideoIndexList({
    type: "3",
    page: 1,
    random: 1,
  });

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
      <LatestVideoListSkeleton
        sectionTitle={t("homepage.free")}
        sectionIcon={
          <img src={freeIcon} className="size-6" alt="free icon" />
        }
      />
    );
  }
  if (isError)
    return (
      <ListError
        onRetry={() => refetch()}
        sectionTitle={t("homepage.free")}
        sectionIcon={
          <img
            loading="lazy"
            src={freeIcon}
            className="size-6"
            alt="free icon"
          />
        }
      />
    );

  // Check for empty data
  const freeVideoList = data?.data?.data || [];
  const totalVideos = data?.data?.total || 0;
  const shouldShowMore = totalVideos > freeVideoList.length;

  if (freeVideoList.length === 0) {
    return null;
  }

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={freeIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">{t("homepage.free")}</span>
        </div>
        <div className="flex items-center gap-2">
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
          {shouldShowMore && (
            <Button
              variant="outline"
              size="sm"
              className="rounded-2xl"
              onClick={() => navigate("/free-videos")}
            >
              {t("common.more")}
              <ChevronRight />
            </Button>
          )}
        </div>
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        {isFetching && !isPending ? (
          <VideoListSkeleton />
        ) : (
          <div ref={emblaRef} className={cn("embla__viewport")}>
            <div className="embla__container flex touch-pan-y">
              {freeVideoList.map((item) => (
                <div
                  key={item.id}
                  className="embla__slide flex-[0_0_auto] min-w-0 mr-3"
                >
                  <EnhancedVideoCard
                    video={item}
                    layout="vertical"
                    size="sm"
                    linkState={{
                      from: "free",
                      categoryName: t("homepage.free"),
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
        )}
      </div>
    </div>
  );
};
