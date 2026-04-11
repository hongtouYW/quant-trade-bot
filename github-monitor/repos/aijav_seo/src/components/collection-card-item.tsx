import React from "react";
import { useTranslation } from "react-i18next";
import { Heart, CircleX } from "lucide-react";
import { cn } from "@/lib/utils";
import { Base64Image } from "@/components/Base64Image.tsx";

interface CollectionCardItemProps {
  title: string;
  count?: number;
  countSuffix?: string;
  icon?: React.ReactNode;
  bgColor?: string;
  iconColor?: string;
  backgroundImage?: string;
  imageAlt?: string;
  isEditMode?: boolean;
  onRemove?: () => void;
  isRemoving?: boolean;
  onClick?: () => void;
  className?: string;
  containerClassName?: string;
}

const CollectionCardItem: React.FC<CollectionCardItemProps> = ({
  title,
  count,
  icon,
  bgColor = "bg-[#E126FC]",
  iconColor = "text-white",
  backgroundImage,
  imageAlt = "Card background",
  isEditMode = false,
  onRemove,
  isRemoving = false,
  onClick,
  className,
  countSuffix,
  containerClassName,
}) => {
  const { t } = useTranslation();
  const suffix = countSuffix ?? t("my_favorites.video_count_suffix");
  return (
    <div
      className={cn(
        "flex flex-col items-start space-y-2 sm:space-y-3 group cursor-pointer w-full max-w-[150px]",
        containerClassName,
      )}
      onClick={onClick}
    >
      {/* Card */}
      <div
        className={cn(
          !backgroundImage ? bgColor : "bg-gray-200",
          "size-full rounded-2xl flex items-center justify-center shadow-lg hover:shadow-xl transform transition-all duration-300 relative overflow-hidden",
          className,
        )}
      >
        {/* Background Image */}
        {backgroundImage && (
          <Base64Image
            originalUrl={backgroundImage}
            alt={imageAlt}
            className="absolute inset-0 w-full h-full object-cover"
          />
        )}

        {/* Overlay for better icon visibility when using background image */}
        {backgroundImage && (
          <div className="absolute inset-0 bg-black/20 group-hover:bg-black/30 transition-colors duration-300" />
        )}

        {/* Background pattern for depth (only for gradient backgrounds) */}
        {!backgroundImage && (
          <div className="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
        )}

        {/* Icon - only show if no background image */}
        {!backgroundImage && (
          <div
            className={`${iconColor} z-10 transition-transform duration-300 group-hover:scale-110`}
          >
            {icon || <Heart className="size-18" fill="currentColor" />}
          </div>
        )}

        {/*/!* Icon overlay for background images - smaller and with better contrast *!/*/}
        {/*{backgroundImage && (*/}
        {/*  <div className="absolute bottom-2 right-2 z-10">*/}
        {/*    <div className="bg-white/90 rounded-full p-1.5 sm:p-2 shadow-sm">*/}
        {/*      <div className="text-gray-700 transition-transform duration-300 group-hover:scale-110">*/}
        {/*        {icon || (*/}
        {/*          <Heart*/}
        {/*            size={20}*/}
        {/*            className="sm:w-6 sm:h-6"*/}
        {/*            fill="currentColor"*/}
        {/*          />*/}
        {/*        )}*/}
        {/*      </div>*/}
        {/*    </div>*/}
        {/*  </div>*/}
        {/*)}*/}

        {/* Edit Mode Overlay */}
        {isEditMode && onRemove && (
          <div
            onClick={(e) => {
              e.stopPropagation();
              if (!isRemoving) {
                onRemove();
              }
            }}
            className={cn(
              "absolute inset-0 bg-black/50 flex items-center justify-center rounded-2xl z-20 transition-colors duration-200 group/overlay",
              isRemoving
                ? "cursor-not-allowed bg-black/70"
                : "cursor-pointer hover:bg-black/60",
            )}
          >
            <div
              className={cn(
                "flex items-center gap-2 transition-transform duration-200",
                isRemoving ? "opacity-50" : "group-hover/overlay:scale-105",
              )}
            >
              <CircleX
                className={cn(
                  "size-5 text-white transition-colors duration-200",
                  !isRemoving && "group-hover/overlay:text-destructive",
                )}
              />
              <span
                className={cn(
                  "text-white text-base font-medium transition-colors duration-200",
                  !isRemoving && "group-hover/overlay:text-white/90",
                )}
              >
                {isRemoving
                  ? t("common.removing") || "Removing..."
                  : t("my_favorites.remove_favorite")}
              </span>
            </div>
          </div>
        )}
      </div>

      {/* Text content */}
      <div className="text-center flex flex-col md:flex-row md:justify-between items-start md:items-center gap-1 w-full">
        <h3 className="text-xs sm:text-sm font-medium truncate pr-1 text-gray-900 dark:text-gray-100">
          {title}
        </h3>
        {count !== undefined && (
          <p className="text-xs sm:text-sm font-normal text-gray-600 dark:text-gray-400">
            {count}
            {suffix}
          </p>
        )}
      </div>
    </div>
  );
};

export default CollectionCardItem;
