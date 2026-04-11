import { useEffect } from "react";
import recomendedListIcon from "../assets/samples/recommended-list/recomended-icon.png";
import { PlaylistCard } from "@/components/playlist-card";
import { useInfiniteGroupList } from "@/hooks/group/useGroupList.ts";
import { PlaylistCollectionSkeleton } from "@/components/skeletons";
import { PlaylistEmpty } from "@/components/empty-states";
import { ListError } from "@/components/error-states";
import { EndOfContent } from "@/components/EndOfContent";
import { useTranslation } from "react-i18next";
import { useInView } from "react-intersection-observer";

export const MyRecommendedList = () => {
  const { t } = useTranslation();
  const {
    data: infiniteData,
    isPending,
    isError,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteGroupList({
    limit: 30,
  });

  // Flatten all pages of groups
  const allGroups =
    infiniteData?.pages?.flatMap((page) => page.data?.data) ?? [];

  // Intersection observer for infinite scroll
  const { ref: inViewRef, inView } = useInView({
    threshold: 0.1,
    rootMargin: "100px",
  });

  // Trigger fetch when sentinel element is in view
  useEffect(() => {
    if (inView && hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [inView, hasNextPage, isFetchingNextPage, fetchNextPage]);

  if (isPending) {
    return (
      <PlaylistCollectionSkeleton
        sectionTitle={t("series.series_list")}
        sectionIcon={
          <img
            loading="lazy"
            src={recomendedListIcon}
            className="size-6"
            alt="recommended list icon"
          />
        }
      />
    );
  }

  if (isError) {
    return (
      <ListError
        title={t("series.playlists_error")}
        description={t("series.playlists_error_desc")}
        onRetry={() => refetch()}
        sectionTitle={t("series.series_list")}
        sectionIcon={
          <img
            loading="lazy"
            src={recomendedListIcon}
            className="size-6"
            alt="recommended list icon"
          />
        }
      />
    );
  }

  if (!allGroups || allGroups.length === 0) {
    return (
      <PlaylistEmpty
        title={t("series.no_playlists")}
        description={t("series.no_playlists_desc")}
        sectionTitle={t("series.series_list")}
        sectionIcon={
          <img
            loading="lazy"
            src={recomendedListIcon}
            className="size-6"
            alt="recommended list icon"
          />
        }
      />
    );
  }

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={recomendedListIcon}
            className="size-6"
            alt="recommended list icon"
          />
          <span className="font-bold">{t("series.series_list")}</span>
        </div>
      </div>

      {/* Grid Container */}
      <div className="mt-3.5">
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-4 sm:gap-5">
          {allGroups.map((item, index) => (
            <div key={index}>
              <PlaylistCard
                item={item}
                index={index}
                linkState={{
                  from: "series",
                  categoryName: t("series.series_list"),
                }}
                showVideosCount={true}
                imageSize="responsive"
              />
            </div>
          ))}
        </div>

        {/* Sentinel element for infinite scroll */}
        {hasNextPage && (
          <div
            ref={inViewRef}
            className="h-16 flex items-center justify-center mt-6"
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
      </div>
    </div>
  );
};
