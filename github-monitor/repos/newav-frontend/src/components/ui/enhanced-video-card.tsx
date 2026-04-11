import { Star, UserRoundIcon } from "lucide-react";
import { Link } from "react-router";
import { cn, getObjectPosition } from "@/lib/utils";
import { cva, type VariantProps } from "class-variance-authority";
import { useTranslation } from "react-i18next";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import React, { useMemo } from "react";
import yellowDiamondIcon from "@/assets/yellow-diamond-icon.png";
import { Base64Image } from "@/components/Base64Image";
import { FilmstripBackground } from "@/components/ui/filmstrip-background";
import logoOnly from "@/assets/logo.svg";

// Video data interface
export interface VideoCardData {
  id: number | string;
  title: string;
  mash_title?: string;
  thumb?: string;
  preview?: string;
  actor?: Array<{
    id?: number;
    name?: string;
    image?: string;
  }>;
  position?: "right" | "left" | "centre";
  play?: number | string;
  private?: number;
  is_new?: number;
  rating_count?: number;
  rating_avg?: number;
  zimu?: object;
  zimu_status?: number;
}

// Container variants
const videoCardVariants = cva(
  "overflow-hidden rounded-lg text-card-foreground transition-colors",
  {
    variants: {
      layout: {
        vertical: "my-1 mx-0.5", // Vertical layout - can include keen-slider__slide class when needed
        "vertical-large": "flex flex-col", // Grid-optimized vertical layout for VideoListPage
        "horizontal-compact":
          "flex gap-2 hover:bg-primary/10 ring ring-transparent duration-300 hover:ring-primary h-[120px] mr-1", // Unified horizontal layout
      },
      size: {
        xs: "",
        sm: "",
        md: "",
      },
    },
    defaultVariants: {
      layout: "vertical",
      size: "sm",
    },
  },
);

// Image container variants
const imageVariants = cva("relative overflow-hidden", {
  variants: {
    layout: {
      vertical: "rounded-[8px]",
      "vertical-large": "rounded-lg w-full",
      "horizontal-compact":
        "flex items-center justify-center sm:min-w-[200px] sm:max-w-[200px] h-full rounded-[8px] flex-shrink-0",
    },
    size: {
      xs: "h-[128px] md:h-[190px] sm:h-[211px]",
      sm: "h-[156px] sm:h-[221px] w-full md:min-w-[123px]",
      md: "min-w-[290px] w-full h-[200px]",
      compact: "max-h-[120px] w-[165px]", // For horizontal layouts
    },
  },
  defaultVariants: {
    layout: "vertical",
    size: "sm",
  },
});

// Content container variants
const contentVariants = cva("relative", {
  variants: {
    layout: {
      vertical:
        "py-1 sm:py-2 px-1 gap-1 sm:gap-2 justify-between flex flex-col",
      "vertical-large":
        "pt-1.5 px-1.5 pb-2 flex items-end justify-between gap-1",
      "horizontal-compact":
        "flex flex-col justify-between gap-2 py-1 h-full min-w-0 flex-1",
    },
    size: {
      xs: "h-[3rem] sm:h-[5rem]", // For vertical layouts - smaller for mobile
      sm: "h-[4.5rem]", // For vertical layouts
      md: "h-[4.5rem]", // For vertical layouts
      compact: "", // For horizontal layouts - no fixed height
      large: "", // For vertical-large layout - no fixed height
    },
  },
  defaultVariants: {
    layout: "vertical",
    size: "sm",
  },
});

// Title variants
const titleVariants = cva(
  "text-card-foreground leading-tight line-clamp-2 overflow-hidden",
  {
    variants: {
      layout: {
        vertical: "font-bold flex-shrink-0",
        "vertical-large": "text-sm font-semibold",
        "horizontal-compact": "font-medium text-sm mb-1",
      },
      size: {
        xs: "text-xs sm:text-sm",
        sm: "text-xs sm:text-sm",
        md: "text-xs sm:text-sm",
        compact: "text-sm", // For horizontal layouts
        large: "text-sm", // For vertical-large layout
      },
    },
    defaultVariants: {
      layout: "vertical",
      size: "sm",
    },
  },
);

