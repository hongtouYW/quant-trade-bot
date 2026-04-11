import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { MessageCircle } from "lucide-react";
import { useReviewList } from "@/hooks/review/useReviewList.ts";
import { useLikeReview } from "@/hooks/review/useLikeReview.ts";
import { useEditReview } from "@/hooks/review/useEditReview.ts";
import { useDeleteReview } from "@/hooks/review/useDeleteReview.ts";
import { useUser } from "@/contexts/UserContext";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { EmptyState } from "@/components/ui/empty-state";
import { CommentItem } from "./CommentItem";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog.tsx";
import { Button } from "@/components/ui/button.tsx";

type CommentListProps = {
  videoId: string | undefined;
};

// Form schema for editing comments
const EditFormSchema = z.object({
  review: z.string().max(225, "最多 225 个字符").min(1),
  rating: z.number().min(1, "请选择评分"),
});

type EditFormData = z.infer<typeof EditFormSchema>;

export const CommentList = ({ videoId }: CommentListProps) => {
  const { t } = useTranslation();
  const { data: reviews } = useReviewList(videoId!);
  const { user } = useUser();
  const { executeWithAuth } = useAuthAction();
  const [showReplyForms, setShowReplyForms] = useState(true);
  const { mutate: likeReview, isPending: isLiking } = useLikeReview(videoId!);
  const { mutate: editReview, isPending: isEditing } = useEditReview(videoId!);
  const { mutate: deleteReview, isPending: isDeleting } = useDeleteReview(
    videoId!,
  );
  const [editingReviewId, setEditingReviewId] = useState<number | null>(null);
  const [deleteConfirmOpen, setDeleteConfirmOpen] = useState(false);
  const [reviewToDelete, setReviewToDelete] = useState<number | null>(null);
  const [replyingToId, setReplyingToId] = useState<number | null>(null);
  const [collapsedReplies, setCollapsedReplies] = useState<Set<number>>(
    new Set(),
  );

  // Initialize all replies as collapsed when data loads
  useEffect(() => {
    if (reviews?.length) {
      const allReplyIds = new Set<number>();
      reviews.forEach((review) => {
        if (review?.replies && review.replies.length > 0) {
          allReplyIds.add(review.id);
        }
      });
      setCollapsedReplies(allReplyIds);
    }
  }, [reviews]);

  const editForm = useForm<EditFormData>({
    resolver: zodResolver(EditFormSchema),
    defaultValues: {
      review: "",
      rating: 0,
    },
  });

  const handleEditComment = (
    reviewId: number,
    currentReview: string,
    currentRating: number,
  ) => {
    executeWithAuth(() => {
      setEditingReviewId(reviewId);
      editForm.reset({
        review: currentReview,
        rating: currentRating,
      });
    });
  };

  const handleDeleteComment = (reviewId: number) => {
    executeWithAuth(() => {
      setReviewToDelete(reviewId);
      setDeleteConfirmOpen(true);
    });
  };

  const handleConfirmDelete = () => {
    if (reviewToDelete) {
      deleteReview(
        { review_id: reviewToDelete },
        {
          onSuccess: () => {
            setDeleteConfirmOpen(false);
            setReviewToDelete(null);
          },
        },
      );
    }
  };

  const handleCancelDelete = () => {
    setDeleteConfirmOpen(false);
    setReviewToDelete(null);
  };

  const handleLikeComment = (reviewId: number) => {
    executeWithAuth(() => {
      likeReview({ review_id: reviewId });
    });
  };

  const handleSaveEdit = (reviewId: number) => {
    const formData = editForm.getValues();
    editReview(
      {
        review_id: reviewId,
        rating: formData.rating,
        review: formData.review,
      },
      {
        onSuccess: () => {
          setEditingReviewId(null);
          editForm.reset();
        },
      },
    );
  };

  const handleCancelEdit = () => {
    setEditingReviewId(null);
    editForm.reset();
  };

  const handleReplyClick = (reviewId: number) => {
    executeWithAuth(() => {
      if (replyingToId === reviewId) {
        // If already replying to this comment, toggle form visibility
        setShowReplyForms(!showReplyForms);
      } else {
        // Start replying to this comment and show form
        setReplyingToId(reviewId);
        setShowReplyForms(true);
      }
    });
  };

  const handleCancelReply = () => {
    setReplyingToId(null);
  };

  const toggleRepliesCollapsed = (reviewId: number) => {
    const newCollapsed = new Set(collapsedReplies);
    if (newCollapsed.has(reviewId)) {
      newCollapsed.delete(reviewId);
    } else {
      newCollapsed.add(reviewId);
    }
    setCollapsedReplies(newCollapsed);
  };

  // Check if current user can edit/delete a comment
  const canEditComment = (reviewUserId: number) => {
    return !!(user && user.id === reviewUserId);
  };

  if (!reviews?.length) {
    return (
      <EmptyState
        icon={MessageCircle}
        title={t("comments.no_comments")}
        description={t("comments.no_comments_description")}
        size="sm"
        className="py-8"
      />
    );
  }

  return (
    <div className="mt-4 flex flex-col gap-2 w-full">
      {reviews?.map((review) => (
        <CommentItem
          key={review?.id}
          review={review}
          depth={0}
          videoId={videoId}
          isEditing={editingReviewId === review?.id}
          isReplying={replyingToId === review?.id}
          isCollapsed={collapsedReplies.has(review?.id)}
          canEdit={canEditComment(review?.user?.id)}
          showReplyForms={showReplyForms}
          onEdit={() =>
            handleEditComment(review?.id, review?.review, review?.rating)
          }
          onDelete={() => handleDeleteComment(review?.id)}
          onReply={() => handleReplyClick(review?.id)}
          onCancelReply={handleCancelReply}
          onToggleCollapse={() => toggleRepliesCollapsed(review?.id)}
          onLike={() => handleLikeComment(review?.id)}
          onSaveEdit={() => handleSaveEdit(review?.id)}
          onCancelEdit={handleCancelEdit}
          editForm={editForm}
          isLiking={isLiking}
          isEditingAny={isEditing}
          isDeletingAny={isDeleting}
          t={t}
        />
      ))}

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteConfirmOpen} onOpenChange={setDeleteConfirmOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("video_player.delete_comment")}</DialogTitle>
            <DialogDescription>
              确定要删除这条评论吗？此操作无法撤销。
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={handleCancelDelete}
              disabled={isDeleting}
            >
              取消
            </Button>
            <Button
              variant="destructive"
              onClick={handleConfirmDelete}
              disabled={isDeleting}
            >
              {isDeleting ? "删除中..." : "确认删除"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};
