import { useState } from "react";
import type { UseFormReturn } from "react-hook-form";
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar.tsx";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu.tsx";
import { Button } from "@/components/ui/button.tsx";
import { Rating, RatingButton } from "@/components/ui/rating";
import { Textarea } from "@/components/ui/textarea";
import {
  ChevronDown,
  ChevronUp,
  Edit,
  EllipsisVertical,
  MessagesSquare,
  ThumbsUp,
  Trash2,
  Check,
  X,
} from "lucide-react";
import { format } from "date-fns";
import CommentArea from "@/components/comment-area.tsx";
import avatarPlaceholder from "../../assets/user-avatar.png";
import type { Review } from "@/types/review.types";

interface EditFormData {
  review: string;
  rating: number;
}

export interface CommentItemProps {
  review: Review;
  depth: number;
  videoId: string | undefined;
  isEditing: boolean;
  isReplying: boolean;
  isCollapsed: boolean;
  canEdit: boolean;
  showReplyForms: boolean;
  onEdit: () => void;
  onDelete: () => void;
  onReply: () => void;
  onCancelReply: () => void;
  onToggleCollapse: () => void;
  onLike: () => void;
  onSaveEdit: () => void;
  onCancelEdit: () => void;
  editForm: UseFormReturn<EditFormData>;
  isLiking: boolean;
  isEditingAny: boolean;
  isDeletingAny: boolean;
  t: (key: string, options?: Record<string, unknown>) => string;
}