// Subtitle variants
const subtitleVariants = cva("text-muted-foreground truncate h-[20px]", {
  variants: {
    size: {
      xs: "text-xs sm:text-sm",
      sm: "text-sm",
      md: "text-sm",
    },
  },
  defaultVariants: {
    size: "sm",
  },
});

// todo - check how to fix both mobile and desktop view
// Responsive width classes
const responsiveWidthClasses = {
  xs: "min-w-[120px] max-w-[120px] sm:max-w-[140px]",
  sm: "min-w-[117px] max-w-[117px] sm:max-w-[150px]",
  md: "min-w-[180px] max-w-[180px]",
};

// Reusable function to render video title with diamond icon for series (private === 3)
const renderVideoTitle = (
  video: VideoCardData,
  fallbackTitle: string = "Untitled Video",
  iconSize: number = 16,
  layout?: "vertical" | "vertical-large" | "horizontal-compact",
): React.ReactNode => {
  const title = video?.mash_title || fallbackTitle;

  if (video.private === 3 && layout !== "horizontal-compact") {
    return (
      <span className="flex gap-1">
        <img
          src={yellowDiamondIcon}
          className="mt-0.5"
          alt="Series"
          style={{ width: iconSize, height: iconSize }}
        />
        <span className="line-clamp-2">{title}</span>
      </span>
    );
  }

  return title;
};

// Component props interface
interface EnhancedVideoCardProps
  extends VariantProps<typeof videoCardVariants> {
  video: VideoCardData;
  layout?: "vertical" | "vertical-large" | "horizontal-compact";
  linkState?: {
    from: string;
    categoryName: string;
    seriesId?: string;
  };
  linkPrefix?: string;
  showBadges?: boolean;
  showRating?: boolean;
  showActor?: boolean;
  isActive?: boolean; // For highlighting current video
  imageOnly?: boolean; // For rendering only image with badges, no content section
  className?: string;
  imageContainerClassName?: string; // For overriding image container styles
}

// Video Image Fallback Component
const VideoImageFallback: React.FC<{
  orientation?: "horizontal" | "vertical";
}> = ({ orientation = "horizontal" }) => (
  <FilmstripBackground
    bgColor="#FCE9FF"
    orientation={orientation}
    className="w-full h-full flex items-center justify-center p-4"
  >
    <img src={logoOnly} alt="Logo" className="size-8 md:size-12" />
  </FilmstripBackground>
);

// Video Image Component using Base64Image for transformed URLs
export const VideoImage: React.FC<{
  src: string;
  alt: string;
  className?: string;
  onError?: (e: React.SyntheticEvent<HTMLImageElement>) => void;
  inView?: boolean;
  onLoad?: (e: React.SyntheticEvent<HTMLImageElement>) => void;
  fallbackOrientation?: "horizontal" | "vertical";
}> = ({
  src,
  alt,
  className,
  onError,
  inView,
  onLoad,
  fallbackOrientation = "horizontal",
}) => {
  return (
    <Base64Image
      originalUrl={src}
      alt={alt}
      className={className}
      onError={onError}
      inView={inView}
      onLoad={onLoad}
      fallbackElement={<VideoImageFallback orientation={fallbackOrientation} />}
    />
  );
};

