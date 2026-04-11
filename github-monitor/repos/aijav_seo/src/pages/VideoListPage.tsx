import { useEffect } from "react";
import { useNavigate, useSearchParams } from "react-router";
import { useTranslation } from "react-i18next";
import { ChevronLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { VideoListPageSkeleton } from "@/components/skeletons/VideoListPageSkeleton";
import { PageError } from "@/components/error-states";
import { EmptyState } from "@/components/ui/empty-state";
import { useVideoList } from "@/hooks/video/useVideoList";
import { generatePageNumbers } from "@/lib/pagination";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { TagScrollSelector } from "@/components/ui/tag-scroll-selector";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export default function VideoListPage() {
  const { t } = useTranslation();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  // Scroll to top on component mount or when tag changes
  useEffect(() => {
    const sidebarInset = document.querySelector('[data-slot="sidebar-inset"]');
    if (sidebarInset && window.innerWidth >= 768) {
      sidebarInset.scrollTo({ top: 0, behavior: "smooth" });
    } else {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }, [searchParams.get("tag")]); // Scroll when tag param changes

  // Function to go back to the original source page, not just previous history
  const handleGoBack = () => {
    // Check if there's a referrer in the current search params or if we came from a tag
    const tagId = searchParams.get("tag");

    if (tagId) {
      // If we came from a tag, likely from a video player page, go back to home or video player
      navigate(-1); // Go back 2 steps to skip the intermediate pagination
    } else {
      // For other cases, try to go back to home or the original source
      navigate("/");
    }
  };

  // Get filter parameters
  const tagId = searchParams.get("tag");
  const tagName = searchParams.get("tagName");
  const categoryId = searchParams.get("category");
  const keyword = searchParams.get("search");

  // Get current page from URL params or default to 1
  const currentPage = parseInt(searchParams.get("page") || "1");

  // Build request body based on query params
  const requestBody = {
    ...(tagId && { tag_id: tagId }),
    ...(categoryId && { category_id: categoryId }),
    ...(keyword && { keyword }),
    page: currentPage,
    limit: 20,
  };

  const { data, isPending, isError } = useVideoList(requestBody);
  const videos = data?.data?.data || [];

  // Pagination data
  const totalPages = data?.data?.last_page || 1;

  // Function to handle page change
  const handlePageChange = (page: number) => {
    const newParams = new URLSearchParams(searchParams);
    newParams.set("page", page.toString());
    setSearchParams(newParams);

    // Scroll to top when page changes
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

  const pageNumbers = generatePageNumbers(currentPage, totalPages);

  // Generate toolbar title based on active filters
  const getToolbarTitle = () => {
    if (tagId) return `#${tagName || tagId}`;
    if (categoryId) return t("video_list.category_filter");
    if (keyword) return `"${keyword}"`;
    return t("video_list.all_videos");
  };

  if (isError) return <PageError />;

  return (
    <div className="bg-background text-foreground transition-colors">
      {/* Sticky Header Container */}
      <div className="sticky top-[60px] md:top-0 z-40 bg-background">
        {/* Header with Tag Selector in one line */}
        <header className="border-b px-4 bg-background">
          {/* Single Header Row */}
          <div className="flex h-14 items-center gap-4">
            <Button
              variant="ghost"
              size="sm"
              onClick={handleGoBack}
              className="flex items-center gap-2 flex-shrink-0"
            >
              <ChevronLeft className="w-4 h-4" />
            </Button>

            <div className="flex flex-row justify-center items-center py-2 px-5 gap-2.5 rounded-full flex-shrink-0 bg-brand-light-purple text-brand-accent dark:bg-primary/20 dark:text-primary-foreground transition-colors">
              <h5 className="text-base font-bold leading-none">
                {getToolbarTitle()}
              </h5>
            </div>

            {/* Tag Scroll Selector - Inline with header */}
            {tagId && <TagScrollSelector currentTagId={tagId} />}
          </div>
        </header>
      </div>

      {/* Content */}
      <div className="p-4">
        {isPending ? (
          <VideoListPageSkeleton />
        ) : videos.length === 0 ? (
          <EmptyState
            title={t("video_list.no_videos")}
            description={t("video_list.no_videos_description")}
            className="py-8"
          />
        ) : (
          <div className="grid lg:grid-cols-4 grid-cols-2 gap-2.5">
            {videos.map((video) => (
              <EnhancedVideoCard
                key={video.id}
                video={video}
                layout="vertical-large"
                showBadges={true}
                showRating={true}
                showActor={false}
                linkState={
                  tagName || categoryId
                    ? {
                        from: "category",
                        categoryName: tagName || categoryId || "",
                      }
                    : undefined
                }
                className="w-full h-full"
                imageContainerClassName="h-[120px]"
              />
            ))}
          </div>
        )}
      </div>

      {/* Pagination - Always visible during loading or when multiple pages exist */}
      {(isPending || totalPages > 1) && (
        <div className="flex justify-center px-4 pb-4">
          <Pagination>
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious
                  href="#"
                  onClick={(e) => {
                    e.preventDefault();
                    if (currentPage > 1) {
                      handlePageChange(currentPage - 1);
                    }
                  }}
                  className={
                    currentPage <= 1 ? "pointer-events-none opacity-50" : ""
                  }
                />
              </PaginationItem>

              {pageNumbers.map((pageNum, index) => (
                <PaginationItem key={index}>
                  {pageNum === "ellipsis" ? (
                    <PaginationEllipsis />
                  ) : (
                    <PaginationLink
                      href="#"
                      onClick={(e) => {
                        e.preventDefault();
                        handlePageChange(pageNum as number);
                      }}
                      isActive={currentPage === pageNum}
                    >
                      {pageNum}
                    </PaginationLink>
                  )}
                </PaginationItem>
              ))}

              <PaginationItem>
                <PaginationNext
                  href="#"
                  onClick={(e) => {
                    e.preventDefault();
                    if (currentPage < totalPages) {
                      handlePageChange(currentPage + 1);
                    }
                  }}
                  className={
                    currentPage >= totalPages
                      ? "pointer-events-none opacity-50"
                      : ""
                  }
                />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        </div>
      )}

      <div className="py-4 px-4">
        <RecommendedHorizontalList />
      </div>
    </div>
  );
}
