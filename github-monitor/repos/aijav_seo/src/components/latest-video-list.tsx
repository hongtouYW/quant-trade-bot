import useEmblaCarousel from "embla-carousel-react";
import newLogoIcon from "../assets/samples/latest/new-icon.png";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { useVideoIndexList } from "@/hooks/video/useVideoIndexList.ts";
import { LatestVideoListSkeleton } from "@/components/skeletons";
import { VideoListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";

const LatestVideoList = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isPending, isError } = useVideoIndexList({
    limit: 30,
    type: "4",
  });
  // const queryClient = useQueryClient();

  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  // const prefetchVideoInfo = (videoId: number | string) => {
  //   queryClient.prefetchQuery({
  //     queryKey: ["videoInfo", String(videoId)],
  //     queryFn: () => fetchVideoInfo({ vid: Number(videoId) }),
  //     staleTime: 1000 * 60 * 5, // Consider data fresh for 5 minutes
  //   });
  // };

  if (isPending)
    return (
      <LatestVideoListSkeleton
        sectionTitle={t("navbar.latest")}
        sectionIcon={
          <img src={newLogoIcon} className="size-6" alt="new logo icon" />
        }
      />
    );
  if (isError) return <div>Error loading videos</div>;

  // Check for empty data and filter out group series videos (private === 3)
  const allVideos = data?.data?.data || [];
  const videoList = allVideos;
  const totalVideos = data?.data?.total || 0;
  const shouldShowMore = totalVideos > videoList.length;

  if (videoList.length === 0) {
    return (
      <VideoListEmpty
        title={t("empty.no_latest_videos")}
        description={t("empty.no_latest_videos_desc")}
      />
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={newLogoIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">{t("navbar.latest")}</span>
        </div>
        {shouldShowMore && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl"
            onClick={() => navigate("/latest-videos")}
          >
            {t("common.more")}
            <ChevronRight />
          </Button>
        )}
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
                    from: "latest",
                    categoryName: t("navbar.latest"),
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

export default LatestVideoList;
