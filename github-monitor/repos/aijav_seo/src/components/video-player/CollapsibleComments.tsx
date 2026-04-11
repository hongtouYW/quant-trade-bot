import { useTranslation } from "react-i18next";
import { ChevronDown, ChevronUp } from "lucide-react";
import { Button } from "@/components/ui/button.tsx";
import { useReviewList } from "@/hooks/review/useReviewList.ts";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs.tsx";
import CommentArea from "@/components/comment-area.tsx";
import { CommentList } from "./CommentList";

type CollapsibleCommentsProps = {
  videoId: string | undefined;
  expanded: boolean;
  onToggle: () => void;
};

export const CollapsibleComments = ({
  videoId,
  expanded,
  onToggle,
}: CollapsibleCommentsProps) => {
  const { t } = useTranslation();
  const { data: reviews } = useReviewList(videoId!);

  const commentCount = reviews?.length || 0;

  return (
    <div className="space-y-4">
      {/* Comment Header with Toggle */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <h3 className="font-semibold text-sm sm:text-lg">
            {t("video_player.all_comments")}
          </h3>
          <div className="bg-primary text-primary-foreground text-xs font-medium px-2 py-1 rounded-full">
            {commentCount}
          </div>
        </div>
        <Button
          variant="ghost"
          size="sm"
          onClick={onToggle}
          className="flex items-center gap-2 text-muted-foreground hover:text-foreground"
        >
          {expanded ? (
            <ChevronUp className="size-4" />
          ) : (
            <ChevronDown className="size-4" />
          )}
        </Button>
      </div>

      {/* Collapsible Content */}
      {expanded && (
        <div className="space-y-4">
          <div>
            <CommentArea videoId={videoId} />
          </div>

          <div className="mt-6">
            <Tabs
              defaultValue="all"
              className="rounded-full text-sm sm:text-lg"
            >
              <TabsList className="rounded-full">
                <TabsTrigger
                  className="rounded-full data-[state=active]:bg-primary data-[state=active]:text-primary-foreground"
                  value="all"
                >
                  {t("video_player.all_comments")}
                </TabsTrigger>
              </TabsList>
              <TabsContent value="all">
                <CommentList videoId={videoId} />
              </TabsContent>
            </Tabs>
          </div>
        </div>
      )}
    </div>
  );
};
