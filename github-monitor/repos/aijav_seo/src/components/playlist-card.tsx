import { Link } from "react-router";
import diamondIcon from "@/assets/diamond-icon.png";
import purchasedIcon from "@/assets/purchased.png";
import { useTranslation } from "react-i18next";
import { Base64Image } from "@/components/Base64Image";
import seriesIcon from "/series_icon.svg";

interface PlaylistCardProps {
  item: {
    id: number;
    title: string;
    description: string;
    amount: number;
    image: string;
    total_video: number;
    is_purchase?: number;
    is_collect?: number;
    videos?: { length?: number } | any[];
  };
  index: number;
  linkState?: {
    from: string;
    categoryName: string;
  };
  showVideosCount?: boolean;
  imageSize?: "fixed" | "responsive"; // New prop to control image sizing
}

export const PlaylistCard = ({
  item,
  linkState,
  showVideosCount = false,
  imageSize = "fixed", // Default to fixed for backward compatibility
}: PlaylistCardProps) => {
  const { t } = useTranslation();

  const getVideosCount = () => {
    if (Array.isArray(item.videos)) {
      return item.videos.length;
    }
    return item.videos?.length || 0;
  };

  return (
    <div className="keen-slider__slide my-1 mx-0.5 hover:bg-[#EC67FF]/20 transition-colors overflow-hidden ring ring-transparent duration-300 rounded-xl hover:ring-[#EC67FF]">
      <Link
        to={`/series/${item.id}`}
        state={linkState}
        className="flex flex-col items-start w-full h-full"
      >
        <div
          className={
            imageSize === "responsive"
              ? "w-full aspect-square rounded-xl overflow-hidden"
              : "h-[150px] w-[150px] sm:h-[180px] sm:w-[180px] rounded-xl overflow-hidden"
          }
        >
          <Base64Image
            originalUrl={item.image}
            className="w-full h-full object-cover"
            alt="Playlist thumbnail"
            fallbackElement={
              <div className="w-full h-full bg-primary/10 hover:border-b grid place-items-center">
                <div className="text-center">
                  <img
                    src={seriesIcon}
                    alt="Series icon"
                    className="w-12 h-12 mx-auto mb-2"
                  />
                </div>
              </div>
            }
          />
        </div>

        {/* Card Content */}
        <div className="pt-2 px-1.5 relative w-full max-w-[150px] sm:max-w-full">
          {/* Title */}
          <h3 className="font-bold text-sm capitalize truncate sm:max-w-[170px] text-card-foreground leading-tight mb-1 line-clamp-2">
            {item.title}
          </h3>

          <div className="flex justify-between items-baseline">
            <span className="text-muted-foreground truncate text-xs h-[20px]">
              {showVideosCount
                ? `${item.total_video} ${t("common.videos")}`
                : getVideosCount()}
            </span>
            {item.is_purchase === 1 ? (
              <div className="flex items-center">
                <img
                  src={purchasedIcon}
                  className="w-3 h-3"
                  alt="pruchased icon"
                />
                <span className="text-xs text-muted-foreground ml-1">
                  {t("common.purchased")}
                </span>
              </div>
            ) : (
              <div className="flex items-center">
                <img
                  src={diamondIcon}
                  className="w-3 h-3"
                  alt="currency icon"
                />
                <span className="text-xs text-muted-foreground font-medium ml-1">
                  {item.amount}
                </span>
              </div>
            )}
          </div>
        </div>
      </Link>
    </div>
  );
};
