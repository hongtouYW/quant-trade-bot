import { Star } from "lucide-react";
import { Link } from "react-router";
import { cn } from "@/lib/utils";

export interface VideoCardData {
  id: number | string;
  title: string;
  thumb: string;
  actor?: {
    name?: string;
  };
  play?: number | string;
  private?: number;
}

interface VideoCardProps {
  video: VideoCardData;
  size?: 'small' | 'medium' | 'large';
  showBadges?: boolean;
  className?: string;
  linkPrefix?: string;
}

export const VideoCard: React.FC<VideoCardProps> = ({
  video,
  size = 'small',
  showBadges = true,
  className,
  linkPrefix = '/watch'
}) => {
  const sizeConfig = {
    small: {
      container: "w-[150px]",
      image: "h-[221px]",
      title: "text-sm",
      subtitle: "text-sm",
      rating: "text-xs"
    },
    medium: {
      container: "w-[180px]", 
      image: "h-[260px]",
      title: "text-base",
      subtitle: "text-sm",
      rating: "text-sm"
    },
    large: {
      container: "w-[220px]",
      image: "h-[320px]", 
      title: "text-lg",
      subtitle: "text-base",
      rating: "text-base"
    }
  };

  const config = sizeConfig[size];

  return (
    <div className={cn(config.container, className)}>
      <Link
        to={`${linkPrefix}/${video.id}`}
        className="relative bg-card text-card-foreground w-full h-full group transition-colors overflow-hidden ring ring-transparent duration-300 rounded-lg hover:bg-primary/10 hover:ring-primary"
      >
        {/* Image Container */}
        <div className={cn("relative rounded-[8px] overflow-hidden", config.image)}>
          <img
            loading="lazy"
            src={video.thumb}
            className="absolute inset-0 w-full h-full object-cover object-right"
            alt="thumbnail"
          />
          
          {/* Badges */}
          {showBadges && (
            <>
              {/* NEW Badge - Top Left */}
              {video.private === 0 && (
                <div className="absolute top-0 z-20 left-0 text-white bg-primary text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-br-lg">
                  新片
                </div>
              )}
              
              {/* VIP Badge - Bottom Right */}
              {video.private !== 0 && (
                <div className="absolute bottom-0 z-20 right-0 bg-secondary text-xs font-bold uppercase tracking-tight px-2.5 py-1 rounded-tl-lg">
                  VIP
                </div>
              )}
            </>
          )}
        </div>

        {/* Card Content - Below Image */}
        <div className="py-2 px-1 relative">
          {/* Title */}
          <h3 className={cn(
            "font-bold text-card-foreground leading-tight mb-1 line-clamp-2",
            config.title
          )}>
            {video.title}
          </h3>

          <div className="flex justify-between items-center">
            <span className={cn(
              "text-muted-foreground truncate h-[20px]",
              config.subtitle
            )}>
              {video?.actor?.name}
            </span>
            {/* Rating */}
            <div className="flex items-center text-yellow-400">
              <Star className="w-3 h-3 fill-current" />
              <span className={cn("ml-1", config.rating)}>{video.play}</span>
            </div>
          </div>
        </div>
      </Link>
    </div>
  );
};
