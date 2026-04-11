import { Link } from "react-router";
import useEmblaCarousel from "embla-carousel-react";
import { Badge } from "@/components/ui/badge.tsx";

type TagsCarouselProps = {
  tags?: Array<{ id: number; name: string }> | null;
};

export const TagsCarousel = ({ tags }: TagsCarouselProps) => {
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
  });

  if (!tags || tags.length === 0) {
    return null;
  }

  return (
    <div className="mt-4 overflow-hidden" ref={emblaRef}>
      <div className="flex gap-1.5">
        {tags.map((tag) => (
          <Badge
            key={tag?.id}
            className="py-0.5 px-2.5 rounded-[6px] bg-[#FCE9FF] [a&]:hover:bg-[#FCE9FF] text-sm text-black font-medium flex-shrink-0"
            asChild
          >
            <Link
              className="text-xs sm:text-base"
              to={`/video/list?tag=${tag?.id}&tagName=${encodeURIComponent(tag?.name || "")}`}
            >
              {tag?.name}
            </Link>
          </Badge>
        ))}
      </div>
    </div>
  );
};
