import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { AnimatePresence, motion } from "framer-motion";
import { Heart, Edit, X, Trash2, Menu } from "lucide-react";
import { useMyCollectedVideos } from "@/hooks/video/useMyCollectedVideos";
import { useToggleVideoCollection } from "@/hooks/video/useToggleVideoCollection";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { cn } from "@/lib/utils";

interface CollectedVideosListProps {
  currentVideoId?: string;
}

export function CollectedVideosList({
  currentVideoId,
}: CollectedVideosListProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data } = useMyCollectedVideos();
  const toggleVideoCollection = useToggleVideoCollection();

  const [isActionMode, setIsActionMode] = useState(false);
  const [removingIds, setRemovingIds] = useState<Set<number>>(new Set());
  const [confirmationDialog, setConfirmationDialog] = useState<{
    isOpen: boolean;
    videoId?: number;
    videoTitle?: string;
  }>({
    isOpen: false,
  });

  const collectedVideos = data?.data?.data || [];

  const handleActionClick = () => {
    setIsActionMode(!isActionMode);
  };

  const handleVideoClick = (videoId: number) => {
    if (!isActionMode) {
      navigate(`/watch/${videoId}`, {
        state: {
          from: "collected-videos",
          categoryName: t("my_favorites.favorite_videos"),
        },
      });
    }
  };

  const handleRemoveVideo = (videoId: number) => {
    const video = collectedVideos?.find((v) => v.id === videoId);
    setConfirmationDialog({
      isOpen: true,
      videoId,
      videoTitle: video?.title,
    });
  };

  const handleConfirmRemoval = async () => {
    const { videoId } = confirmationDialog;
    if (!videoId) return;

    setConfirmationDialog({ isOpen: false });
    setRemovingIds((prev) => new Set(prev).add(videoId));

    try {
      await toggleVideoCollection.mutateAsync({ vid: videoId });
      setTimeout(() => {
        setRemovingIds((prev) => {
          const next = new Set(prev);
          next.delete(videoId);
          return next;
        });
      }, 300);
    } catch (error) {
      setRemovingIds((prev) => {
        const next = new Set(prev);
        next.delete(videoId);
        return next;
      });
      console.error("Failed to remove video:", error);
    }
  };

  const handleCancelRemoval = () => {
    setConfirmationDialog({ isOpen: false });
  };

  if (collectedVideos.length === 0) {
    return (
      <div className="rounded-lg overflow-hidden bg-card text-card-foreground border border-border transition-colors p-4">
        <div className="flex items-center gap-3 pb-4 border-b border-border/60">
          <div className="size-12 bg-brand-light-purple/30 rounded-lg flex items-center justify-center">
            <Heart className="size-6 text-brand-accent" />
          </div>
          <h3 className="font-semibold text-base">
            {t("my_favorites.favorite_videos")}
          </h3>
        </div>

        <div className="mt-4">
          <div className="mb-4 flex items-center justify-between text-sm">
            <span className="font-medium text-muted-foreground">清单列表</span>
            <span className="font-medium text-muted-foreground">0部</span>
          </div>
          <div className="bg-muted rounded-lg p-8 text-center text-muted-foreground transition-colors">
            <Heart className="size-12 mx-auto mb-3 text-muted-foreground/70" />
            <p className="text-sm">{t("empty.no_collected_videos")}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <>
      <div className="rounded-lg overflow-hidden bg-card text-card-foreground border border-border transition-colors p-4">
        <div className="flex flex-col gap-4">
          <div className="flex items-start gap-3">
            <div className="size-20 bg-brand-light-purple/30 rounded-lg flex items-center justify-center">
              <Heart className="size-8 text-brand-accent" />
            </div>
            <h3 className="font-semibold text-base">
              {t("my_favorites.favorite_videos")}
            </h3>
          </div>
          <Button
            variant="outline"
            onClick={handleActionClick}
            className="self-start"
          >
            {isActionMode ? (
              <>
                <X className="w-4 h-4" />
                <span>{t("my_favorites.cancel")}</span>
              </>
            ) : (
              <>
                <Edit className="w-4 h-4" />
                <span>{t("my_favorites.edit")}</span>
              </>
            )}
          </Button>
        </div>

        <div className="mt-4">
          <div className="pb-2 mb-4 flex items-center justify-between border-b border-border/60 text-sm">
            <span className="font-semibold text-muted-foreground">
              清单列表
            </span>
            <span className="font-medium text-muted-foreground">
              {collectedVideos.length}部
            </span>
          </div>

          <div className="bg-card rounded-lg overflow-hidden">
            <div className="max-h-[500px] space-y-3 flex flex-col overflow-y-auto [&::-webkit-scrollbar]:hidden">
              <AnimatePresence mode="popLayout">
                {collectedVideos.map((video) => (
                  <motion.div
                    key={video.id}
                    layout
                    initial={{ opacity: 1, scale: 1 }}
                    exit={{
                      scale: 0,
                      opacity: 0,
                      transition: { duration: 0.3, ease: "easeInOut" },
                    }}
                  >
                    <div className="flex items-center transition-colors">
                      {isActionMode && (
                        <div className="flex items-center justify-center min-w-[20px] px-3">
                          <Menu className="w-3 h-3 text-muted-foreground" />
                        </div>
                      )}

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
                            from: "collected-videos",
                            categoryName: t("my_favorites.favorite_videos"),
                          }}
                          linkPrefix="/watch"
                          showBadges={true}
                          showRating={false}
                          showActor={true}
                          isActive={currentVideoId === video.id.toString()}
                          className="!border-b !border-border/40 last:!border-b-0 cursor-pointer"
                        />
                      </div>

                      {isActionMode && (
                        <div className="px-3">
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              if (!removingIds.has(video.id)) {
                                handleRemoveVideo(video.id);
                              }
                            }}
                            className="flex items-center justify-center size-6 rounded-full bg-destructive text-destructive-foreground hover:bg-destructive/90 transition-colors"
                            disabled={removingIds.has(video.id)}
                          >
                            <Trash2 className="w-3 h-3" />
                          </button>
                        </div>
                      )}
                    </div>
                  </motion.div>
                ))}
              </AnimatePresence>
            </div>
          </div>
        </div>
      </div>

      {/* Confirmation Dialog */}
      <Dialog
        open={confirmationDialog.isOpen}
        onOpenChange={(open) => !open && handleCancelRemoval()}
      >
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader className="text-center">
            <DialogTitle className="flex flex-col items-center gap-4">
              <div className="w-16 h-16 bg-destructive/10 rounded-full flex items-center justify-center">
                <Trash2 className="w-8 h-8 text-destructive" />
              </div>
              {t("my_favorites.remove_confirmation_title")}
            </DialogTitle>
            <DialogDescription className="text-center text-base mt-2">
              {t("my_favorites.remove_confirmation_message")}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="flex flex-col gap-3 sm:flex-row sm:justify-center">
            <Button
              variant="outline"
              onClick={handleCancelRemoval}
              className="flex-1 sm:flex-none"
            >
              {t("my_favorites.cancel_removal")}
            </Button>
            <Button
              onClick={handleConfirmRemoval}
              className="flex-1 sm:flex-none bg-primary text-primary-foreground hover:bg-primary/90"
              disabled={toggleVideoCollection.isPending}
            >
              {toggleVideoCollection.isPending
                ? t("common.removing")
                : t("my_favorites.confirm_removal")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
