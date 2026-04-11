import { useEffect } from "react";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight, Grid3X3 } from "lucide-react";
import { useInfiniteCategorizedVideoList } from "@/hooks/video/useCategorizedVideoList.ts";
import { useTranslation } from "react-i18next";
import { useInView } from "react-intersection-observer";
import { ListError } from "@/components/error-states";
import { CategoryCarousel } from "@/components/CategoryCarousel";
import { InfiniteListSkeleton } from "@/components/skeletons/InfiniteListSkeleton";
import { useNavigate } from "react-router";

export const InfiniteList = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const {
    data: infiniteData,
    isPending,
    isError,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteCategorizedVideoList();

  // Intersection observer for infinite scroll
  const { ref: inViewRef, inView } = useInView({
    threshold: 1,
    rootMargin: "1000px",
  });

  // Trigger fetch when sentinel element is in view
  useEffect(() => {
    if (inView && hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [inView, hasNextPage, isFetchingNextPage, fetchNextPage]);

  if (isPending) {
    return <InfiniteListSkeleton categoryCount={3} videosPerCategory={10} />;
  }

  if (isError) {
    return (
      <ListError
        onRetry={() => refetch()}
        sectionTitle={t("homepage.categorized_videos")}
        sectionIcon={<Grid3X3 className="size-6" />}
      />
    );
  }

  // Flatten all pages of categorized videos
  const allCategories = infiniteData?.pages?.flatMap((page) => page.data) ?? [];

  const categoriesWithVideos = allCategories.filter(
    (tag) => tag.video_list && tag.video_list.length > 0,
  );

  if (categoriesWithVideos.length === 0) {
    return null;
  }

  return (
    <div className="space-y-4 sm:space-y-6">
      {allCategories.map((tag) => {
        // Only render categories that have videos
        if (!tag.video_list || tag.video_list.length === 0) {
          return null;
        }

        return (
          <div key={tag.id}>
            <div className="flex justify-between items-center">
              <div className="flex items-center space-x-2">
                <div className="size-5 bg-[#F54336] rounded-r-2xl" />
                <span className="font-bold">{tag.name}</span>
              </div>
              <div className="flex items-center gap-2">
                {/*<Button*/}
                {/*  variant="ghost"*/}
                {/*  size="sm"*/}
                {/*  className="rounded-2xl text-primary hover:text-primary"*/}
                {/*  onClick={() => refetch()}*/}
                {/*>*/}
                {/*  <RefreshCw />*/}
                {/*  {t("common.random")}*/}
                {/*</Button>*/}
                {tag.video_list && tag.video_list.length > 0 && (
                  <Button
                    variant="outline"
                    size="sm"
                    className="rounded-2xl"
                    onClick={() =>
                      navigate(`/category/${tag.id}`, {
                        state: { categoryName: tag.name },
                      })
                    }
                  >
                    {t("common.more")}
                    <ChevronRight />
                  </Button>
                )}
              </div>
            </div>

            <CategoryCarousel videos={tag.video_list} categoryName={tag.name} />
          </div>
        );
      })}

      {/* Sentinel element for infinite scroll */}
      {hasNextPage && (
        <div ref={inViewRef} className="h-16 flex items-center justify-center">
          {isFetchingNextPage && (
            <span className="text-muted-foreground">{t("common.loading")}</span>
          )}
        </div>
      )}

      {/* End of content indicator */}
      {/*{!hasNextPage && <EndOfContent />}*/}
    </div>
  );
};
