import { useMemo, useState } from "react";
import useEmblaCarousel from "embla-carousel-react";
import { Base64Image } from "@/components/Base64Image";
import { ImageViewer } from "./ImageViewer";

interface ThumbnailGalleryProps {
  thumb: string;
  thumbSeries: string;
}

export function ThumbnailGallery({
  thumb,
  thumbSeries,
}: ThumbnailGalleryProps) {
  const [viewerOpen, setViewerOpen] = useState(false);
  const [selectedThumbnailIndex, setSelectedThumbnailIndex] = useState(0);

  const [emblaRef] = useEmblaCarousel({
    align: "start",
    skipSnaps: false,
    dragFree: true,
  });

  // Parse thumb_series string and generate thumbnail URLs
  const thumbnailUrls = useMemo(() => {
    if (!thumb || !thumbSeries) return [];

    // Extract the base URL from thumb (remove filename)
    const filename = thumb.substring(0, thumb.lastIndexOf("."));
    const extension = thumb.substring(thumb.lastIndexOf("."));

    // Parse the comma-separated indices
    const indices = thumbSeries
      .split(",")
      .map((idx) => idx.trim())
      .filter((idx) => idx.length > 0);

    // Generate thumbnail URLs with indices
    return indices.map((index) => {
      return `${filename}_thumb_${index}${extension}`;
    });
  }, [thumb, thumbSeries]);

  if (thumbnailUrls.length === 0) {
    return null;
  }

  return (
    <div className="w-full">
      <div className="overflow-hidden rounded-lg" ref={emblaRef}>
        <div className="flex gap-2 touch-pan-y">
          {thumbnailUrls.map((url, index) => (
            <div
              key={index}
              className="flex-shrink-0 min-w-0 aspect-video w-40 sm:w-60 rounded-lg overflow-hidden cursor-pointer hover:opacity-80 transition-opacity"
              onClick={() => {
                setSelectedThumbnailIndex(index);
                setViewerOpen(true);
              }}
            >
              <Base64Image
                originalUrl={url}
                alt={`Thumbnail ${index}`}
                className="w-full h-full object-cover"
              />
            </div>
          ))}
        </div>
      </div>

      <ImageViewer
        images={thumbnailUrls}
        initialIndex={selectedThumbnailIndex}
        open={viewerOpen}
        onOpenChange={setViewerOpen}
      />
    </div>
  );
}
