import { useEffect, useState, useCallback } from "react";
import producerIcon from "../assets/samples/producers/producer-icon.png";
import { useInfiniteMyPublisherList } from "@/hooks/publisher/useMyPublisherList.ts";
import { PublisherListSkeleton } from "@/components/skeletons";
import { PublisherListEmpty } from "@/components/empty-states";
import { useTranslation } from "react-i18next";
import { ListError } from "@/components/error-states";
import { EndOfContent } from "@/components/EndOfContent";
import { ProfileCard } from "@/components/profile-card";
import { useNavigate } from "react-router";
import { Button } from "@/components/ui/button.tsx";
import { ChevronRight } from "lucide-react";
import { useInView } from "react-intersection-observer";

interface MyPublisherListProps {
  isAllTab?: boolean;
}

export const MyPublisherList = ({ isAllTab = false }: MyPublisherListProps) => {
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
  } = useInfiniteMyPublisherList(30);

  // Flatten all pages into a single array
  const publisherList =
    data?.pages?.flatMap((page) => page.data?.data || []) || [];

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

  // Trigger fetch when sentinel element is in view (only for Publishers tab)
  useEffect(() => {
    if (!isAllTab && inView && hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [isAllTab, inView, hasNextPage, isFetchingNextPage, fetchNextPage]);

  if (isPending)
    return (
      <PublisherListSkeleton
        sectionTitle={t("following.followed_publishers")}
        sectionIcon={
          <img
            loading="lazy"
            src={producerIcon}
            className="size-6"
            alt="producer icon"
          />
        }
      />
    );
  if (isError)
    return (
      <ListError
        title={t("common.error_loading")}
        description={t("common.error_loading_publishers")}
        onRetry={() => refetch()}
        sectionTitle={t("following.followed_publishers")}
        sectionIcon={
          <img
            loading="lazy"
            src={producerIcon}
            className="size-6"
            alt="producer icon"
          />
        }
      />
    );

  // Check for empty data
  if (publisherList.length === 0) {
    return (
      <PublisherListEmpty
        title={t("empty.no_followed_publishers")}
        description={t("empty.no_followed_publishers_desc")}
        onRefresh={() => refetch()}
        sectionTitle={t("following.followed_publishers")}
        sectionIcon={
          <img
            loading="lazy"
            src={producerIcon}
            className="size-6"
            alt="producer icon"
          />
        }
      />
    );
  }

  // If in All tab: show only 2 rows with "More" button
  // If in Publishers tab: show all publishers with infinite scroll
  const displayedPublishers = isAllTab
    ? publisherList.slice(0, maxItems)
    : publisherList;
  const shouldShowMore = isAllTab && publisherList.length > maxItems;

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={producerIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">
            {t("following.followed_publishers")}
          </span>
        </div>
        {shouldShowMore && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl"
            onClick={() =>
              navigate("/following?tab=publishers", { replace: true })
            }
          >
            {t("common.more")}
            <ChevronRight />
          </Button>
        )}
      </div>

      <div className="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-9 gap-1 mt-3.5">
        {displayedPublishers.map((item) => (
          <ProfileCard
            key={item.id}
            item={item}
            type="publisher"
            showSubscribeButton={true}
            showVideoCount={false}
            className="min-h-full px-0 sm:p-[10px] w-full border-transparent sm:hover:border-primary"
            onClick={(publisher) => navigate(`/publisher/${publisher.id}`)}
          />
        ))}
      </div>

      {/* Sentinel element for infinite scroll - only show in Publishers tab */}
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

      {/* End of content indicator - only show in Publishers tab */}
      {!isAllTab && !hasNextPage && <EndOfContent />}
    </div>
  );
};