// Smart Video Image Component with orientation detection
const SmartVideoImage: React.FC<{
  src: string;
  alt: string;
  landscapeClass?: string;
  portraitClass?: string;
  inView?: boolean;
  fallbackOrientation?: "horizontal" | "vertical";
}> = ({
  src,
  alt,
  landscapeClass = "",
  portraitClass = "",
  inView,
  fallbackOrientation = "horizontal",
}) => {
  const [isLandscape, setIsLandscape] = React.useState<boolean | null>(null);

  const handleLoad = (e: React.SyntheticEvent<HTMLImageElement>) => {
    const img = e.currentTarget;
    setIsLandscape(img.naturalWidth >= img.naturalHeight);
  };

  return (
    <VideoImage
      src={src}
      alt={alt}
      className={cn(
        "transition-all duration-200",
        isLandscape === null && portraitClass, // Default to portrait while loading
        isLandscape === true && landscapeClass,
        isLandscape === false && portraitClass,
      )}
      onLoad={handleLoad}
      inView={inView}
      fallbackOrientation={fallbackOrientation}
    />
  );
};

// Badge Component - Corner badges for vertical layouts
const CornerBadge: React.FC<{
  video: VideoCardData;
  t: (key: string) => string;
}> = ({ video, t }) => {
  return (
    <>
      {video.is_new === 1 && (
        <div className="absolute top-0 z-20 left-0 bg-primary text-primary-foreground text-xs font-bold tracking-tight px-2.5 py-1 rounded-br-lg">
          {t("common.new")}
        </div>
      )}
      {video.private === 0 && (
        <div className="absolute bottom-0 z-20 right-0 bg-[#06C149] text-white text-xs font-bold tracking-tight px-2.5 py-1 rounded-tl-lg">
          {t("homepage.free")}
        </div>
      )}
      {video.private === 2 && (
        <div className="absolute bottom-0 z-20 right-0 bg-brand-gold text-[color:var(--vip-text)] text-xs font-bold uppercase tracking-tight px-2.5 py-1 rounded-tl-lg">
          VIP
        </div>
      )}
      {video.zimu_status === 4 && (
        <div
          className="absolute bottom-0 z-20 left-0 text-white text-xs font-bold tracking-tight px-2.5 py-1 rounded-tr-lg"
          style={{ backgroundColor: "#67AEFF" }}
        >
          {t("common.cc")}
        </div>
      )}
    </>
  );
};

// Badge Component - Pill style for horizontal layouts
const HorizontalBadge: React.FC<{
  video: VideoCardData;
  t: (key: string) => string;
}> = ({ video, t }) => {
  const badges = [];

  if (video.is_new === 1) {
    badges.push(
      <span
        key="new"
        className="rounded-full font-semibold text-[10px] bg-primary text-primary-foreground h-5 px-2 flex items-center"
      >
        {t("common.new")}
      </span>,
    );
  }

  if (video.private === 0) {
    badges.push(
      <span
        key="free"
        className="rounded-full font-semibold text-[10px] bg-[#06C149] text-white h-5 px-2 flex items-center"
      >
        {t("homepage.free")}
      </span>,
    );
  }

  if (video.private === 2) {
    badges.push(
      <span
        key="vip"
        className="rounded-full bg-brand-gold text-[10px] text-[color:var(--vip-text)] font-semibold h-5 px-2 flex items-center"
      >
        VIP
      </span>,
    );
  }

  if (video.private === 3) {
    badges.push(
      <img
        key="series"
        src={yellowDiamondIcon}
        className="size-5"
        alt="Series"
      />,
    );
  }

  if (video.zimu_status === 4) {
    badges.push(
      <span
        key="cc"
        className="rounded-full font-semibold text-[10px] text-white h-5 px-2 flex items-center"
        style={{ backgroundColor: "#67AEFF" }}
      >
        {t("common.cc")}
      </span>,
    );
  }

  return badges.length > 0 ? <div className="flex gap-1">{badges}</div> : null;
};

