import { useCallback, useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import useEmblaCarousel from "embla-carousel-react";
import { ChevronLeft, ChevronRight, Flame, ThumbsUp } from "lucide-react";

interface PackageOption {
  id: number;
  name: string;
  price: string;
  title?: string;
  money?: string | number;
  cost?: string | number;
  hot?: number;
  recommended?: number;
  recommend?: number;
  [key: string]: unknown;
}

interface PlanCardProps {
  title: string;
  image?: string;
  packages?: PackageOption[];
  statusText?: React.ReactNode;
  onPackageClick?: (pkg: PackageOption) => void;
}

type TranslateFn = (key: string, options?: Record<string, unknown>) => string;

function PackageCard({
  pkg,
  t,
  onClick,
}: {
  pkg: PackageOption;
  t: TranslateFn;
  onClick?: () => void;
}) {
  return (
    <div className="relative border border-[#DAC7A0] bg-[#FFFCEF] w-full flex-shrink-0 rounded-3xl transition-all hover:shadow h-full flex items-center overflow-hidden">
      {/* Badge */}
      {(pkg.hot === 1 || pkg.recommend === 1 || pkg.recommended === 1) && (
        <div className="absolute top-2 right-2 flex flex-col items-center gap-1 z-20">
          {pkg.hot === 1 && (
            <div
              className="flex items-center gap-1 px-1.5 py-0.5 rounded-3xl text-white text-base font-medium"
              style={{
                background:
                  "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
              }}
            >
              <Flame size={16} className="flex-shrink-0 fill-white" />
              <span className="truncate">{t("plans.badge_hot")}</span>
            </div>
          )}
          {(pkg.recommend === 1 || pkg.recommended === 1) && (
            <div
              className="flex items-center gap-1 px-1.5 py-0.5 rounded-3xl text-white text-base  font-medium"
              style={{
                background:
                  "linear-gradient(90deg, #FA7154 0%, #F9176D 49.01%, #F308CF 100.01%)",
              }}
            >
              <ThumbsUp size={14} className="flex-shrink-0 fill-white" />
              <span className="truncate">{t("plans.badge_recommended")}</span>
            </div>
          )}
        </div>
      )}

      {/* Cutout section */}
      <div className="z-10">
        <div className="absolute rounded-full border-[#DAC7A0] border w-5 h-5 bg-white mt-6 -left-2"></div>
        <div className="absolute rounded-full border-[#DAC7A0] border w-5 h-5 bg-white mt-6 -right-2"></div>
      </div>

      <div className="text-center w-full h-full flex flex-col">
        <div className="p-3 md:p-5 min-h-[120px] md:min-h-[150px] grid place-items-center">
          <div className="space-y-2 md:space-y-4">
            <div className="flex flex-col items-center">
              {pkg.is_sale === 1 && pkg.cost && (
                <span className="text-sm md:text-2xl font-light text-[#917457] line-through">
                  {pkg.cost}
                </span>
              )}
              <div className="flex items-baseline justify-center -space-x-0.5">
                <span className="text-[32px] md:text-[50px] font-semibold text-[#6E340D] leading-none">
                  {pkg.price}
                </span>
                <span className="text-sm md:text-base font-semibold text-[#6E340D]">
                  {t("plans.currency_yuan")}
                </span>
              </div>
            </div>
            <div className="text-[14px] md:text-[16px] font-normal text-[#917457] text-center">
              {pkg.name}
            </div>
          </div>
        </div>
        {/* Purchase Button Section */}
        <div
          className="relative bg-[#FCF0CE] cursor-pointer h-full grid place-items-center min-h-[60px]"
          onClick={onClick}
        >
          <svg
            className="absolute top-0 left-0 h-3 w-full"
            viewBox="0 0 100 10"
            preserveAspectRatio="none"
          >
            <polygon
              points="0,0 5,10 10,0 15,10 20,0 25,10 30,0 35,10 40,0 45,10 50,0 55,10 60,0 65,10 70,0 75,10 80,0 85,10 90,0 95,10 100,0"
              fill="#FFFCEF"
            />
          </svg>
          <p className="mt-2 md:mt-3 text-xl md:text-3xl font-medium text-[#6E340D]">
            {t("plans.buy_button")}
          </p>
        </div>
      </div>
    </div>
  );
}

export default function PlanCard({
  title,
  image,
  packages,
  statusText,
  onPackageClick,
}: PlanCardProps) {
  const { t } = useTranslation();
  const [emblaRef, emblaApi] = useEmblaCarousel({
    dragFree: true,
    containScroll: "trimSnaps",
  });
  const [canScrollPrev, setCanScrollPrev] = useState(false);
  const [canScrollNext, setCanScrollNext] = useState(false);

  const updateScrollState = useCallback(() => {
    if (!emblaApi) return;
    setCanScrollPrev(emblaApi.canScrollPrev());
    setCanScrollNext(emblaApi.canScrollNext());
  }, [emblaApi]);

  useEffect(() => {
    if (!emblaApi) return;
    updateScrollState();
    emblaApi.on("select", updateScrollState);
    emblaApi.on("reInit", updateScrollState);
    return () => {
      emblaApi.off("select", updateScrollState);
      emblaApi.off("reInit", updateScrollState);
    };
  }, [emblaApi, updateScrollState]);

  return (
    <div className="relative bg-card text-card-foreground border border-border min-h-[280px] md:h-[316px] shadow-lg opacity-100 gap-2.5 flex flex-col rounded-2xl p-4 transition-colors">
      <div>
        <div className="flex items-center gap-2 mb-2">
          <div className="w-[3px] h-5 rounded-full bg-brand-gold"></div>
          <h3 className="text-base font-semibold">{title}</h3>
        </div>
        <div className="w-full h-px bg-divider"></div>
      </div>

      {/* Content Section */}
      <div className="flex flex-col md:flex-row h-full gap-3">
        {/* Image Section */}
        <div className="shrink-0 relative">
          {image ? (
            <>
              <img
                src={image}
                alt={title}
                className="w-full h-full object-contain aspect-video"
              />
              {statusText && (
                <div className="absolute left-2 top-3/5 -translate-y-1/2">
                  {statusText}
                </div>
              )}
            </>
          ) : null}
        </div>

        {/* Package Options Section */}
        <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
          {packages && packages.length > 0 && (
            <>
              {/* Mobile Grid Layout */}
              <div className="grid grid-cols-2 gap-2 h-full md:hidden">
                {packages.map((pkg, index) => (
                  <PackageCard
                    key={index}
                    pkg={pkg}
                    t={t}
                    onClick={() => onPackageClick?.(pkg)}
                  />
                ))}
              </div>

              {/* Desktop Embla Carousel */}
              <div className="hidden md:block relative h-full">
                <button
                  type="button"
                  className={`absolute left-0 top-1/2 -translate-y-1/2 z-10 h-11 w-11 rounded-full bg-white/95 backdrop-blur-sm border border-[#DAC7A0] shadow-lg flex items-center justify-center text-[#6E340D] hover:shadow-xl hover:bg-[#F8EAD1] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#DAC7A0] transition-opacity duration-300 ${
                    canScrollPrev ? "opacity-100" : "opacity-0 pointer-events-none"
                  }`}
                  onClick={() => emblaApi?.scrollPrev()}
                  aria-label={t("common.previous") || "Previous"}
                >
                  <ChevronLeft size={20} />
                  <span className="sr-only">{t("common.previous") || "Previous"}</span>
                </button>
                <button
                  type="button"
                  className={`absolute right-0 top-1/2 -translate-y-1/2 z-10 h-11 w-11 rounded-full bg-white/95 backdrop-blur-sm border border-[#DAC7A0] shadow-lg flex items-center justify-center text-[#6E340D] hover:shadow-xl hover:bg-[#F8EAD1] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#DAC7A0] transition-opacity duration-300 ${
                    canScrollNext ? "opacity-100" : "opacity-0 pointer-events-none"
                  }`}
                  onClick={() => emblaApi?.scrollNext()}
                  aria-label={t("common.next") || "Next"}
                >
                  <ChevronRight size={20} />
                  <span className="sr-only">{t("common.next") || "Next"}</span>
                </button>
                <div className="overflow-hidden h-full" ref={emblaRef}>
                  <div className="flex gap-3 h-full">
                    {packages.map((pkg, index) => (
                      <div key={index} className="flex-[0_0_220px] min-w-0">
                        <PackageCard
                          pkg={pkg}
                          t={t}
                          onClick={() => onPackageClick?.(pkg)}
                        />
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
