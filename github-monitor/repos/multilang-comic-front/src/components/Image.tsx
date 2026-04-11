import { useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { useConfig } from "../contexts/config.context";
import { fetchData, formatImgUrl, imgDecrypt } from "../utils/utils";

const Image = ({
  src,
  alt,
  className,
  id,
  loading,
  blurBg = true,
  borderRadius = "rounded-sm",
  imageSize,
  size = true,
  scrollContainerRef,
  eagerLoad = false,
}: {
  src: string;
  alt?: string;
  className?: string;
  id?: string;
  loading?: "lazy" | "eager";
  blurBg?: boolean;
  borderRadius?: string;
  imageSize?: string | null;
  size?: boolean;
  scrollContainerRef?: React.RefObject<HTMLElement | null>;
  eagerLoad?: boolean;
}) => {
  const { config } = useConfig();
  const [imageUrl, setImageUrl] = useState<string>(
    `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/default-image-2.png`
  );
  const [isInView, setIsInView] = useState<boolean>(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const fmtImageUrl = useCallback(async (src: string) => {
    const imageUrlKey =
      import.meta.env.VITE_STATIC_IMAGE_HOST || "https://toonmh.a791243y.com/";
    // console.log("imageUrlKey", imageUrlKey);

    const imgSrc = await formatImgUrl(
      imageUrlKey,
      src,
      size,
      imageSize || "600x600"
    );
    if (imgSrc) {
      return setImageUrl(imgSrc);
    }

    if (imageUrlKey !== "" && src !== "") {
      const encryptUrls = `${imageUrlKey}/${src}.txt`;

      const res = await fetchData(encryptUrls);

      let __decrypted = "";
      if (res) {
        __decrypted = res.indexOf("data") >= 0 ? res : imgDecrypt(res);
        return setImageUrl(__decrypted);
      }
    }
  }, [size, imageSize]);

  // Set up IntersectionObserver for lazy loading
  useLayoutEffect(() => {
    // If marked for eager loading, load immediately
    if (eagerLoad) {
      setIsInView(true);
      fmtImageUrl(src);
      return;
    }

    // If scrollContainerRef is provided but not yet available, wait
    if (scrollContainerRef && !scrollContainerRef.current) {
      return;
    }

    // Add delay to prevent race condition where all images load at once
    const timer = setTimeout(() => {
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              setIsInView(true);
              fmtImageUrl(src);
              // Stop observing once visible
              if (containerRef.current) {
                observer.unobserve(containerRef.current);
              }
            }
          });
        },
        {
          root: scrollContainerRef?.current || null,
          rootMargin: "50px", // Start loading 50px before the image comes into view
          threshold: 0.01, // Must be at least 1% visible
        }
      );

      if (containerRef.current) {
        observer.observe(containerRef.current);
      }
    }, 100); // 100ms delay to let browser paint first

    return () => {
      clearTimeout(timer);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [src, config?.image_host, fmtImageUrl, scrollContainerRef?.current, eagerLoad]);

  // Re-fetch if src/config changes while already visible
  useEffect(() => {
    if (isInView) {
      fmtImageUrl(src);
    }
  }, [src, config?.image_host, fmtImageUrl, isInView]);

  return (
    <>
      {blurBg ? (
        <div ref={containerRef} className="relative w-full h-full overflow-hidden">
          <div className={`overflow-hidden w-full h-full ${borderRadius}`}>
            <img
              id={id}
              src={
                imageUrl ||
                `${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/default-image-2.png`
              }
              alt={alt}
              className={`${className} blur-[5px] object-cover`}
              loading={loading}
            />
          </div>
          <img
            id={id}
            src={
              imageUrl ||
              `${import.meta.env.VITE_INDEX_DOMAIN
              }/assets/images/default-image-2.png`
            }
            alt={alt}
            className={`${className} object-contain absolute top-0 left-0`}
            loading={loading}
          />
        </div>
      ) : (
        <div ref={containerRef}>
          <img
            id={id}
            src={imageUrl || "/assets/images/default-image-2.png"}
            alt={alt}
            className={className}
            loading={loading}
          />
        </div>
      )}
    </>
  );
};

export default Image;
