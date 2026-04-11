import useEmblaCarousel from "embla-carousel-react";
import producerIcon from "../assets/samples/producers/producer-icon.png";

import { cn } from "@/lib/utils.ts";
import { useNavigate } from "react-router";
import { usePublisherList } from "@/hooks/publisher/usePublisherList.ts";
import { PublisherListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { ProfileCard } from "@/components/profile-card";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight } from "lucide-react";
import type { PublisherResult } from "@/types/search.types";

export const PublisherList = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: publishers } = usePublisherList({
    limit: 20,
    page: 1,
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

  const handlePublisherClick = (publisher: PublisherResult) => {
    navigate(`/publisher/${publisher.id}`);
  };

  // Check for empty data
  const publisherList = publishers?.data?.data || [];
  const totalPublishers = publishers?.data?.total || 0;
  const shouldShowMore = totalPublishers > publisherList.length;

  if (publisherList.length === 0) {
    return (
      <PublisherListEmpty
        title={t("empty.no_publishers")}
        description={t("empty.no_publishers_desc")}
        sectionTitle={t("homepage.publisher_selection")}
        sectionIcon={
          <img
            loading="lazy"
            src={producerIcon}
            className="size-6"
            alt="producer icon"
          />
        }
      />
    );
  }

  return (
    <div className="-mt-2 sm:-mt-3">
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={producerIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">{t("homepage.publisher_selection")}</span>
        </div>
        {shouldShowMore && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl"
            onClick={() => navigate("/publishers")}
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
            {publisherList.map((item) => (
              <div
                key={item.id}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-2"
              >
                <ProfileCard
                  item={item}
                  type="publisher"
                  showVideoCount={false}
                  onClick={handlePublisherClick}
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
