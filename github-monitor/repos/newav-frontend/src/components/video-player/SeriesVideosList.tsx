import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { Bookmark, Play, Share2 } from "lucide-react";
import { useGroupDetail } from "@/hooks/group/useGroupDetail";
import { useToggleGroupCollection } from "@/hooks/group/useToggleGroupCollection";
import { Button } from "@/components/ui/button";
import { ShareDialog } from "@/components/ShareDialog";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card.tsx";
import { cn } from "@/lib/utils";

interface SeriesVideosListProps {
  currentVideoId?: string;
  seriesTitle?: string;
  seriesId?: number;
}

export function SeriesVideosList({
  currentVideoId,
  seriesTitle,
  seriesId,
}: SeriesVideosListProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { executeWithAuth } = useAuthAction();
  const { data } = useGroupDetail(seriesId);
  const { mutate: toggleCollection, isPending: isCollecting } =
    useToggleGroupCollection();

  const seriesVideos = data?.videos || [];

  const handleCollect = () => {
    executeWithAuth(() => {
      if (seriesId) {
        toggleCollection({ gid: seriesId });
      }
    });
  };

  const handleVideoClick = (videoId: number) => {
    navigate(`/watch/${videoId}`, {
      state: {
        from: "series-detail",
        categoryName: seriesTitle || data?.title,
        seriesId: seriesId,
      },
    });
  };

  if (seriesVideos.length === 0) {
    return (
      <div className="rounded-lg overflow-hidden bg-card text-card-foreground border border-border transition-colors p-4">
        <div className="flex items-center gap-3 pb-4 border-b border-border/60">
          <div className="size-12 bg-brand-light-purple/30 rounded-lg flex items-center justify-center">
            <Play className="size-6 text-brand-accent" />
          </div>
          <h3 className="font-semibold text-base">
            {seriesTitle || data?.title || t("series.series_list")}
          </h3>
        </div>

        <div className="mt-4">
          <div className="mb-4 flex items-center justify-between text-sm">
            <span className="font-medium text-muted-foreground">清单列表</span>
            <span className="font-medium text-muted-foreground">0部</span>
          </div>
          <div className="bg-muted rounded-lg p-8 text-center text-muted-foreground transition-colors">
            <Play className="size-12 mx-auto mb-3 text-muted-foreground/70" />
            <p className="text-sm">{t("tabs.no_videos_found")}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg overflow-hidden bg-card text-card-foreground border border-border transition-colors p-4">
      <div className="flex flex-col gap-4">
        <div className="flex items-start gap-3">
          <div className="size-20 bg-brand-light-purple/30 rounded-lg overflow-hidden flex justify-center">
            {data?.image ? (
              <img src={data.image} className="w-full h-full object-cover" />
            ) : (
              <Play className="size-8 text-brand-accent my-auto" />
            )}
          </div>
          <div className="flex-1 min-w-0">
            <h3 className="font-semibold text-base">
              {seriesTitle || data?.title || t("series.series_list")}
            </h3>
            {data?.description && (
              <p className="text-sm text-muted-foreground mt-2 line-clamp-3">
                {data.description}
              </p>
            )}
          </div>
        </div>

        <div className="flex gap-2 w-full">
          <Button
            className="flex-1 h-10 rounded-lg"
            variant={data?.is_collect === 1 ? "default" : "outline"}
            onClick={handleCollect}
            disabled={isCollecting}
          >
            <Bookmark
              className={cn(
                "w-4 h-4 mr-2",
                data?.is_collect === 1
                  ? "fill-primary-foreground text-primary-foreground"
                  : "text-muted-foreground",
              )}
            />
            {data?.is_collect === 1
              ? t("video_player.collected")
              : t("video_player.collect")}
          </Button>
          <ShareDialog>
            <Button className="flex-1 h-10 rounded-lg" variant="outline">
              <Share2 className="w-4 h-4 mr-2 text-muted-foreground" />
              {t("video_player.share")}
            </Button>
          </ShareDialog>
        </div>
      </div>

      <div className="mt-4">
        <div className="pb-2 mb-4 flex items-center justify-between border-b border-border/60 text-sm">
          <span className="font-semibold text-muted-foreground">清单列表</span>
          <span className="font-medium text-muted-foreground">
            {seriesVideos.length}部
          </span>
        </div>

        <div className="bg-card rounded-lg overflow-hidden">
          <div className="max-h-[500px] space-y-3 flex flex-col overflow-y-auto [&::-webkit-scrollbar]:hidden">
            {seriesVideos.map((video) => (
              <div key={video.id} className="transition-colors">
                <div
                  className={cn(
                    "flex-1",
                    currentVideoId === video.id.toString()
                      ? "bg-primary/10"
                      : "hover:bg-muted/40 dark:hover:bg-muted/10",
                  )}
                  onClick={() => handleVideoClick(video.id)}
                >
                  <EnhancedVideoCard
                    video={video}
                    layout="horizontal-compact"
                    linkState={{
                      from: "series-detail",
                      categoryName: seriesTitle ?? data?.title ?? "",
                    }}
                    linkPrefix="/watch"
                    showBadges={true}
                    showRating={false}
                    showActor={true}
                    isActive={currentVideoId === video.id.toString()}
                    className="!border-b !border-border/40 last:!border-b-0 cursor-pointer"
                  />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
