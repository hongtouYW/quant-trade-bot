import useEmblaCarousel from "embla-carousel-react";
import Autoplay from "embla-carousel-autoplay";
import { useBanner } from "@/hooks/banner/useBanner.ts";
import { BannerSkeleton } from "@/components/skeletons";
import { Link } from "react-router";
import { useCallback, useEffect, useState, useRef } from "react";
import { Base64Image } from "./Base64Image";

interface BannerCarouselProps {
  position: 1 | 2 | 3;
  slidesPerView?: "multiple" | "single";
  aspectRatio?: string;
  mobileAspectRatio?: string;
  desktopHeight?: string;
  showDots?: boolean;
  autoScroll?: boolean;
  autoScrollInterval?: number;
}

export const BannerCarousel = ({
  position,
  slidesPerView = "multiple",
  aspectRatio = "aspect-[1060/510]",
  mobileAspectRatio,
  desktopHeight,
  showDots = true,
  autoScroll = false,
  autoScrollInterval = 5000,
}: BannerCarouselProps) => {
  const { data, isPending, isError } = useBanner(position);
  const autoplayRef = useRef(Autoplay({ delay: autoScrollInterval }));

  const [emblaRef, emblaApi] = useEmblaCarousel(
    slidesPerView === "single"
      ? {
          align: "start",
          containScroll: "trimSnaps",
          slidesToScroll: 1,
          loop: autoScroll,
        }
      : {
          align: "start",
          containScroll: "trimSnaps",
          slidesToScroll: 1,
          loop: autoScroll,
          breakpoints: {
            "(min-width: 500px)": { slidesToScroll: 2 },
            "(min-width: 1000px)": { slidesToScroll: 3 },
          },
        },
    autoScroll ? [autoplayRef.current] : [],
  );

  const [selectedIndex, setSelectedIndex] = useState(0);
  const [scrollSnaps, setScrollSnaps] = useState<number[]>([]);
  const [slidesInView, setSlidesInView] = useState<number[]>([]);

  const onDotButtonClick = useCallback(
    (index: number) => {
      if (!emblaApi) return;
      emblaApi.scrollTo(index);
    },
    [emblaApi],
  );

  const onInit = useCallback(
    (emblaApi: ReturnType<typeof useEmblaCarousel>[1]) => {
      if (!emblaApi) return;
      setScrollSnaps(emblaApi.scrollSnapList());
    },
    [],
  );

  const onSelect = useCallback(
    (emblaApi: ReturnType<typeof useEmblaCarousel>[1]) => {
      if (!emblaApi) return;
      setSelectedIndex(emblaApi.selectedScrollSnap());
    },
    [],
  );

  const updateSlidesInView = useCallback(
    (emblaApi: ReturnType<typeof useEmblaCarousel>[1]) => {
      if (!emblaApi) return;
      setSlidesInView((slidesInView) => {
        if (slidesInView.length === emblaApi.slideNodes().length) {
          emblaApi.off("slidesInView", updateSlidesInView);
        }
        const inView = emblaApi
          .slidesInView()
          .filter((index: number) => !slidesInView.includes(index));
        return slidesInView.concat(inView);
      });
    },
    [],
  );

  useEffect(() => {
    if (!emblaApi) return;
    onInit(emblaApi);
    onSelect(emblaApi);
    updateSlidesInView(emblaApi);
    emblaApi.on("reInit", onInit);
    emblaApi.on("reInit", onSelect);
    emblaApi.on("select", onSelect);
    emblaApi.on("slidesInView", updateSlidesInView);
  }, [emblaApi, onInit, onSelect, updateSlidesInView]);


  const getBannerDestination = (banner: {
    url?: string;
    aid?: number;
    vid?: number;
  }) => {
    if (banner.aid)
      return { type: "internal" as const, path: `/actress/${banner.aid}` };
    if (banner.vid)
      return { type: "internal" as const, path: `/watch/${banner.vid}` };
    if (banner.url?.trim())
      return { type: "external" as const, path: banner.url };
    return { type: "none" as const, path: null };
  };

  const handleImageError = (e: React.SyntheticEvent<HTMLImageElement>) => {
    const target = e.target as HTMLImageElement;
    target.src =
      "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='382' height='184' viewBox='0 0 382 184'%3E%3Crect width='382' height='184' fill='%23f3f4f6'/%3E%3Ctext x='191' y='92' text-anchor='middle' fill='%236b7280' font-family='system-ui, Noto Sans SC, sans-serif' font-weight='500' font-size='14'%3E图片加载失败%3C/text%3E%3C/svg%3E";
  };

  if (isPending)
    return (
      <BannerSkeleton
        showDots={showDots}
        slidesPerView={slidesPerView}
        aspectRatio={aspectRatio}
        mobileAspectRatio={mobileAspectRatio}
        desktopHeight={desktopHeight}
      />
    );
  if (isError) return <div>Error loading banner</div>;
  if (!data?.data?.length) return null;

  const reversedBanners = [...data.data].reverse();
  const slideClass =
    slidesPerView === "single"
      ? "embla__slide flex-[0_0_100%] min-w-0"
      : "embla__slide flex-[0_0_100%] min-w-0 sm:flex-[0_0_50%] lg:flex-[0_0_33.333%]";

  return (
    <div className="embla w-full overflow-hidden">
      <div ref={emblaRef} className="embla__viewport">
        <div className="embla__container flex gap-4">
          {reversedBanners.map((banner, index) => {
            const destination = getBannerDestination(banner);
            const imageAspectRatio = mobileAspectRatio
              ? `${mobileAspectRatio} sm:${aspectRatio}`
              : aspectRatio;

            const heightClass = desktopHeight
              ? `h-[160px] sm:${desktopHeight}`
              : "h-[160px]";

            const imageContent = (
              <Base64Image
                originalUrl={banner.thumb}
                inView={slidesInView.includes(index)}
                alt={banner.title}
                className={`w-full ${imageAspectRatio} ${heightClass} object-cover`}
                onError={handleImageError}
              />
            );

            const wrapperClasses =
              "relative bg-muted/50 h-auto sm:h-full dark:shadow-none dark:border-none shadow-lg shadow-[#E0E0E0] overflow-hidden rounded-xl transition-shadow " +
              (desktopHeight ? heightClass : imageAspectRatio) +
              " border border-[#E0E0E0] hover:shadow-xl";

            return (
              <div key={banner.id} className={slideClass}>
                {destination.type === "none" ? (
                  <div className={wrapperClasses}>{imageContent}</div>
                ) : destination.type === "external" ? (
                  <a
                    href={destination.path}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={`${wrapperClasses} cursor-pointer block`}
                    aria-label={banner.title}
                  >
                    {imageContent}
                  </a>
                ) : (
                  <Link
                    to={destination.path}
                    className={`${wrapperClasses} cursor-pointer block`}
                    aria-label={banner.title}
                  >
                    {imageContent}
                  </Link>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {showDots && scrollSnaps.length > 1 && (
        <div className="flex justify-center mt-4 space-x-2">
          {scrollSnaps.map((_, index) => (
            <button
              key={index}
              className={`w-2 h-2 rounded-full transition-colors ${
                index === selectedIndex
                  ? "bg-primary"
                  : "bg-muted-foreground/30 hover:bg-muted-foreground/50"
              }`}
              onClick={() => onDotButtonClick(index)}
              aria-label={`Go to slide ${index + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  );
};
