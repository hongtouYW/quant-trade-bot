import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useLocation, useNavigate } from "react-router";
import { useTranslation } from "react-i18next";
import { cn } from "@/lib/utils";

interface VideoPlayerHeaderProps {
  className?: string;
}

export function VideoPlayerHeader({ className }: VideoPlayerHeaderProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();

  // Get navigation context from location state or URL params
  const state = location.state as {
    from?: string;
    categoryName?: string;
    categoryId?: string;
  } | null;

  // Parse URL search params for additional context
  const searchParams = new URLSearchParams(location.search);
  const from = state?.from || searchParams.get("from");
  const categoryName = state?.categoryName || searchParams.get("category");
  // const categoryId = state?.categoryId || searchParams.get("categoryId");

  const handleBack = () => {
    // Always try to go back in browser history
    if (window.history.length > 1) {
      navigate(-1);
    } else {
      // Fallback to home if no history
      navigate("/");
    }
  };

  // Determine what to show based on navigation context
  // const showBackButton = true; // Always show back button
  const showCategory = categoryName || from === "latest" || from === "category";

  return (
    <header
      className={cn(
        "border-b bg-background",
        "sticky top-0 z-50 w-full",
        className,
      )}
    >
      <div className="flex h-14 items-center gap-2 px-4 md:px-4 sm:px-4 xs:px-2 font-semibold">
        {/* Back Button - Always visible on mobile, conditional on desktop */}
        <Button
          variant="ghost"
          size="icon"
          onClick={handleBack}
          className="shrink-0 -ml-2 hover:bg-accent rounded-full"
          aria-label={t("common.back", { defaultValue: "返回" })}
        >
          <ArrowLeft className="h-5 w-5" />
        </Button>

        {/* Category/Context Information */}
        <div className="flex items-center gap-1 flex-1 min-w-0">
          {showCategory && (
            <>
              <span className="text-sm text-muted-foreground truncate max-w-[200px] sm:max-w-none">
                {categoryName ||
                  (from === "latest" ? t("navbar.latest") : "") ||
                  (from === "category"
                    ? t("common.category", { defaultValue: "分类" })
                    : "")}
              </span>
              {/*{categoryName && (*/}
              {/*  <ChevronRight className="h-4 w-4 text-muted-foreground shrink-0" />*/}
              {/*)}*/}
            </>
          )}

          {/*/!* Current Page Title *!/*/}
          {/*<span className="text-sm font-semibold truncate">*/}
          {/*  {t("video_player.video_detail", { defaultValue: "视频详情" })}*/}
          {/*</span>*/}
        </div>

        {/* Optional Action Buttons (for future use) */}
        <div className="flex items-center gap-2">
          {/* Placeholder for future actions like share, more options, etc. */}
        </div>
      </div>
    </header>
  );
}
