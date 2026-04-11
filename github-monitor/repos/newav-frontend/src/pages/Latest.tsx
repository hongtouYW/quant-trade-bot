import { ListFilter } from "lucide-react";
import { useState, useEffect, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { useInView } from "react-intersection-observer";
import { Button } from "@/components/ui/button.tsx";
import { cn } from "@/lib/utils.ts";
import { useSidebar } from "@/components/ui/sidebar.tsx";
import newIcon from "@/assets/samples/latest/new-icon.png";
import calendarIcon from "@/assets/calender-icon.png";
import { useInfiniteSelectedMonthVideoList } from "@/hooks/video/useSelectedMonthVideoList.ts";
import { LatestPageSkeleton } from "@/components/skeletons";
import { LatestVideosEmpty } from "@/components/empty-states";
import { ListError } from "@/components/error-states";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { EndOfContent } from "@/components/EndOfContent";
import {
  Drawer,
  DrawerContent,
  DrawerHeader,
  DrawerTitle,
} from "@/components/ui/drawer";
import { generateMonthlyFilters } from "@/lib/monthUtils";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export type Month = {
  label: string;
  value: string;
};

export default function Latest() {
  const { t } = useTranslation();
  const { state: sidebarState } = useSidebar();
  const [isDesktopOverlayOpen, setIsDesktopOverlayOpen] = useState(false);
  const [isMobileDrawerOpen, setIsMobileDrawerOpen] = useState(false);

  // Generate all 60 months (5 years) - first one is current month with "latest.updated_month" label
  const monthlyFilters = useMemo(() => {
    const baseMonthlyFilters = generateMonthlyFilters(t);

    // Add "其他" (Others) item at the end for early videos
    return [
      ...baseMonthlyFilters,
      {
        label: t("latest.others"),
        value: "early_video",
      },
    ];
  }, [t]);

  const [selectedMonth, setSelectedMonth] = useState<Month>(monthlyFilters[0]);

  // Update selectedMonth label when language changes
  useEffect(() => {
    setSelectedMonth((prev) => {
      const updatedMonth = monthlyFilters.find((m) => m.value === prev.value);
      return updatedMonth || monthlyFilters[0];
    });
  }, [monthlyFilters]);

  const {
    data: infiniteData,
    isPending: isLoading,
    isError,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteSelectedMonthVideoList(selectedMonth.value);

  // Flatten all pages of videos
  const allVideos =
    infiniteData?.pages?.flatMap((page) => page.data?.data) ?? [];

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

  // For mobile: show months 2-3 (index 1-2) next to "本月更新"
  const mobileVisibleMonths = monthlyFilters.slice(1, 3);

  // For desktop: show months 2-11 (index 1-10) next to "本月更新"
  const desktopVisibleMonths = monthlyFilters.slice(1, 11);

  // For desktop overlay: remaining months (from month 12 onwards, excluding "其他")
  const desktopOverlayMonths = monthlyFilters.slice(11, -1);

  const handleMonthClick = (month: Month) => {
    setSelectedMonth(month);
    setIsDesktopOverlayOpen(false);
    setIsMobileDrawerOpen(false);
    // Scroll to top when month changes
    // On desktop: scroll the SidebarInset container (md:overflow-auto)
    // On mobile: scroll the window
    const sidebarInset = document.querySelector('[data-slot="sidebar-inset"]');
    if (sidebarInset && window.innerWidth >= 768) {
      // Desktop: scroll the SidebarInset container
      sidebarInset.scrollTo({ top: 0, behavior: "smooth" });
    } else {
      // Mobile or fallback: scroll the window
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  };

  const getMonthButtonClasses = (
    isActive: boolean,
    size: "default" | "compact" = "default",
  ) =>
    cn(
      "rounded-full font-medium transition-all duration-200 text-center",
      size === "default"
        ? "px-4 py-2 text-sm whitespace-nowrap"
        : "px-3 py-2 text-xs",
      isActive
        ? "bg-primary text-primary-foreground shadow-sm"
        : "bg-card text-muted-foreground hover:bg-primary/10 hover:text-primary",
    );

  return (
    <>
      <div>
        <div className="sticky top-[112px] md:top-0 z-40 bg-background">
          {/* Header - desktop only */}
          <header className="hidden md:block border-b px-4">
            <div className="flex h-14 items-center justify-between gap-4">
              <div className="flex flex-1 items-center gap-2 text-base font-bold">
                <img
                  loading="lazy"
                  src={newIcon}
                  className="size-6"
                  alt="new logo icon"
                />
                <span className="">{t("navbar.latest")}</span>
              </div>
            </div>
          </header>

          {/* Filter section - Desktop: overlay trigger, Mobile: updated + 2 months + drawer button */}
          <div className="border-b px-4 py-4 relative">
            {/* Mobile: 本月更新 + 2 months + 展开 button */}
            <div className="flex md:hidden items-start gap-2">
              {/* 本月更新 button (first month with special label) */}
              <Button
                onClick={() => handleMonthClick(monthlyFilters[0])}
                className={getMonthButtonClasses(
                  selectedMonth.value === monthlyFilters[0].value,
                )}
              >
                {monthlyFilters[0].label}
              </Button>
              {/* Next 2 month buttons */}
              <div className="grid grid-cols-2 gap-2 flex-1">
                {mobileVisibleMonths.map((month, index) => (
                  <Button
                    key={index}
                    onClick={() => handleMonthClick(month)}
                    className={getMonthButtonClasses(
                      selectedMonth.value === month.value,
                    )}
                  >
                    {month.label}
                  </Button>
                ))}
              </div>
              {/* 展开 button for mobile */}
              <Button
                onClick={() => setIsMobileDrawerOpen(true)}
                className="px-4 py-2 rounded-full text-sm font-medium bg-card text-muted-foreground hover:bg-primary/10 hover:text-primary whitespace-nowrap transition-colors"
              >
                展开
              </Button>
            </div>

            {/* Desktop: 本月更新 + First 10 months + filter button */}
            <div className="hidden md:flex items-start gap-2 w-full">
              {/* All 11 month buttons in a single grid for even spacing */}
              <div className="grid grid-cols-11 gap-2 flex-1">
                {/* 本月更新 button (first month with special label) */}
                <Button
                  onClick={() => handleMonthClick(monthlyFilters[0])}
                  className={getMonthButtonClasses(
                    selectedMonth.value === monthlyFilters[0].value,
                  )}
                >
                  {monthlyFilters[0].label}
                </Button>
                {/* Next 10 month buttons */}
                {desktopVisibleMonths.map((month, index) => (
                  <Button
                    key={index}
                    onClick={() => handleMonthClick(month)}
                    className={getMonthButtonClasses(
                      selectedMonth.value === month.value,
                    )}
                  >
                    {month.label}
                  </Button>
                ))}
              </div>
              {/* Filter button on the right */}
              <button
                onClick={() => setIsDesktopOverlayOpen(!isDesktopOverlayOpen)}
                className="text-muted-foreground h-fit hover:text-foreground transition-colors mt-2 flex-shrink-0"
              >
                <ListFilter
                  className={cn(
                    isDesktopOverlayOpen && "rotate-180",
                    "size-6 text-primary",
                  )}
                />
              </button>
            </div>

            {/* Desktop Overlay - Only showing remaining months and "其他" */}
            {isDesktopOverlayOpen && (
              <div className="hidden md:block absolute top-full left-0 right-0 bg-background shadow-lg z-40 border border-border/60">
                <div className="px-4 pb-4 mr-6 max-h-[400px] overflow-y-auto">
                  <div className="grid grid-cols-11 gap-2">
                    {desktopOverlayMonths.map((month, index) => (
                      <Button
                        key={index}
                        onClick={() => handleMonthClick(month)}
                        className={getMonthButtonClasses(
                          selectedMonth.value === month.value,
                        )}
                      >
                        {month.label}
                      </Button>
                    ))}
                    {/* "其他" button at the end */}
                    <Button
                      onClick={() =>
                        handleMonthClick(
                          monthlyFilters[monthlyFilters.length - 1],
                        )
                      }
                      className={getMonthButtonClasses(
                        selectedMonth.value === "early_video",
                      )}
                    >
                      {t("latest.others")}
                    </Button>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
        <div className="px-4 py-4 ">
          <div className="flex flex-1 items-center gap-2 text-base font-bold">
            <img
              loading="lazy"
              src={calendarIcon}
              className="size-6"
              alt="new logo icon"
            />
            <span className="">{selectedMonth.label}</span>
          </div>

          <div className="mt-2.5 flex w-full">
            {isLoading && <LatestPageSkeleton />}
            {isError && (
              <ListError
                title={t("latest.error")}
                description={t("latest.error_recovery_text")}
                onRetry={() => refetch()}
                sectionTitle={t("latest.updated_month")}
                sectionIcon={
                  <img
                    loading="lazy"
                    src={calendarIcon}
                    className="size-6"
                    alt="calendar icon"
                  />
                }
              />
            )}
            {!isLoading && !isError && !allVideos?.length && (
              <div className="w-full">
                <LatestVideosEmpty
                  selectedMonth={selectedMonth.label}
                  title={t("latest.no_videos_title", {
                    month: selectedMonth.label,
                  })}
                  description={t("latest.no_videos_description")}
                />
              </div>
            )}
            {allVideos?.length !== 0 && (
              /* Grid Container */
              <div className="mt-3.5 w-full">
                <div
                  className={cn(
                    "grid gap-3.5 md:gap-2.5",
                    sidebarState === "expanded"
                      ? "grid-cols-3 md:grid-cols-4 lg:grid-cols-4 xl:grid-cols-7 2xl:grid-cols-10"
                      : "grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-8 2xl:grid-cols-10",
                  )}
                >
                  {allVideos?.map((item) => (
                    <div key={item.id} className="flex flex-col">
                      <EnhancedVideoCard
                        video={item}
                        size="sm"
                        layout="vertical"
                        className="mx-0 min-w-0 max-w-none w-full"
                        linkState={{
                          from: "latest",
                          categoryName: `${t("navbar.latest")} - ${selectedMonth.label}`,
                        }}
                        linkPrefix="/watch"
                        showBadges={true} // Show NEW badge for latest videos
                        showRating={true}
                        showActor={true}
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
            )}
          </div>
          <div className="py-4">
            <RecommendedHorizontalList />
          </div>
        </div>
      </div>

      {/* Mobile Drawer for month selection */}
      <Drawer
        open={isMobileDrawerOpen}
        onOpenChange={setIsMobileDrawerOpen}
        modal={true}
        dismissible={true}
        preventScrollRestoration={true}
      >
        <DrawerContent className="!max-h-[50vh] h-[50vh]">
          <DrawerHeader>
            <DrawerTitle>{t("latest.select_month")}</DrawerTitle>
          </DrawerHeader>
          <div className="px-4 pb-4 overflow-y-auto flex-1">
            <div className="grid grid-cols-4 gap-2 pb-4">
              {monthlyFilters.map((month, index) => (
                <Button
                  key={index}
                  onClick={() => handleMonthClick(month)}
                  className={getMonthButtonClasses(
                    selectedMonth.value === month.value,
                    "compact",
                  )}
                >
                  {month.label}
                </Button>
              ))}
            </div>
          </div>
        </DrawerContent>
      </Drawer>
    </>
  );
}
