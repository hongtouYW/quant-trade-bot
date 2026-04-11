import { useTranslation } from "react-i18next";
import { BookMarked, Edit, X, Trash2 } from "lucide-react";
import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import CollectionCardItem from "@/components/collection-card-item.tsx";
import { useMyCollectedGroups } from "@/hooks/group/useMyCollectedGroups";
import { useMyCollectedVideos } from "@/hooks/video/useMyCollectedVideos";
import { useToggleGroupCollection } from "@/hooks/group/useToggleGroupCollection";
import { useNavigate } from "react-router";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export default function MyFavourites() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: collectedGroups, isPending } = useMyCollectedGroups();
  const { data: collectedVideos } = useMyCollectedVideos();
  const toggleCollection = useToggleGroupCollection();
  const [isEditMode, setIsEditMode] = useState(false);
  const [removingIds, setRemovingIds] = useState<Set<number>>(new Set());
  const [confirmationDialog, setConfirmationDialog] = useState<{
    isOpen: boolean;
    groupId?: number;
    groupTitle?: string;
  }>({
    isOpen: false,
  });

  const handleEditClick = () => {
    setIsEditMode(!isEditMode);
  };

  const handleGroupClick = (groupId: number) => {
    if (!isEditMode) {
      navigate(`/series/${groupId}`);
    }
  };

  const handleCollectedVideosClick = () => {
    if (!isEditMode) {
      navigate("/collected-videos");
    }
  };

  const handleRemoveFavorite = (groupId: number) => {
    const group = collectedGroups?.find((g) => g.id === groupId);
    setConfirmationDialog({
      isOpen: true,
      groupId,
      groupTitle: group?.title,
    });
  };

  const handleConfirmRemoval = async () => {
    const { groupId } = confirmationDialog;
    if (!groupId) return;

    // Close dialog first
    setConfirmationDialog({ isOpen: false });

    // Add to removing state for animation
    setRemovingIds((prev) => new Set(prev).add(groupId));

    try {
      await toggleCollection.mutateAsync({ gid: groupId });
      // On success, clean up removing state after animation duration
      setTimeout(() => {
        setRemovingIds((prev) => {
          const next = new Set(prev);
          next.delete(groupId);
          return next;
        });
      }, 300);
    } catch (error) {
      // On error, immediately remove from removing state to stop animation
      setRemovingIds((prev) => {
        const next = new Set(prev);
        next.delete(groupId);
        return next;
      });
      console.error("Failed to remove favorite:", error);
    }
  };

  const handleCancelRemoval = () => {
    setConfirmationDialog({ isOpen: false });
  };

  return (
    <>
      <div>
        <header className="border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <BookMarked className="size-6" />
              <span>{t("my_favorites.page_title")}</span>
            </div>
            {collectedGroups && collectedGroups.length > 0 && (
              <button
                onClick={handleEditClick}
                className={`flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                  isEditMode
                    ? "text-brand-accent dark:text-brand-accent bg-[#EC67FF]/10 hover:bg-[#EC67FF]/20"
                    : "text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-white/10"
                }`}
              >
                {isEditMode ? (
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
            )}
          </div>
        </header>

        <div className="mt-6 px-4">
          <div className="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:gap-4 sm:justify-start">
            {/* Collected video section */}
            <CollectionCardItem
              containerClassName="max-w-none w-auto"
              className="size-32 sm:size-45"
              title={t("my_favorites.favorite_videos")}
              count={collectedVideos?.data?.total || 0}
              onClick={handleCollectedVideosClick}
            />

            {isPending ? (
              // Single skeleton card matching CollectionCardItem layout
              <div className="flex flex-col items-center space-y-2 sm:space-y-3 w-full sm:w-fit">
                {/* Skeleton Card */}
                <Skeleton className="size-32 sm:size-45 rounded-2xl" />
                {/* Skeleton Text content */}
                <div className="text-center flex justify-between items-center w-full">
                  <Skeleton className="h-4 w-20" />
                  <Skeleton className="h-4 w-8" />
                </div>
              </div>
            ) : collectedGroups && collectedGroups.length > 0 ? (
              <AnimatePresence mode="popLayout">
                {collectedGroups?.map((group) => (
                  <motion.div
                    key={group.id}
                    layout
                    initial={{ opacity: 1, scale: 1 }}
                    exit={{
                      scale: 0,
                      opacity: 0,
                      transition: { duration: 0.3, ease: "easeInOut" },
                    }}
                  >
                    <CollectionCardItem
                      containerClassName="max-w-none w-auto"
                      className="size-32 sm:size-45"
                      title={group.title}
                      count={group.total_video}
                      backgroundImage={group.image}
                      imageAlt={group.title}
                      isEditMode={isEditMode}
                      onRemove={() => handleRemoveFavorite(group.id)}
                      isRemoving={removingIds.has(group.id)}
                      onClick={() => handleGroupClick(group.id)}
                    />
                  </motion.div>
                ))}
              </AnimatePresence>
            ) : null}
          </div>
        </div>
      </div>

      <div className="py-4 px-4">
        <RecommendedHorizontalList />
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
              disabled={toggleCollection.isPending}
            >
              {toggleCollection.isPending
                ? t("common.removing")
                : t("my_favorites.confirm_removal")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
