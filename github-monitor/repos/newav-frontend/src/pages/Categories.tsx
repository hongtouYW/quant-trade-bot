import { useEffect } from "react";
import { useTranslation } from "react-i18next";
import { LayoutDashboard } from "lucide-react";
import { Link } from "react-router";
import { useInView } from "react-intersection-observer";
import { useTagList } from "@/hooks/tag/useTagList.ts";
import { useInfiniteTagList } from "@/hooks/tag/useInfiniteTagList.ts";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area";
import { EndOfContent } from "@/components/EndOfContent";

export default function Categories() {
  const { t } = useTranslation();

  // First call: Get top tags for navigation links
  const { data: topTagsData } = useTagList({
    page: 1,
    top: 1,
    limit: 10, // Limit to reasonable number of top tags
  });

  // Second call: Get all tags with infinite query
  const {
    data: infiniteTagsData,
    isPending,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteTagList();

  const topTags = topTagsData?.data ?? [];

  // Flatten all pages of tags
  const allTags = infiniteTagsData?.pages?.flatMap((page) => page.data) ?? [];

  // Intersection observer for infinite scroll
  const { ref: inViewRef, inView } = useInView({
    threshold: 1,
    rootMargin: "200px",
  });

  // Trigger fetch when sentinel element is in view
  useEffect(() => {
    if (inView && hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [inView, hasNextPage, isFetchingNextPage, fetchNextPage]);

  return (
    <div className="bg-background text-foreground transition-colors">
      <div className="sticky top-[112px] md:top-0 z-40 bg-background">
        {/* Header - desktop only */}
        <header className="hidden md:block border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <LayoutDashboard
                className="sm:-ms-1"
                size={20}
                aria-hidden="true"
              />
              <span className="">{t("navbar.categories")}</span>
            </div>
          </div>
        </header>

        {/* Top tags section - always visible */}
        {topTags.length > 0 && (
          <div className="border-b">
            <ScrollArea className="h-auto">
              <div className="flex gap-2 p-4 sm:gap-3">
                {/* Top Tags */}
                {topTags.map((tag) => (
                  <Link
                    key={tag.id}
                    to={`/video/list?tag=${tag.id}&tagName=${encodeURIComponent(tag.name)}`}
                    className="relative flex cursor-pointer flex-col items-center gap-3 rounded-full px-7 py-2.5 text-center shadow-xs transition-colors outline-none min-w-fit bg-primary/10 text-primary hover:bg-primary/20 hover:text-primary-foreground dark:hover:bg-primary/30"
                  >
                    <p className="px-2 sm:px-8.5 text-sm leading-none font-normal whitespace-nowrap">
                      {tag.name}
                    </p>
                  </Link>
                ))}
              </div>
              <ScrollBar orientation="horizontal" />
            </ScrollArea>
          </div>
        )}
      </div>

      <div className="my-6 px-4 space-y-4">
        {isPending ? (
          <div className="text-center py-8">
            <span className="text-muted-foreground">{t("common.loading")}</span>
          </div>
        ) : isError ? (
          <div className="text-center py-8">
            <span className="text-red-500">{t("common.error")}</span>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
              {allTags.map((tag, index) => (
                <Link
                  to={`/video/list?tag=${tag.id}&tagName=${encodeURIComponent(tag.name)}`}
                  key={index}
                  className="flex flex-col items-start p-3 gap-3 w-full min-h-[80px] bg-muted text-muted-foreground rounded-lg hover:bg-muted/80 transition-colors sm:flex-row sm:items-center sm:justify-between sm:py-2.5 sm:px-4 sm:rounded-full sm:gap-0 sm:min-h-0"
                >
                  <span className="font-medium text-sm sm:text-base">
                    {tag.name}
                  </span>
                  <span className="text-xs sm:text-base">
                    {tag.video_count}
                    {t("categories.video_count_unit")}
                  </span>
                </Link>
              ))}
            </div>

            {/* Sentinel element for infinite scroll */}
            {hasNextPage && (
              <div
                ref={inViewRef}
                className="h-16 flex items-center justify-center"
              >
                {isFetchingNextPage && (
                  <span className="text-muted-foreground">
                    {t("common.loading")}
                  </span>
                )}
              </div>
            )}

            {/* End of content indicator */}
            {!hasNextPage && <EndOfContent />}
          </>
        )}
      </div>
    </div>
  );
}