export const CommentItem = ({
  review,
  depth,
  videoId,
  isEditing,
  isReplying,
  isCollapsed,
  canEdit,
  showReplyForms,
  onEdit,
  onDelete,
  onReply,
  onCancelReply,
  onToggleCollapse,
  onLike,
  onSaveEdit,
  onCancelEdit,
  editForm,
  isLiking,
  isEditingAny,
  isDeletingAny,
  t,
}: CommentItemProps) => {
  const maxDepth = 3; // Maximum nesting depth
  const hasReplies = review?.replies && review.replies.length > 0;
  const indentClass = depth > 0 ? `ml-${Math.min(depth * 6, 12)}` : "";

  // Each reply manages its own collapsed state for its children - collapsed by default
  const [childCollapsedReplies, setChildCollapsedReplies] = useState<
    Set<number>
  >(() => new Set(review?.replies?.map((reply) => reply?.id) || []));

  const toggleChildRepliesCollapsed = (reviewId: number) => {
    const newCollapsed = new Set(childCollapsedReplies);
    if (newCollapsed.has(reviewId)) {
      newCollapsed.delete(reviewId);
    } else {
      newCollapsed.add(reviewId);
    }
    setChildCollapsedReplies(newCollapsed);
  };

  return (
    <div className={`${indentClass} ${depth > 0 ? "pl-8" : ""}`}>
      {/* Main Comment */}
      <div className="flex items-start gap-3">
        <Avatar className="size-8 rounded-full flex-shrink-0">
          <AvatarImage src={avatarPlaceholder} alt="placeholder" />
          <AvatarFallback className="rounded-lg">
            {review?.user?.username?.charAt(0)?.toUpperCase() || "U"}
          </AvatarFallback>
        </Avatar>

        <div className="flex-1 min-w-0">
          {/* Header with username and actions */}
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm sm:text-base font-semibold truncate">
              {review?.user?.username}
            </span>
            {canEdit && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="icon" className="h-8 w-8">
                    <EllipsisVertical className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={onEdit}>
                    <Edit className="mr-2 h-4 w-4" />
                    {t("video_player.edit_comment")}
                  </DropdownMenuItem>
                  <DropdownMenuItem variant="destructive" onClick={onDelete}>
                    <Trash2 className="mr-2 h-4 w-4" />
                    {t("video_player.delete_comment")}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            )}
          </div>

          {/* Comment Content */}
          <div className="space-y-3">
            {isEditing ? (
              <>
                {/* Edit Mode */}
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">
                    {t("comments.rating_label")}:
                  </span>
                  <Rating
                    onValueChange={(number) =>
                      editForm.setValue("rating", number)
                    }
                    defaultValue={editForm.watch("rating")}
                  >
                    {Array.from({ length: 5 }).map((_, index) => (
                      <RatingButton size={15} key={index} />
                    ))}
                  </Rating>
                </div>
                <Textarea
                  {...editForm.register("review")}
                  className="text-base text-[#616161] border-[#E0E0E0]"
                  maxLength={225}
                  placeholder={t("comments.comment_placeholder")}
                />
                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    onClick={onSaveEdit}
                    disabled={isEditingAny || !editForm.formState.isValid}
                    className="bg-green-600 hover:bg-green-700"
                  >
                    <Check className="mr-1 h-4 w-4" />
                    {t("common.confirm")}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={onCancelEdit}
                    disabled={isEditingAny}
                  >
                    <X className="mr-1 h-4 w-4" />
                    {t("common.cancel")}
                  </Button>
                </div>
              </>
            ) : (
              <>
                {/* Read-only Mode */}
                {depth === 0 && (
                  <Rating readOnly={true} defaultValue={review?.rating || 0}>
                    {Array.from({ length: 5 }).map((_, index) => (
                      <RatingButton size={15} key={index} />
                    ))}
                  </Rating>
                )}
                <p className="text-sm sm:text-base text-[#616161] break-words">
                  {review?.review}
                </p>
              </>
            )}

            {/* Date */}
            <span className="text-sm text-[#757575]">
              {review?.created_at
                ? format(new Date(review.created_at), "yyyy-MM-dd")
                : "-"}
            </span>

            {/* Action Buttons */}
            {!isEditing && (
              <div className="flex items-center gap-2 flex-wrap">
                <Button
                  className="text-[#757575] h-8 px-2"
                  variant="ghost"
                  size="sm"
                  onClick={onLike}
                  disabled={isLiking}
                >
                  <ThumbsUp className="mr-1 h-4 w-4" />
                  {review?.like_count || 0}
                </Button>

                {depth < maxDepth && (
                  <Button
                    className="text-[#757575] h-8 px-2"
                    variant="ghost"
                    size="sm"
                    onClick={onReply}
                  >
                    <MessagesSquare className="mr-1 h-4 w-4" />
                    {t("comments.reply")}
                  </Button>
                )}

                {hasReplies && (
                  <Button
                    className="text-[#757575] h-8 px-2"
                    variant="ghost"
                    size="sm"
                    onClick={onToggleCollapse}
                  >
                    {isCollapsed ? (
                      <>
                        <ChevronDown className="mr-1 h-4 w-4" />
                        {t("comments.show_replies")} ({review.replies.length})
                      </>
                    ) : (
                      <>
                        <ChevronUp className="mr-1 h-4 w-4" />
                        {t("comments.hide_replies")}
                      </>
                    )}
                  </Button>
                )}
              </div>
            )}

            {/* Reply Form */}
            {isReplying && showReplyForms && (
              <div className="bg-gray-50 p-3 rounded-lg border">
                <div className="text-sm text-gray-600 mb-2">
                  {t("comments.replying_to", {
                    username: review?.user?.username,
                  })}
                </div>
                <CommentArea
                  videoId={videoId}
                  parentId={review?.id}
                  onCancel={onCancelReply}
                />
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Replies */}
      {hasReplies && !isCollapsed && (
        <div className="mt-3 space-y-3">
          {review.replies.map((reply) => (
            <CommentItem
              key={reply?.id}
              review={reply}
              depth={depth + 1}
              videoId={videoId}
              isEditing={false} // For now, disable editing replies
              isReplying={false}
              isCollapsed={childCollapsedReplies.has(reply?.id)}
              canEdit={canEdit} // This should be checked for each reply individually
              showReplyForms={showReplyForms}
              onEdit={() => {}} // Placeholder
              onDelete={() => {}} // Placeholder
              onReply={() => {}} // Placeholder
              onCancelReply={() => {}}
              onToggleCollapse={() => toggleChildRepliesCollapsed(reply?.id)}
              onLike={() => {}} // Placeholder
              onSaveEdit={() => {}}
              onCancelEdit={() => {}}
              editForm={editForm}
              isLiking={isLiking}
              isEditingAny={isEditingAny}
              isDeletingAny={isDeletingAny}
              t={t}
            />
          ))}
        </div>
      )}
    </div>
  );
};
