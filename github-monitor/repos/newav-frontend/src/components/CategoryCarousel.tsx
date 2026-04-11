import useEmblaCarousel from "embla-carousel-react";
import { cn } from "@/lib/utils.ts";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import type { Video } from "@/types/video.types.ts";

interface CategoryCarouselProps {
  videos: Video[];
  categoryName: string;
}

export const CategoryCarousel = ({
  videos,
  categoryName,
}: CategoryCarouselProps) => {
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
    <div className="embla mt-3.5 w-full overflow-hidden">
      <div ref={emblaRef} className={cn("embla__viewport")}>
        <div className="embla__container flex">
          {videos.map((item) => (
            <div
              key={item.id}
              className="embla__slide flex-[0_0_auto] min-w-0 mr-3"
            >
              <EnhancedVideoCard
                video={item}
                layout="vertical"
                size="sm"
                linkState={{ from: "category", categoryName }}
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
  );
};
