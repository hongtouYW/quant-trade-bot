import useEmblaCarousel from "embla-carousel-react";
import fireIcon from "../assets/samples/hot/fire-icon.png";
import appLogoWhite from "../assets/logo-white.svg";
import { cn } from "@/lib/utils.ts";
import { useTranslation } from "react-i18next";
import { Link, useNavigate } from "react-router";
import { useHotlistLists } from "@/hooks/video/useHotlistLists";
import { HotListSkeleton } from "@/components/skeletons/HotListSkeleton";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight } from "lucide-react";
import { Base64Image } from "@/components/Base64Image.tsx";

export const HotList = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: hotlistData, isPending } = useHotlistLists();

  const hotlistItems = hotlistData?.data?.data || [];
  const totalHotlist = hotlistData?.data?.total ?? hotlistItems.length;
  const shouldShowMore = totalHotlist > hotlistItems.length;

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
      <HotListSkeleton
        sectionIcon={
          <img
            loading="lazy"
            src={fireIcon}
            className="size-6"
            alt="new logo icon"
          />
        }
        sectionTitle={t("popular_rankings.ranking_categories")}
      />
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={fireIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">
            {t("popular_rankings.ranking_categories")}
          </span>
        </div>
        {shouldShowMore && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl"
            onClick={() => navigate("/hotlist")}
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
            {hotlistItems.map((item) => (
              <Link
                to={`/hotlist/${item.id}`}
                key={item.id}
                className="embla__slide flex-[0_0_auto] mr-3.5"
              >
                <div className="sm:h-[170px] h-[100px] min-w-[165px] max-w-[165px] sm:max-w-full sm:w-[280px] relative rounded-xl overflow-hidden shadow-lg cursor-pointer transition-transform duration-200">
                  <Base64Image
                    originalUrl={item.image}
                    alt={item.title}
                    className="w-full h-full object-cover"
                  />
                  <div className="absolute inset-0 bg-black opacity-50" />
                  <div className="absolute inset-0 flex flex-col items-center gap-1 justify-center">
                    <img
                      loading="lazy"
                      src={appLogoWhite}
                      className="size-8 md:size-12"
                      alt="app logo"
                    />
                    <h2 className=" text-white text-sm sm:text-xl font-semibold text-center px-2 sm:px-4">
                      {item.title}
                    </h2>
                    <h2 className="text-white text-sm sm:text-xl text-center px-2 sm:px-4">
                      {item.sub_title}
                    </h2>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
