import { useEffect, useState, useCallback } from "react";
import actorIcon from "../assets/samples/actor/actor-icon.png";
import { useInfiniteSubscribedActorList } from "@/hooks/actor/useSubscribedActorList.ts";
import { ActorListSkeleton } from "@/components/skeletons";
import { ActorListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { ListError } from "@/components/error-states";
import { EndOfContent } from "@/components/EndOfContent";
import { ProfileCard } from "@/components/profile-card";
import { useNavigate } from "react-router";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight } from "lucide-react";
import { useInView } from "react-intersection-observer";

interface MyActorListProps {
  isAllTab?: boolean;
}

export const MyActorList = ({ isAllTab = false }: MyActorListProps) => {
  const { t } = useTranslation();
  const navigate = useNavigate();

  // Use infinite query hook
  const {
    data,
    isPending,
    isError,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteSubscribedActorList(30);

  // Flatten all pages into a single array
  const actorList = data?.pages?.flatMap((page) => page.data?.data || []) || [];

  // Limit to exactly 2 rows at all screen sizes (only in All tab)
  // Mobile (4 cols) = 8 items, Desktop (5 cols) = 10 items, XL (9 cols) = 18 items
  const getMaxItems = useCallback(() => {
    if (typeof window === "undefined") return 8;
    const width = window.innerWidth;
    if (width >= 1280) return 18; // xl: 9 cols × 2 rows
    if (width >= 1024) return 10; // lg: 5 cols × 2 rows
    return 8; // base/md: 4 cols × 2 rows
  }, []);

  const [maxItems, setMaxItems] = useState(getMaxItems);

  useEffect(() => {
    const handleResize = () => setMaxItems(getMaxItems());
    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, [getMaxItems]);

  // Intersection observer for infinite scroll (only when NOT in All tab)
  const { ref: inViewRef, inView } = useInView({
    threshold: 0.1,
    rootMargin: "100px",
  });

  // Trigger fetch when sentinel element is in view (only for Actors tab)
  useEffect(() => {
    if (!isAllTab && inView && hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [isAllTab, inView, hasNextPage, isFetchingNextPage, fetchNextPage]);

  if (isPending)
    return (
      <ActorListSkeleton
        sectionIcon={
          <img
            loading="lazy"
            src={actorIcon}
            className="size-6"
            alt="actor icon"
          />
        }
        sectionTitle={t("following.followed_actresses")}
      />
    );
  if (isError)
    return (
      <ListError
        title={t("common.error_loading")}
        description={t("common.error_loading_actresses")}
        onRetry={() => refetch()}
        sectionTitle={t("following.followed_actresses")}
        sectionIcon={
          <img
            loading="lazy"
            src={actorIcon}
            className="size-6"
            alt="actor icon"
          />
        }
      />
    );

  // Check for empty data
  if (actorList.length === 0) {
    return (
      <ActorListEmpty
        title={t("empty.no_followed_actresses")}
        description={t("empty.no_followed_actresses_desc")}
        onRefresh={() => refetch()}
        sectionTitle={t("following.followed_actresses")}
        sectionIcon={
          <img
            loading="lazy"
            src={actorIcon}
            className="size-6"
            alt="actor icon"
          />
        }
      />
    );
  }

  // If in All tab: show only 2 rows with "More" button
  // If in Actors tab: show all actors with infinite scroll
  const displayedActors = isAllTab ? actorList.slice(0, maxItems) : actorList;
  const shouldShowMore = isAllTab && actorList.length > maxItems;

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={actorIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">{t("following.followed_actresses")}</span>
        </div>
        {shouldShowMore && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl"
            onClick={() => navigate("/following?tab=actors", { replace: true })}
          >
            {t("common.more")}
            <ChevronRight />
          </Button>
        )}
      </div>

      <div className="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-9 gap-1.5 mt-3.5">
        {displayedActors.map((item) => (
          <ProfileCard
            key={item.id}
            item={item}
            type="actor"
            showSubscribeButton={true}
            showVideoCount={false}
            className="min-h-full px-0 sm:p-[10px] w-full border-transparent sm:hover:border-primary"
            onClick={(actor) => navigate(`/actress/${actor.id}`)}
          />
        ))}
      </div>

      {/* Sentinel element for infinite scroll - only show in Actors tab */}
      {!isAllTab && hasNextPage && (
        <div
          ref={inViewRef}
          className="h-16 flex items-center justify-center mt-6"
        >
          {isFetchingNextPage && (
            <span className="text-muted-foreground">{t("common.loading")}</span>
          )}
        </div>
      )}

      {/* End of content indicator - only show in Actors tab */}
      {!isAllTab && !hasNextPage && <EndOfContent />}
    </div>
  );
};
