import useEmblaCarousel from "embla-carousel-react";
import myListIcon from "@/assets/samples/my-list/my-list-icon.png";
import { Heart, History, LockKeyholeOpen } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";

const useUserActionData = () => {
  const { t } = useTranslation();

  return [
    {
      title: t("user_actions.watch_history"),
      icon: History,
      path: "/watch-history",
    },
    {
      title: t("user_actions.favorite_videos"),
      icon: Heart,
      path: "/my-favorites",
    },
    {
      title: t("user_actions.purchased_videos"),
      icon: LockKeyholeOpen,
      path: "/purchase-history",
    },
    // Hide items without routes for now
    // {
    //   title: t("user_actions.repeat_watch"),
    //   icon: InfinityIcon,
    // },
    // {
    //   title: t("user_actions.revisit_favorites"),
    //   icon: Film,
    // },
  ].filter((item) => item.path); // Only show items with paths
};

export const UserActionMenu = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const data = useUserActionData();

  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={myListIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">{t("user_actions.my_list_group")}</span>
        </div>
        {/*<Button variant="outline" size="sm" className="rounded-2xl">*/}
        {/*  更多*/}
        {/*  <ChevronRight />*/}
        {/*</Button>*/}
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden max-w-full sm:max-w-[630px]">
        <div ref={emblaRef} className="embla__viewport">
          <div className="embla__container flex touch-pan-y">
            {data.map((item, index) => (
              <div
                key={index}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-4"
              >
                <div
                  className="flex flex-col items-center  text-card-foreground h-full cursor-pointer rounded-2xl transition-colors"
                  onClick={() => navigate(item.path)}
                >
                  <div className="flex flex-col items-center p-6 sm:p-7 bg-primary/10 text-primary gap-1 rounded-full overflow-hidden transition-colors">
                    <item.icon size={28} className="sm:w-8 sm:h-8" />
                  </div>

                  {/* Card Content */}
                  <div className="py-2 sm:py-3 px-1.5 max-w-[90px]">
                    {/* Title */}
                    <h3 className="font-bold text-xs sm:text-sm text-foreground leading-tight text-center whitespace-normal">
                      {item.title}
                    </h3>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
