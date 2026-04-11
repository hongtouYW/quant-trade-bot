import useEmblaCarousel from "embla-carousel-react";
import actorIcon from "../assets/samples/actor/actor-icon.png";
import { cn } from "@/lib/utils.ts";
import { useActorList } from "@/hooks/actor/useActorList.ts";
import { useNavigate } from "react-router";
import { ActorListSkeleton } from "@/components/skeletons";
import { ActorListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { ProfileCard } from "@/components/profile-card";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight } from "lucide-react";
import type { ActorList as ActorType } from "@/types/actor.types";

export const ActorList = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isPending, isError, refetch } = useActorList(
    {
      order: 5,
      limit: 20,
      page: 1,
    },
    99,
  );

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
      <ActorListSkeleton
        sectionTitle={t("homepage.actress_selection")}
        sectionIcon={
          <img
            loading="lazy"
            src={actorIcon}
            className="size-6"
            alt="actor icon"
          />
        }
      />
    );
  if (isError) return "Error";

  // Check for empty data
  const actorList = data?.data?.data || [];
  const totalActors = data?.data?.total || 0;
  const shouldShowMore = totalActors > actorList.length;

  if (actorList.length === 0) {
    return (
      <ActorListEmpty
        title={t("empty.no_actresses")}
        description={t("empty.no_actresses_desc")}
        onRefresh={() => refetch()}
        sectionTitle={t("homepage.actress_selection")}
        sectionIcon={
          <img
            loading="lazy"
            src={actorIcon}
            className="size-6"
            alt="actor icon"
          />
        }
      />
    );
  }

  const handleActorClick = (actor: ActorType) => {
    navigate(`/actress/${actor.id}`);
  };

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={actorIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">{t("homepage.actress_selection")}</span>
        </div>
        {shouldShowMore && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl"
            onClick={() => navigate("/actresses")}
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
            {actorList.map((item) => (
              <div
                key={item.id}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-2"
              >
                <ProfileCard
                  item={item}
                  type="actor"
                  showVideoCount={false}
                  onClick={handleActorClick}
                  className="min-h-full w-fit border-transparent hover:border-primary"
                />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
