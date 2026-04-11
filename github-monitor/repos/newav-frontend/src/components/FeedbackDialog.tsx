import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { CircleHelp } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { useFeedback } from "@/hooks/user/useFeedback";

interface FeedbackDialogProps {
  videoId?: number | string;
}

export function FeedbackDialog({ videoId }: FeedbackDialogProps) {
  const { t } = useTranslation();
  const { executeWithAuth } = useAuthAction();
  const [title, setTitle] = useState("");
  const [content, setContent] = useState("");
  const [open, setOpen] = useState(false);

  const feedbackMutation = useFeedback();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!title.trim() || !content.trim()) {
      return;
    }

    const trimmedTitle = title.trim();
    const trimmedContent = content.trim();
    const contentWithVideoId = videoId
      ? `${trimmedContent} [VID: ${videoId}]`
      : trimmedContent;

    executeWithAuth(() => {
      feedbackMutation.mutate(
        { title: trimmedTitle, content: contentWithVideoId },
        {
          onSuccess: (data) => {
            if (data.code === 1) {
              setTitle("");
              setContent("");
              setOpen(false);
            }
          },
        },
      );
    });
  };

  const handleClose = () => {
    setOpen(false);
    setTitle("");
    setContent("");
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button
          className="rounded-full font-semibold size-9 sm:w-fit px-2! sm:h-9 sm:px-4! bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF]"
          variant="outline"
        >
          <CircleHelp />
          <span className="hidden sm:inline">{t("video_player.feedback")}</span>
        </Button>
      </DialogTrigger>
      <DialogContent
        className="sm:max-w-md max-w-[90vw] rounded-lg backdrop-blur-sm bg-background/95"
        style={{
          backdropFilter: "blur(4px)",
        }}
      >
        <DialogHeader>
          <DialogTitle className="text-[22px] font-semibold">
            {t("feedback.title")}
          </DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="feedback-title" className="text-sm font-medium">
              {t("feedback.title_label")}
            </Label>
            <Input
              id="feedback-title"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder={t("feedback.title_placeholder")}
              className="w-full"
              required
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="feedback-content" className="text-sm font-medium">
              {t("feedback.content_label")}
            </Label>
            <Textarea
              id="feedback-content"
              value={content}
              onChange={(e) => setContent(e.target.value)}
              placeholder={t("feedback.content_placeholder")}
              className="w-full min-h-24 resize-none"
              rows={4}
              required
            />
          </div>

          <DialogFooter className="gap-2 mt-6">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={feedbackMutation.isPending}
              className="flex-1 rounded-lg"
            >
              {t("feedback.cancel")}
            </Button>
            <Button
              type="submit"
              className="flex-1 bg-[#EC67FF] hover:bg-[#EC67FF]/90 text-white font-semibold rounded-lg"
              disabled={
                !title.trim() || !content.trim() || feedbackMutation.isPending
              }
            >
              {feedbackMutation.isPending
                ? t("feedback.submitting")
                : t("feedback.submit")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
