import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useNavigate } from "react-router";
import { useVideoIndexList } from "@/hooks/video/useVideoIndexList.ts";
import { LatestVideoListSkeleton } from "@/components/skeletons";
import { VideoListEmpty } from "@/components/empty-states";
import { ListError } from "@/components/error-states";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { Button } from "@/components/ui/button.tsx";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import { ChevronLeft } from "lucide-react";
import newIcon from "@/assets/samples/latest/new-icon.png";
import { useSidebar } from "@/components/ui/sidebar";
import { scrollToTop } from "@/pages/SearchResult.tsx";

export default function LatestVideos() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { state: sidebarState, isMobile } = useSidebar();
  const [currentPage, setCurrentPage] = useState(1);
  const limit = isMobile ? 27 : sidebarState === "expanded" ? 32 : 27;

  const { data, isPending, isError, refetch } = useVideoIndexList({
    page: currentPage,
    limit: limit,
    type: "4",
  });

  const videoList = data?.data?.data || [];
  const totalPages = data?.data?.last_page || 1;
  const total = data?.data?.total || 0;

  const handlePrevPage = () => {
    setCurrentPage((prev) => Math.max(1, prev - 1));
    scrollToTop();
  };

  const handleNextPage = () => {
    setCurrentPage((prev) => Math.min(totalPages, prev + 1));
    scrollToTop();
  };

  // Helper function to render the stable header
  const renderHeader = () => (
    <>
      <header className="border-b px-4">
        <div className="flex h-14 items-center justify-between gap-4">
          <div className="flex flex-1 items-center gap-2 text-base font-bold">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(-1)}
              className="mr-2 p-1 h-8 w-8"
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <img
              loading="lazy"
              src={newIcon}
              className="size-6"
              alt="new logo icon"
            />
            <span>{t("navbar.latest")}</span>
            {total > 0 && (
              <span className="text-sm text-gray-500 ml-2">
                ({total} {t("common.videos")})
              </span>
            )}
          </div>
        </div>
      </header>
    </>
  );

  // Show loading state with stable header for initial load or when no previous data
  if (isPending && (currentPage === 1 || videoList.length === 0)) {
    return (
      <div className="flex flex-col min-h-screen">
        {renderHeader()}
        <div className="flex-1 p-4">
          <LatestVideoListSkeleton />
        </div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex flex-col min-h-screen">
        {renderHeader()}
        <div className="flex-1 p-4">
          <ListError
            title={t("error.loading_failed")}
            description={t("error.please_try_again")}
            onRetry={() => refetch()}
            sectionTitle={t("navbar.latest")}
            sectionIcon={
              <img
                loading="lazy"
                src={newIcon}
                className="size-6"
                alt="new icon"
              />
            }
          />
        </div>
      </div>
    );
  }

  // Only show empty state if not loading and actually no data
  if (videoList.length === 0 && !isPending) {
    return (
      <div className="flex flex-col min-h-screen">
        {renderHeader()}
        <div className="flex-1 p-4">
          <VideoListEmpty
            title={t("empty.no_latest_videos")}
            description={t("empty.no_latest_videos_desc")}
          />
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col min-h-screen">
      {renderHeader()}

      {/* Content area that grows to fill available space */}
      <div className="flex-1 p-4 pb-0">
        {/* Video Grid */}
        <div
          className={`grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5 mb-4 ${sidebarState === "expanded" ? "xl:grid-cols-8" : "xl:grid-cols-9"}`}
        >
          {videoList.map((video) => (
            <EnhancedVideoCard
              key={video.id}
              video={video}
              size="sm"
              layout="vertical"
              linkState={{
                from: "latest-videos",
                categoryName: t("navbar.latest"),
              }}
              linkPrefix="/watch"
              showBadges={true}
              showRating={true}
              showActor={true}
            />
          ))}
        </div>
      </div>

      {/* Sticky Pagination - Always visible on desktop when totalPages > 1 */}
      {totalPages > 1 && (
        <div className="sticky bottom-0 border-t border-border/60 bg-background md:bg-background/95 md:backdrop-blur-sm p-4 transition-colors">
          <div className="relative">
            {/* Loading overlay for pagination area - when we have previous data and loading new page */}
            {isPending && videoList.length > 0 && (
              <div className="absolute inset-0 bg-background/80 backdrop-blur-sm rounded-lg flex items-center justify-center z-10 transition-colors">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <div className="w-4 h-4 border-2 border-border/40 border-t-primary rounded-full animate-spin"></div>
                  Loading page {currentPage}...
                </div>
              </div>
            )}

            <Pagination>
              <PaginationContent>
                <PaginationItem>
                  <PaginationPrevious
                    onClick={handlePrevPage}
                    className={
                      currentPage === 1
                        ? "pointer-events-none opacity-50"
                        : "cursor-pointer"
                    }
                  />
                </PaginationItem>

                {/* Show page numbers - limit to show max 5 pages around current */}
                {(() => {
                  const maxVisiblePages = 5;
                  const halfVisible = Math.floor(maxVisiblePages / 2);

                  let startPage = Math.max(1, currentPage - halfVisible);
                  let endPage = Math.min(
                    totalPages,
                    startPage + maxVisiblePages - 1,
                  );

                  // Adjust start if we're near the end
                  if (endPage - startPage < maxVisiblePages - 1) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                  }

                  return Array.from(
                    { length: endPage - startPage + 1 },
                    (_, i) => startPage + i,
                  ).map((page) => (
                    <PaginationItem key={page}>
                      <PaginationLink
                        onClick={() => {
                          setCurrentPage(page);
                          scrollToTop();
                        }}
                        isActive={currentPage === page}
                        className="cursor-pointer"
                      >
                        {page}
                      </PaginationLink>
                    </PaginationItem>
                  ));
                })()}

                <PaginationItem>
                  <PaginationNext
                    onClick={handleNextPage}
                    className={
                      currentPage === totalPages
                        ? "pointer-events-none opacity-50"
                        : "cursor-pointer"
                    }
                  />
                </PaginationItem>
              </PaginationContent>
            </Pagination>
          </div>
        </div>
      )}
    </div>
  );
}
