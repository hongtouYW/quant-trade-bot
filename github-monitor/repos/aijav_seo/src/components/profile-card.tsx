import { Button } from "@/components/ui/button.tsx";
import { cn } from "@/lib/utils.ts";
import { useTranslation } from "react-i18next";
import actorProfileIcon from "/actor_profile_icon.svg";
import { useSubscribeToActor } from "@/hooks/actor/useSubscribeActor.ts";
import { useSubscribeToPublisher } from "@/hooks/publisher/useSubscribePublisher.ts";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { Base64Image } from "@/components/Base64Image";

interface ProfileCardProps {
  item: {
    id: string | number;
    name: string;
    image?: string;
    is_subscribe?: number;
    add_time?: number;
    subscribe_time?: number;
    video_count?: number;
  };
  type: "actor" | "publisher";
  onSubscribe?: (item: any) => void;
  showSubscribeButton?: boolean;
  showVideoCount?: boolean;
  onClick?: (item: any) => void;
  className?: string;
}

export const ProfileCard = ({
  item,
  type,
  onSubscribe,
  showSubscribeButton = true,
  showVideoCount = true,
  onClick,
  className,
}: ProfileCardProps) => {
  const { t } = useTranslation();
  const { executeWithAuth } = useAuthAction();
  const { mutate: subscribeToActor } = useSubscribeToActor();
  const { mutate: subscribeToPublisher } = useSubscribeToPublisher();

  const getInitials = (name: string) => {
    return name
      .split(" ")
      .map((word) => word.charAt(0))
      .join("")
      .toUpperCase()
      .slice(0, 2);
  };

  const handleClick = () => {
    if (onClick) {
      onClick(item);
    }
  };

  const handleSubscribeClick = (e: React.MouseEvent) => {
    e.stopPropagation();

    if (onSubscribe) {
      executeWithAuth(() => onSubscribe(item));
      return;
    }

    // Default subscription logic with auth check
    executeWithAuth(() => {
      if (type === "actor") {
        subscribeToActor({
          aid: String(item.id),
        });
      } else if (type === "publisher") {
        subscribeToPublisher({
          pid: String(item.id),
        });
      }
    });
  };

  const getVideoCount = () => {
    return item.video_count || 0;
  };

  const getVideoCountTranslationKey = () => {
    return type === "actor" ? "series.works_count" : "series.series_count";
  };

  return (
    <div
      className={cn(
        "flex flex-col items-center border border-border bg-transparent text-card-foreground transition-colors duration-300 hover:bg-primary/10 hover:border-primary rounded-xl overflow-hidden py-[10px] px-[4px] sm:p-[10px] min-w-0",
        onClick && "cursor-pointer",
        className,
      )}
      onClick={handleClick}
    >
      <div className="size-[76px] sm:size-24">
        {item.image && item.image.trim() !== "" ? (
          <Base64Image
            originalUrl={item.image}
            alt="Card Background"
            className={cn(
              "w-full h-full rounded-full object-cover border-2 border-primary",
              type === "actor" ? "object-cover" : "object-contain",
            )}
            fallbackElement={
              type === "publisher" ? (
                <div className="w-full h-full rounded-full border-2 border-primary bg-primary/10 flex items-center justify-center text-primary font-bold text-2xl sm:text-3xl">
                  {getInitials(item.name)}
                </div>
              ) : (
                <div className="w-full h-full rounded-full border-2 border-primary bg-primary/10 flex items-center justify-center">
                  <img
                    src={actorProfileIcon}
                    alt="Actor icon"
                    className="w-8 h-8"
                  />
                </div>
              )
            }
          />
        ) : type === "publisher" ? (
          <div className="w-full h-full rounded-full border-2 border-primary bg-primary/10 flex items-center justify-center text-primary font-bold text-2xl sm:text-3xl">
            {getInitials(item.name)}
          </div>
        ) : (
          <div className="w-full h-full rounded-full border-2 border-primary bg-primary/10 flex items-center justify-center">
            <img src={actorProfileIcon} alt="Actor icon" className="w-8 h-8" />
          </div>
        )}
      </div>

      <div className="flex flex-col w-full items-center gap-1 justify-center overflow-hidden min-w-0 max-w-[76px] sm:max-w-[96px]">
        <h2 className="mt-2 text-xs sm:text-sm font-bold text-center truncate w-full px-1">
          {item.name}
        </h2>

        {showVideoCount && (
          <span className="text-xs sm:text-sm text-muted-foreground text-center w-full">
            {t(getVideoCountTranslationKey(), { count: getVideoCount() })}
          </span>
        )}

        {showSubscribeButton && (
          <Button
            className={cn(
              item?.is_subscribe === 1 || item?.add_time || item?.subscribe_time
                ? "bg-[#781938]"
                : "bg-[#EA1E61]",
              "mt-1 rounded-2xl py-1.5 px-2 sm:px-4 text-xs sm:text-sm h-7 sm:w-full w-[72px] text-white hover:opacity-90 transition-colors",
            )}
            size="sm"
            onClick={handleSubscribeClick}
          >
            {item?.is_subscribe === 1 || item?.add_time || item?.subscribe_time
              ? t("common.followed")
              : t("common.follow")}
          </Button>
        )}
      </div>
    </div>
  );
};