export const EnhancedVideoCard: React.FC<EnhancedVideoCardProps> = ({
  video,
  layout = "vertical",
  size = "sm",
  linkState,
  linkPrefix = "/watch",
  showBadges = true,
  showRating = true,
  showActor = true,
  isActive = false,
  imageOnly = false,
  className,
  imageContainerClassName,
}) => {
  const { t } = useTranslation();
  const imageSource =
    video?.preview || video?.thumb || "/placeholder-video.jpg";

  // Determine image size based on layout
  const imageSize = layout === "horizontal-compact" ? "compact" : size;
  const contentSize =
    layout === "horizontal-compact"
      ? "compact"
      : layout === "vertical-large"
        ? "large"
        : size;

  // Get up to 3 actors from the array
  const actors = useMemo(() => {
    if (!video?.actor) return [];
    return video.actor;
  }, [video?.actor]);
  const visibleActors = actors.slice(0, 2);
  const remainingActorsCount = actors.length - visibleActors.length;

  // Generate proper alt text
  const actorNames = actors.map((actor) => actor.name).join(", ");
  const altText = `${video?.mash_title || "Video"} thumbnail${
    video.private === 3 ? " (Series)" : ""
  }${actorNames ? ` - Starring ${actorNames}` : ""}${
    video.private === 0 ? " - Free" : video.private === 2 ? " - VIP" : ""
  }`;

  // Render vertical-large layout
  const renderVerticalLargeLayout = () => (
    <Link
      to={`${linkPrefix}/${video.id}`}
      state={linkState}
      className="relative w-full h-full group"
    >
      <div
        className={cn(
          imageVariants({ layout, size: imageSize }),
          imageContainerClassName,
        )}
      >
        <VideoImage
          src={imageSource}
          alt={altText}
          className="absolute inset-0 w-full h-full object-cover"
          fallbackOrientation="horizontal"
        />
        {showBadges && <CornerBadge video={video} t={t} />}
      </div>
      <div className={contentVariants({ layout, size: contentSize })}>
        <h3 className={titleVariants({ layout, size: contentSize })}>
          {renderVideoTitle(video, "Untitled Video", 16, layout)}
        </h3>
        {showRating && !!video?.rating_avg && video.rating_avg > 0 && (
          <span className="flex text-xs items-center text-[#FFC02D] flex-shrink-0">
            ★ {video.rating_avg}
          </span>
        )}
      </div>
    </Link>
  );

  // Render vertical layout
  const renderVerticalLayout = () => (
    <Link
      to={`${linkPrefix}/${video.id}`}
      state={linkState}
      className="relative bg-card text-card-foreground w-full h-full group transition-colors"
    >
      <div className={imageVariants({ layout, size: imageSize })}>
        <VideoImage
          src={imageSource}
          alt={altText}
          className={cn(
            "absolute inset-0 w-full h-full object-cover",
            getObjectPosition(video.position),
          )}
          fallbackOrientation="vertical"
        />
        {showBadges && layout === "vertical" && (
          <CornerBadge video={video} t={t} />
        )}
      </div>
    </Link>
  );

  // Render horizontal layout image
  const renderHorizontalLayout = () => (
    <Link
      to={`${linkPrefix}/${video.id}`}
      state={linkState}
      className={imageVariants({ layout, size: imageSize })}
    >
      <div className="w-full h-full flex items-center justify-center bg-muted/50">
        <SmartVideoImage
          src={imageSource}
          alt={altText}
          landscapeClass="w-full h-full object-cover"
          portraitClass="max-w-full max-h-full object-contain"
          fallbackOrientation="horizontal"
        />
      </div>
    </Link>
  );

  // Render horizontal content
  const renderHorizontalContent = () => (
    <>
      <Link
        to={`${linkPrefix}/${video.id}`}
        state={linkState}
        className="space-y-2 flex-1 min-h-0 min-w-0"
      >
        {showBadges && (
          <div className="flex gap-1 mb-2">
            <HorizontalBadge video={video} t={t} />
          </div>
        )}
        <h3 className={titleVariants({ layout, size: contentSize })}>
          {renderVideoTitle(video, "Untitled Video", 14, layout)}
        </h3>
      </Link>
      <div className="flex flex-col gap-1 min-w-0">
        {showActor && visibleActors.length > 0 && (
          <div className="flex items-center gap-2 overflow-hidden">
            <div className="flex items-center gap-1 overflow-hidden">
              {visibleActors.map((actor) => (
                <Link
                  key={actor.id}
                  to={`/actress/${actor.id}`}
                  aria-label={`View ${actor.name} profile`}
                  className="flex items-center gap-1 max-w-full overflow-hidden"
                >
                  <Avatar className="size-5 rounded-full flex-shrink-0">
                    {actor.image ? (
                      <Base64Image
                        originalUrl={actor.image}
                        alt={`${actor.name} avatar`}
                        className="w-full h-full object-cover rounded-full"
                      />
                    ) : (
                      <AvatarFallback>
                        <UserRoundIcon
                          size={16}
                          className="opacity-60"
                          aria-hidden="true"
                        />
                      </AvatarFallback>
                    )}
                  </Avatar>
                  <span className="text-xs text-[#757575] font-medium max-w-[96px] overflow-hidden text-ellipsis whitespace-nowrap">
                    {actor.name}
                  </span>
                </Link>
              ))}
            </div>
            {remainingActorsCount > 0 && (
              <span className="rounded-full bg-[#F5F5F5] px-2 py-0.5 text-[10px] font-semibold text-[#616161] whitespace-nowrap flex-shrink-0">
                +{remainingActorsCount}
              </span>
            )}
          </div>
        )}
        {showRating && !!video?.rating_avg && video.rating_avg > 0 && (
          <div
            className="flex items-center text-yellow-400 h-[16px]"
            aria-label={`Rating: ${video.rating_avg} stars`}
          >
            <Star className="w-3 h-3 fill-current" aria-hidden="true" />
            <span className="text-xs font-medium ml-1">{video.rating_avg}</span>
          </div>
        )}
      </div>
    </>
  );

  // Render vertical content
  const renderVerticalContent = () => (
    <Link
      to={`${linkPrefix}/${video.id}`}
      state={linkState}
      className="w-full h-full flex gap-1.5 flex-col justify-between"
    >
      <h3 className={titleVariants({ layout, size })}>
        {renderVideoTitle(video, "Untitled Video", 16, layout)}
      </h3>
      <div className="flex justify-between items-center">
        {showActor && actors.length > 0 && (
          <span className={subtitleVariants({ size })}>
            {actors.map((actor) => actor.name).join(", ")}
          </span>
        )}
        {showRating && !!video?.rating_avg && video.rating_avg > 0 && (
          <div
            className="flex items-center text-yellow-400"
            aria-label={`Rating: ${video.rating_avg} stars`}
          >
            <Star className="w-3 h-3 fill-current" aria-hidden="true" />
            <span
              className={cn(
                "ml-1",
                size === "xs" ? "text-xs" : "text-sm",
                "font-medium",
              )}
            >
              {video.rating_avg}
            </span>
          </div>
        )}
      </div>
    </Link>
  );

  return (
    <div
      className={cn(
        videoCardVariants({ layout, size }),
        // Add keen-slider class for vertical layout (maintains backward compatibility)
        layout === "vertical" && "keen-slider__slide",
        // Apply responsive width classes for vertical layout
        layout === "vertical" && responsiveWidthClasses[size || "sm"],
        // Apply hover effects for vertical layouts
        (layout === "vertical" || layout === "vertical-large") &&
          "hover:bg-primary/20 transition-colors ring ring-transparent duration-300 hover:ring-primary",
        // Highlight active video for horizontal layouts
        isActive && "bg-primary/10",
        className,
      )}
    >
      {/* Image Container */}
      {layout === "vertical-large" && renderVerticalLargeLayout()}
      {layout === "vertical" && renderVerticalLayout()}
      {layout === "horizontal-compact" && renderHorizontalLayout()}

      {/* Content Section */}
      {!imageOnly && layout !== "vertical-large" && (
        <div className={contentVariants({ layout, size: contentSize })}>
          {layout === "horizontal-compact"
            ? renderHorizontalContent()
            : renderVerticalContent()}
        </div>
      )}
    </div>
  );
};
