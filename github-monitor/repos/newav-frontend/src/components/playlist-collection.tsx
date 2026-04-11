import useEmblaCarousel from "embla-carousel-react";
import recomendedListIcon from "../assets/samples/recommended-list/recomended-icon.png";
import { PlaylistCard } from "@/components/playlist-card";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils.ts";
import { useGroupList } from "@/hooks/group/useGroupList.ts";
import { PlaylistCollectionSkeleton } from "@/components/skeletons";
import { PlaylistEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";

export const PlaylistCollection = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const {
    data: groupData,
    isPending,
    isError,
  } = useGroupList({
    limit: 15,
    page: 1,
  });

  const groupList = groupData?.data || [];
  const totalGroups = groupData?.pagination?.total || 0;
  const shouldShowMore = totalGroups > groupList.length;

  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  if (isPending)
    return (
      <PlaylistCollectionSkeleton
        sectionTitle={t("homepage.recommended_playlists")}
        sectionIcon={
          <img
            loading="lazy"
            src={recomendedListIcon}
            className="size-6"
            alt="recommended list icon"
          />
        }
        sectionAction={
          <div className="w-20 h-8 bg-gray-200 rounded-2xl animate-pulse" />
        }
      />
    );
  if (isError) return <div>Error loading recommendations</div>;

  // Check for empty data
  if (!groupList || groupList.length === 0) {
    return (
      <PlaylistEmpty
        title={t("empty.no_playlists")}
        description={t("empty.no_playlists_desc")}
        sectionTitle={t("homepage.recommended_playlists")}
        sectionIcon={
          <img
            loading="lazy"
            src={recomendedListIcon}
            className="size-6"
            alt="recommended list icon"
          />
        }
        sectionAction={null}
      />
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2 text-foreground">
          <img
            loading="lazy"
            src={recomendedListIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">
            {t("homepage.recommended_playlists")}
          </span>
        </div>
        {shouldShowMore && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl"
            onClick={() => navigate("/series")}
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
            {groupList?.map((item, index) => (
              <div
                key={index}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-3.5"
              >
                <PlaylistCard
                  item={item}
                  index={index}
                  showVideosCount={true}
                />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
