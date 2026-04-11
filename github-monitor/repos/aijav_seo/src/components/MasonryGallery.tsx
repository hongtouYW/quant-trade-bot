import { useState, useEffect } from "react";
import Masonry from "react-masonry-css";
import { Skeleton } from "@/components/ui/skeleton";
import { X, ChevronLeft, ChevronRight, Image as ImageIcon } from "lucide-react";
import { Base64Image } from "@/components/Base64Image";
import "./MasonryGallery.css";

interface Photo {
  id: number;
  image: string;
  sort: number;
}

interface MasonryImage {
  id: number;
  image: string;
}

interface MasonryGalleryProps {
  photos?: Photo[];
  isLoading?: boolean;
}

export const MasonryGallery = ({ photos = [], isLoading = false }: MasonryGalleryProps) => {
  const [images, setImages] = useState<MasonryImage[]>([]);
  const [imageLoading, setImageLoading] = useState(isLoading);
  const [selectedImageIndex, setSelectedImageIndex] = useState<number | null>(null);

  useEffect(() => {
    // Transform photos data to match MasonryImage format
    if (photos && photos.length > 0) {
      const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;
      const transformedImages: MasonryImage[] = photos.map((photo) => {
        // Handle different URL formats:
        // 1. If URL already starts with http(s), use as-is
        // 2. If URL starts with /, prepend API base URL without the leading /
        // 3. Otherwise, prepend API base URL
        let imageUrl = photo.image;

        if (!imageUrl.startsWith("http")) {
          if (imageUrl.startsWith("/")) {
            imageUrl = `${API_BASE_URL}${imageUrl.slice(1)}`;
          } else {
            imageUrl = `${API_BASE_URL}${imageUrl}`;
          }
        }

        return {
          id: photo.id,
          image: imageUrl,
        };
      });
      setImages(transformedImages);
    } else {
      setImages([]);
    }
    setImageLoading(false);
  }, [photos]);

  // Keyboard navigation effect
  useEffect(() => {
    if (selectedImageIndex === null) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setSelectedImageIndex(null);
      } else if (e.key === "ArrowLeft" && selectedImageIndex > 0) {
        setSelectedImageIndex(selectedImageIndex - 1);
      } else if (e.key === "ArrowRight" && selectedImageIndex < images.length - 1) {
        setSelectedImageIndex(selectedImageIndex + 1);
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [selectedImageIndex, images.length]);

  if (imageLoading) {
    return (
      <div className="p-4">
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="rounded-lg overflow-hidden">
              <Skeleton className="w-full h-64" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (images.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <ImageIcon size={48} className="text-muted-foreground/40 mb-4" />
        <p className="text-lg text-muted-foreground">No photos available</p>
        <p className="text-sm text-muted-foreground/60 mt-2">
          Photos will appear here when added by the actress
        </p>
      </div>
    );
  }

  const breakpoints = {
    default: 4,
    1024: 3,
    768: 2,
    640: 2,
  };

  const handleImageClick = (index: number) => {
    setSelectedImageIndex(index);
  };

  const handleClose = () => {
    setSelectedImageIndex(null);
  };

  const handlePrevious = () => {
    if (selectedImageIndex !== null && selectedImageIndex > 0) {
      setSelectedImageIndex(selectedImageIndex - 1);
    }
  };

  const handleNext = () => {
    if (selectedImageIndex !== null && selectedImageIndex < images.length - 1) {
      setSelectedImageIndex(selectedImageIndex + 1);
    }
  };


  return (
    <>
      <div className="">
        <Masonry
          breakpointCols={breakpoints}
          className="masonry-grid"
          columnClassName="masonry-grid-column"
        >
          {images.map((image, index) => (
            <div
              key={image.id}
              className="masonry-item rounded-lg overflow-hidden cursor-pointer group"
              onClick={() => handleImageClick(index)}
            >
              <Base64Image
                originalUrl={image.image}
                alt={`gallery-item-${image.id}`}
                className="w-full h-auto object-cover group-hover:scale-105 transition-transform duration-300"
              />
            </div>
          ))}
        </Masonry>
      </div>

      {selectedImageIndex !== null && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
          onClick={handleClose}
        >
          <div
            className="relative max-w-4xl max-h-[90vh] w-full h-full flex items-center justify-center"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Close button */}
            <button
              onClick={handleClose}
              className="absolute top-4 right-4 z-10 p-2 rounded-full bg-black/50 hover:bg-black/70 text-white transition"
              aria-label="Close"
            >
              <X size={24} />
            </button>

            {/* Image */}
            <Base64Image
              originalUrl={images[selectedImageIndex].image}
              alt={`View ${selectedImageIndex + 1}`}
              className="max-w-full max-h-[80vh] object-contain"
            />

            {/* Previous button */}
            {selectedImageIndex > 0 && (
              <button
                onClick={handlePrevious}
                className="absolute left-4 top-1/2 -translate-y-1/2 p-2 rounded-full bg-black/50 hover:bg-black/70 text-white transition"
                aria-label="Previous image"
              >
                <ChevronLeft size={24} />
              </button>
            )}

            {/* Next button */}
            {selectedImageIndex < images.length - 1 && (
              <button
                onClick={handleNext}
                className="absolute right-4 top-1/2 -translate-y-1/2 p-2 rounded-full bg-black/50 hover:bg-black/70 text-white transition"
                aria-label="Next image"
              >
                <ChevronRight size={24} />
              </button>
            )}

            {/* Image counter */}
            <div className="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/50 text-white px-4 py-2 rounded-full text-sm">
              {selectedImageIndex + 1} / {images.length}
            </div>
          </div>
        </div>
      )}
    </>
  );
};
