import { useTranslation } from "react-i18next";
import { History } from "lucide-react";
import { usePlayLog } from "@/hooks/video/usePlayLog";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { cn } from "@/lib/utils";

interface WatchHistoryListProps {
  currentVideoId?: string;
}

export function WatchHistoryList({ currentVideoId }: WatchHistoryListProps) {
  const { t } = useTranslation();
  const { data } = usePlayLog();

  const watchHistory = data?.data?.data || [];

  if (watchHistory.length === 0) {
    return (
      <div className="rounded-lg overflow-hidden bg-card text-card-foreground border border-border transition-colors p-4">
        <div className="flex items-center gap-3 pb-4 border-b border-border/60">
          <div className="size-12 bg-brand-light-purple/30 rounded-lg flex items-center justify-center">
            <History className="size-6 text-brand-accent" />
          </div>
          <h3 className="font-semibold text-base">
            {t("navbar.watch_history")}
          </h3>
        </div>

        <div className="mt-4">
          <div className="mb-4 flex items-center justify-between text-sm">
            <span className="font-medium text-muted-foreground">清单列表</span>
            <span className="font-medium text-muted-foreground">0部</span>
          </div>
          <div className="bg-muted rounded-lg p-8 text-center text-muted-foreground transition-colors">
            <History className="size-12 mx-auto mb-3 text-muted-foreground/70" />
            <p className="text-sm">{t("empty.no_watch_history")}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg overflow-hidden bg-card text-card-foreground border border-border transition-colors p-4">
      <div className="flex items-start gap-3">
        <div className="size-20 bg-brand-light-purple/30 rounded-lg flex items-center justify-center">
          <History className="size-8 text-brand-accent" />
        </div>
        <h3 className="font-semibold text-base">{t("navbar.watch_history")}</h3>
      </div>

      <div className="mt-4">
        <div className="pb-2 mb-4 flex items-center justify-between border-b border-border/60 text-sm">
          <span className="font-semibold text-muted-foreground">清单列表</span>
          <span className="font-medium text-muted-foreground">
            {watchHistory.length}部
          </span>
        </div>

        <div className="bg-card rounded-lg overflow-hidden">
          <div className="max-h-[500px] space-y-3 flex flex-col overflow-y-auto [&::-webkit-scrollbar]:hidden">
            {watchHistory.map((video) => (
              <div key={video.id} className="transition-colors">
                <div
                  className={cn(
                    "flex-1",
                    currentVideoId === video.id.toString()
                      ? "bg-primary/10"
                      : "hover:bg-muted/40 dark:hover:bg-muted/10",
                  )}
                >
                  <EnhancedVideoCard
                    video={video}
                    layout="horizontal-compact"
                    linkState={{
                      from: "watch-history",
                      categoryName: t("navbar.watch_history"),
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
