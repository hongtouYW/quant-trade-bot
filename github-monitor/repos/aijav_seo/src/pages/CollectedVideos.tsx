import { useTranslation } from "react-i18next";
import { useState } from "react";
import { VideoListEmpty } from "@/components/empty-states/video-list-empty";
import { Button } from "@/components/ui/button";
import { ChevronLeft, Edit, Heart, Menu, Play, Trash2, X } from "lucide-react";
import { useNavigate } from "react-router";
import { useMyCollectedVideos } from "@/hooks/video/useMyCollectedVideos";
import { useToggleVideoCollection } from "@/hooks/video/useToggleVideoCollection";
import { Skeleton } from "@/components/ui/skeleton";
import { AnimatePresence, motion } from "framer-motion";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { BackdropContainer } from "@/components/ui/backdrop-container";
import { FixedSidePanel } from "@/components/ui/fixed-side-panel";
import { EmptyStateCard } from "@/components/ui/empty-state-card";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export function CollectedVideos() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isPending: isLoading } = useMyCollectedVideos();
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

    // Close dialog first
    setConfirmationDialog({ isOpen: false });

    // Add to removing state for animation
    setRemovingIds((prev) => new Set(prev).add(videoId));

    try {
      await toggleVideoCollection.mutateAsync({ vid: videoId });
      // On success, clean up removing state after animation duration
      setTimeout(() => {
        setRemovingIds((prev) => {
          const next = new Set(prev);
          next.delete(videoId);
          return next;
        });
      }, 300);
    } catch (error) {
      // On error, immediately remove from removing state to stop animation
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

  if (isLoading) {
    return (
      <div className="bg-background text-foreground transition-colors">
        {/* Fixed Header - Always on top */}
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-card transition-colors">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <Button
                variant="ghost"
                size="icon"
                onClick={() => navigate(-1)}
                aria-label="返回"
                className="size-8"
              >
                <ChevronLeft className="size-6" />
              </Button>
              <div className="flex flex-1 items-center gap-2 text-base font-bold">
                <Skeleton className="w-24 h-6" />
              </div>
            </div>
            <button
              onClick={handleActionClick}
              className={`flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                isActionMode
                  ? "text-primary bg-primary/10 hover:bg-primary/20"
                  : "text-muted-foreground hover:text-foreground hover:bg-muted/40 dark:hover:bg-muted/20"
              }`}
            >
              {isActionMode ? (
                <>
                  <X className="size-4" />
                  <span>{t("my_favorites.cancel")}</span>
                </>
              ) : (
                <>
                  <Edit className="size-4" />
                  <span>{t("my_favorites.edit")}</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* Content with top padding to account for fixed header */}
        <div>
          {/* Mobile Layout - Stacked */}
          <div className="lg:hidden">
            {/* Mobile Playlist Header Skeleton */}
            <div className="relative flex flex-col items-center p-4 overflow-hidden">
              {/* Overlay for opacity */}
              <div
                className="absolute inset-0 bg-overlay-soft"
                aria-hidden="true"
              />
              {/* Content */}
              <div className="relative flex flex-col items-center w-full h-full">
                <div className="w-full max-w-[200px] aspect-square bg-[#FCE9FF] rounded-[10px] border flex items-center justify-center">
                  <Heart className="size-24 text-[#F4A8FFCC] " />
                </div>
                <div className="mt-2.5 w-full">
                  <Skeleton className="w-32 h-6 mx-auto" />
                </div>

                <div className="mt-6 w-full">
                  <Skeleton className="w-full h-12 rounded-full" />
                </div>
              </div>
            </div>

            {/* Mobile Video List Skeleton */}
            <div className="py-4 px-4">
              <div className="flex flex-col gap-3">
                {[1, 2, 3, 4, 5].map((index) => (
                  <div key={index} className="flex gap-3 items-center">
                    <Skeleton className="w-4 h-4 min-w-[16px]" />
                    <div className="w-full flex gap-2 p-2">
                      <Skeleton className="min-w-[120px] max-w-[120px] h-[68px] rounded-[8px]" />
                      <div className="flex flex-col justify-between gap-1 min-w-0 flex-1">
                        <div className="space-y-1">
                          <div className="space-x-1">
                            <Skeleton className="w-8 h-4 rounded-full inline-block" />
                          </div>
                          <Skeleton className="w-full h-8" />
                        </div>
                        <div className="flex flex-col gap-1">
                          <Skeleton className="w-20 h-4" />
                          <div className="flex items-center gap-3">
                            <Skeleton className="w-12 h-3" />
                            <Skeleton className="w-16 h-3" />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Desktop Layout - Fixed Sidebar */}
          <div className="hidden lg:flex relative">
            {/* Fixed Playlist Header Area - Left Side Skeleton */}
            <FixedSidePanel>
              <BackdropContainer>
                <EmptyStateCard
                  icon={Heart}
                  iconSize="size-40"
                  iconFill={true}
                  title=""
                  actions={
                    <>
                      <div className="mt-2.5 w-full">
                        <Skeleton className="w-32 h-6 mx-auto" />
                      </div>
                      <div className="text-sm w-full mb-4">
                        <Skeleton className="w-20 h-4 mx-auto mt-2" />
                      </div>
                      <Skeleton className="w-full h-12 rounded-full mt-4" />
                    </>
                  }
                />
              </BackdropContainer>
            </FixedSidePanel>

            {/* Desktop Content Area Skeleton */}
            <div className="ml-[368px] w-[calc(100%-368px)]">
              <div className="py-4 px-4">
                <div className="flex flex-col gap-4">
                  {[1, 2, 3, 4, 5].map((index) => (
                    <div key={index} className="flex gap-4 items-center">
                      <Skeleton className="w-5 h-5 min-w-[20px]" />
                      <div className="w-full flex gap-3 p-2">
                        <Skeleton className="min-w-[168px] max-w-[168px] h-[94px] rounded-[8px]" />
                        <div className="flex flex-col justify-between gap-2 min-w-0 flex-1">
                          <div className="space-y-2">
                            <div className="space-x-2">
                              <Skeleton className="w-10 h-5 rounded-full inline-block" />
                            </div>
                            <Skeleton className="w-full h-10" />
                          </div>
                          <div className="flex items-center justify-between gap-2">
                            <Skeleton className="w-24 h-4" />
                            <div className="flex items-center gap-4">
                              <Skeleton className="w-12 h-3" />
                              <Skeleton className="w-16 h-3" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (collectedVideos.length === 0) {
    return (
      <div className="bg-background text-foreground transition-colors">
        {/* Fixed Header - Always on top */}
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-card transition-colors">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <Button
                variant="ghost"
                size="icon"
                onClick={() => navigate(-1)}
                aria-label="返回"
                className="size-8"
              >
                <ChevronLeft className="size-6" />
              </Button>
              <div className="flex flex-1 items-center gap-2 text-base font-bold">
                <span>{t("my_favorites.favorite_videos")}</span>
              </div>
            </div>
            <button
              onClick={handleActionClick}
              className={`flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                isActionMode
                  ? "text-brand-accent dark:text-brand-accent bg-[#EC67FF]/10 hover:bg-[#EC67FF]/20"
                  : "text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-white/10"
              }`}
            >
              {isActionMode ? (
                <>
                  <X className="size-4" />
                  <span>{t("my_favorites.cancel")}</span>
                </>
              ) : (
                <>
                  <Edit className="size-4" />
                  <span>{t("my_favorites.edit")}</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* Content with top padding to account for fixed header */}
        <div>
          {/* Mobile Layout - Stacked */}
          <div className="lg:hidden">
            {/* Mobile Playlist Header */}
            <div className="relative flex flex-col items-center p-4 overflow-hidden">
              {/* Overlay for opacity */}
              <div
                className="absolute inset-0 bg-overlay-soft"
                aria-hidden="true"
              />
              {/* Content */}
              <div className="relative flex flex-col items-center w-full h-full">
                <div className="flex gap-3 w-full">
                  <div className="size-[150px] aspect-square bg-[#FCE9FF] rounded-[10px] flex items-center justify-center">
                    <Heart className="size-20 text-[#F4A8FFCC]" />
                  </div>
                  <div className="mt-2.5 text-xl font-semibold tracking-tight w-full">
                    {t("my_favorites.favorite_videos")}
                  </div>
                </div>

                <Button
                  className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
                  style={{
                    borderColor: "#EC67FF",
                    background: "#F4A8FFCC",
                    color: "#BA12D3",
                  }}
                  disabled
                >
                  <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                  {t("common.play_now")}
                </Button>
              </div>
            </div>

            {/* Mobile Empty State */}
            <div className="py-4 px-4">
              <div className="flex items-center justify-center min-h-[200px]">
                <VideoListEmpty
                  title={t("empty.no_collected_videos")}
                  description={t("empty.no_collected_videos_desc")}
                />
              </div>
            </div>

            <div className="py-4 px-4">
              <RecommendedHorizontalList />
            </div>
          </div>

          {/* Desktop Layout - Fixed Sidebar */}
          <div className="hidden lg:flex relative">
            {/* Fixed Playlist Header Area - Left Side */}
            <FixedSidePanel>
              <BackdropContainer>
                <EmptyStateCard
                  icon={Heart}
                  iconSize="size-40"
                  iconFill={true}
                  title={t("my_favorites.favorite_videos")}
                  subtitle={`0 ${t("common.videos")}`}
                  actions={
                    <Button
                      className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 font-semibold shadow-none"
                      style={{
                        borderColor: "#EC67FF",
                        background: "#F4A8FFCC",
                        color: "#BA12D3",
                      }}
                      disabled
                    >
                      <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                      {t("common.play_now")}
                    </Button>
                  }
                />
              </BackdropContainer>
            </FixedSidePanel>

            {/* Desktop Content Area */}
            <div className="ml-[368px] w-[calc(100%-368px)]">
              <div className="py-4 px-4">
                <div className="flex items-center justify-center min-h-[400px]">
                  <VideoListEmpty
                    title={t("empty.no_collected_videos")}
                    description={t("empty.no_collected_videos_desc")}
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <>
      <div className="bg-background text-foreground transition-colors">
        {/* Fixed Header - Always on top */}
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-card transition-colors">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <Button
                variant="ghost"
                size="icon"
                onClick={() => navigate(-1)}
                aria-label="返回"
                className="size-8"
              >
                <ChevronLeft className="size-6" />
              </Button>
              <div className="flex flex-1 items-center gap-2 text-base font-bold">
                <span>{t("my_favorites.favorite_videos")}</span>
              </div>
            </div>
            <button
              onClick={handleActionClick}
              className={`flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                isActionMode
                  ? "text-brand-accent dark:text-brand-accent bg-[#EC67FF]/10 hover:bg-[#EC67FF]/20"
                  : "text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-white/10"
              }`}
            >
              {isActionMode ? (
                <>
                  <X className="size-4" />
                  <span>{t("my_favorites.cancel")}</span>
                </>
              ) : (
                <>
                  <Edit className="size-4" />
                  <span>{t("my_favorites.edit")}</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* Content with top padding to account for fixed header */}
        <div>
          {/* Mobile Layout - Stacked */}
          <div className="lg:hidden">
            {/* Mobile Playlist Header */}
            <div className="relative flex flex-col items-center p-4 overflow-hidden">
              {/* Overlay for opacity */}
              <div
                className="absolute inset-0 bg-overlay-soft"
                aria-hidden="true"
              />
              {/* Content */}
              <div className="relative flex flex-col items-center w-full h-full">
                <div className="flex gap-3 w-full">
                  <div className="size-[150px] aspect-square bg-[#FCE9FF] rounded-[10px] flex items-center justify-center">
                    <Heart className="size-24 fill-[#F4A8FF] stroke-[#F4A8FF]" />
                  </div>
                  <div>
                    <div className="mt-2.5 text-xl font-semibold tracking-tight w-full">
                      {t("my_favorites.favorite_videos")}
                    </div>
                    <div className="text-sm w-full font-light text-gray-400 dark:text-gray-300 mb-4">
                      {collectedVideos.length || "0"} {t("common.videos")}
                    </div>
                  </div>
                </div>

                <Button
                  className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
                  style={{
                    borderColor: "#EC67FF",
                    background: "#F4A8FFCC",
                    color: "#BA12D3",
                  }}
                  onClick={() => {
                    // Play first video in collection
                    if (collectedVideos.length > 0) {
                      navigate(`/watch/${collectedVideos[0].id}`, {
                        state: {
                          from: "collected-videos",
                          categoryName: t("my_favorites.favorite_videos"),
                        },
                      });
                    }
                  }}
                >
                  <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                  {t("common.play_now")}
                </Button>
              </div>
            </div>

            {/* Mobile Video List */}
            <div className="py-4 px-4">
              <div className="flex flex-col gap-3">
                <AnimatePresence mode="popLayout">
                  {collectedVideos.map((video, index) => (
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
                      <div className="flex gap-3 items-center">
                        {isActionMode ? (
                          <div className="flex items-center justify-center min-w-[24px]">
                            <div className="w-3 h-3 rounded-full bg-gray-400"></div>
                          </div>
                        ) : (
                          <span className="text-sm font-semibold text-gray-600 min-w-[16px]">
                            {index + 1}
                          </span>
                        )}
                        <div className="relative w-full">
                          <EnhancedVideoCard
                            video={video}
                            layout="horizontal-compact"
                            linkPrefix={isActionMode ? "#" : "/watch"}
                            linkState={
                              !isActionMode
                                ? {
                                    from: "collected-videos",
                                    categoryName: t(
                                      "my_favorites.favorite_videos",
                                    ),
                                  }
                                : undefined
                            }
                            showBadges={true}
                            showRating={false}
                            showActor={true}
                          />
                          {/* Right side trash button when in action mode */}
                          {isActionMode && (
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                if (!removingIds.has(video.id)) {
                                  handleRemoveVideo(video.id);
                                }
                              }}
                              className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center justify-center w-6 h-6 rounded-full bg-red-500 hover:bg-red-600 transition-colors min-w-[24px] z-10"
                              disabled={removingIds.has(video.id)}
                            >
                              <Trash2 className="w-4 h-4 text-white" />
                            </button>
                          )}
                        </div>
                      </div>
                    </motion.div>
                  ))}
                </AnimatePresence>
              </div>
            </div>
          </div>

          {/* Desktop Layout - Fixed Sidebar */}
          <div className="hidden lg:flex relative">
            {/* Fixed Playlist Header Area - Left Side */}
            <FixedSidePanel>
              <BackdropContainer>
                <EmptyStateCard
                  icon={Heart}
                  iconSize="size-40"
                  iconFill={true}
                  title={t("my_favorites.favorite_videos")}
                  subtitle={`${collectedVideos.length} ${t("common.videos")}`}
                  actions={
                    <Button
                      className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 font-semibold shadow-none"
                      style={{
                        borderColor: "#EC67FF",
                        background: "#F4A8FFCC",
                        color: "#BA12D3",
                      }}
                      onClick={() => {
                        // Play first video in collection
                        if (collectedVideos.length > 0) {
                          navigate(`/watch/${collectedVideos[0].id}`, {
                            state: {
                              from: "collected-videos",
                              categoryName: t("my_favorites.favorite_videos"),
                            },
                          });
                        }
                      }}
                    >
                      <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                      {t("common.play_now")}
                    </Button>
                  }
                />
              </BackdropContainer>
            </FixedSidePanel>

            {/* Desktop Content Area */}
            <div className="ml-[368px] w-[calc(100%-368px)]">
              <div className="py-4 px-4">
                <div className="flex flex-col gap-4">
                  <AnimatePresence mode="popLayout">
                    {collectedVideos.map((video, index) => (
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
                        <div className="flex gap-4 items-center">
                          {isActionMode ? (
                            <div className="flex items-center justify-center min-w-[32px]">
                              <Menu className="w-4 h-4" />
                            </div>
                          ) : (
                            <span className="text-base text-center font-semibold text-gray-600 min-w-[20px]">
                              {index + 1}
                            </span>
                          )}
                          <div className="relative w-full">
                            <EnhancedVideoCard
                              video={video}
                              layout="horizontal-compact"
                              linkPrefix={isActionMode ? "#" : "/watch"}
                              linkState={
                                !isActionMode
                                  ? {
                                      from: "collected-videos",
                                      categoryName: t(
                                        "my_favorites.favorite_videos",
                                      ),
                                    }
                                  : undefined
                              }
                              showBadges={true}
                              showRating={false}
                              showActor={true}
                            />
                            {/* Right side trash button when in action mode */}
                            {isActionMode && (
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  if (!removingIds.has(video.id)) {
                                    handleRemoveVideo(video.id);
                                  }
                                }}
                                className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center justify-center w-8 h-8 rounded-full hover:bg-red-200 transition-colors min-w-[32px] z-10"
                                disabled={removingIds.has(video.id)}
                              >
                                <Trash2 className="w-5 h-5 text-destructive" />
                              </button>
                            )}
                          </div>
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
                  <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                    <Trash2 className="w-8 h-8 text-red-500" />
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
                  className="flex-1 sm:flex-none bg-[#EC67FF] hover:bg-[#EC67FF]/90"
                  disabled={toggleVideoCollection.isPending}
                >
                  {toggleVideoCollection.isPending
                    ? t("common.removing")
                    : t("my_favorites.confirm_removal")}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>
    </>
  );
}
