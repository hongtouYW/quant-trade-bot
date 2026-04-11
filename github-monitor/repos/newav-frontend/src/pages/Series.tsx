import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { useInView } from "react-intersection-observer";
import { LayoutDashboard } from "lucide-react";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group.tsx";
import { MyRecommendedList } from "@/components/my-recommended-list.tsx";
import { useInfinitePublisherList } from "@/hooks/publisher/usePublisherList.ts";
import { PublisherListSkeleton } from "@/components/skeletons";
import { PublisherListEmpty } from "@/components/empty-states";
import { ListError } from "@/components/error-states";
import { EndOfContent } from "@/components/EndOfContent";
import { ProfileCard } from "@/components/profile-card";
import { useNavigate } from "react-router";
import type { PublisherResult } from "@/types/search.types.ts";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export default function Series() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [selected, setSelected] = useState("1");
  const {
    data: publishersInfinite,
    isPending: isPublishersLoading,
    isError: isPublishersError,
    refetch: refetchPublishers,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfinitePublisherList({
    limit: 30,
  });

  // Flatten all pages of publishers
  const publishers =
    publishersInfinite?.pages?.flatMap((page) => page.data?.data) ?? [];

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

  const handlePublisherClick = (publisher: PublisherResult) => {
    navigate(`/publisher/${publisher.id}`, {
      state: { from: "series", categoryName: t("series.all_publishers") },
    });
  };

  const items = [
    { value: "1", label: t("series.series_list") },
    { value: "2", label: t("series.all_publishers") },
  ];

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
              <span className="">{t("navbar.series")}</span>
            </div>
          </div>
        </header>

        {/* Filter section - always visible */}
        <div className="border-b px-4">
          <div className="flex py-3 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <fieldset className="space-y-4">
                <RadioGroup
                  className="grid grid-cols-2 gap-2 sm:grid-flow-col sm:gap-3 sm:grid-cols-none"
                  value={selected}
                  onValueChange={setSelected}
                >
                  {items.map((item) => (
                    <label
                      key={`${item.value}`}
                      className="border-input has-data-[state=checked]:bg-primary/50 has-data-[state=checked]:text-white has-focus-visible:border-ring has-focus-visible:ring-ring/50 relative flex cursor-pointer flex-col items-center gap-3 rounded-full border px-2 py-3 text-center shadow-xs transition-[color,box-shadow] outline-none has-focus-visible:ring-[3px] has-data-disabled:cursor-not-allowed has-data-disabled:opacity-50"
                    >
                      <RadioGroupItem
                        id={`${item.value}`}
                        value={item.value}
                        className="sr-only after:absolute after:inset-0"
                      />
                      <p className="px-2 sm:px-8.5 text-xs sm:text-sm leading-none font-medium">
                        {item.label}
                      </p>
                    </label>
                  ))}
                </RadioGroup>
              </fieldset>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-6 px-4 space-y-4">
        {selected === "1" && (
          <>
            <MyRecommendedList />
          </>
        )}
        {selected === "2" && (
          <>
            {isPublishersLoading && (
              <PublisherListSkeleton
                sectionTitle={t("series.all_publishers")}
                sectionIcon={<LayoutDashboard size={20} />}
              />
            )}
            {isPublishersError && (
              <ListError
                title={t("series.publishers_error")}
                description={t("series.publishers_error_desc")}
                onRetry={() => refetchPublishers()}
                sectionTitle={t("series.all_publishers")}
                sectionIcon={<LayoutDashboard size={20} />}
              />
            )}
            {!isPublishersLoading &&
              !isPublishersError &&
              (!publishers || publishers.length === 0) && (
                <PublisherListEmpty
                  title={t("series.no_publishers")}
                  description={t("series.no_publishers_desc")}
                  sectionTitle={t("series.all_publishers")}
                  sectionIcon={<LayoutDashboard size={20} />}
                />
              )}
            {!isPublishersLoading &&
              !isPublishersError &&
              publishers &&
              publishers.length > 0 && (
                <>
                  <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-10 gap-1 sm:gap-5">
                    {publishers.map((item) => (
                      <div key={item.id}>
                        <ProfileCard
                          item={item}
                          type="publisher"
                          onClick={handlePublisherClick}
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
                </>
              )}
          </>
        )}

        <div className="py-4">
          <RecommendedHorizontalList />
        </div>
      </div>
    </div>
  );
}
