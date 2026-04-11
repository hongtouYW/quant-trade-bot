import { useTranslation } from "react-i18next";
import { ArrowLeft } from "lucide-react";
import { useNavigate } from "react-router";
import { Button } from "@/components/ui/button";
import { useNotices } from "@/hooks/notice/useNotices";
import type { Notice } from "@/types/notice.types.ts";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export default function Notifications() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: noticesData } = useNotices();

  const notices = (noticesData?.data || []) as Notice[];
  const displayNotices = notices.length > 0 ? [] : ([] as Notice[]);

  return (
    <div className="flex flex-col min-h-screen bg-background">
      {/* Header */}
      <div className="sticky top-0 z-10 flex items-center gap-4 px-4 py-3 bg-background border-b">
        <Button
          variant="ghost"
          size="icon"
          onClick={() => navigate(-1)}
          className="h-8 w-8"
        >
          <ArrowLeft className="h-5 w-5" />
        </Button>
        <h1 className="text-lg font-semibold">
          {t("notifications.page_title")}
        </h1>
      </div>

      {/* Content */}
      <div className="flex-1 p-4">
        <div className="bg-card rounded-lg border">
          {displayNotices.length === 0 ? (
            <div className="text-center py-12 px-4 text-muted-foreground">
              <div className="text-sm">{t("common.no_data")}</div>
            </div>
          ) : (
            <div className="divide-y">
              {displayNotices.map((notice, index) => (
                <div key={index} className="p-4">
                  <h4 className="font-medium text-sm text-foreground mb-1">
                    {notice.title}
                  </h4>
                  <div
                    className="text-xs text-muted-foreground prose prose-sm max-w-none"
                    dangerouslySetInnerHTML={{ __html: notice.content }}
                  />
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <div className="py-4 px-4">
        <RecommendedHorizontalList />
      </div>
    </div>
  );
}
