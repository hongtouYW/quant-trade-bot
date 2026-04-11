import { ChevronRight, Search, SearchIcon, X, Clock } from "lucide-react";
import { Link, useLocation, useNavigate } from "react-router";
import useEmblaCarousel from "embla-carousel-react";
import { cn } from "@/lib/utils.ts";
import { Button } from "@/components/ui/button.tsx";
import { Input } from "@/components/ui/input.tsx";
import { useRecentSearches } from "@/hooks/useRecentSearches";
import { useGlobalSearch } from "@/hooks/search/useGlobalSearch.ts";
import { useActorSearch } from "@/hooks/search/useActorSearch.ts";
import { usePublisherSearch } from "@/hooks/search/usePublisherSearch.ts";
import { useGroupSearch } from "@/hooks/search/useGroupSearch.ts";
import { useVideoSearch } from "@/hooks/search/useVideoSearch.ts";
import actorIcon from "../assets/samples/actor/actor-icon.png";
import publisherIcon from "../assets/samples/producers/producer-icon.png";
import videoIcon from "@/assets/video-icon.png";
import recomendedListIcon from "../assets/samples/recommended-list/recomended-icon.png";
import type { Video } from "@/types/video.types.ts";
import type { GroupDetailResponse } from "@/types/group.types.ts";
import { PageError } from "@/components/error-states";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { ProfileCard } from "@/components/profile-card";
import { PlaylistCard } from "@/components/playlist-card";
import { useTranslation } from "react-i18next";
import { SearchResultSkeleton } from "@/components/skeletons/SearchResultSkeleton";
import { SearchResultsEmpty } from "@/components/empty-states/search-results-empty";
import { LatestVideoListSkeleton } from "@/components/skeletons";
import { VideoListEmpty } from "@/components/empty-states";
import { ListError } from "@/components/error-states";
import { useState, useEffect } from "react";
import { RecommendedList } from "@/components/recommended-list.tsx";
import { YouMayLikeList } from "@/components/you-may-like-list.tsx";
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import { ChevronLeft } from "lucide-react";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export default function SearchResult() {
  const location = useLocation();
  const params = new URLSearchParams(location.search);
  const { t } = useTranslation();

  const keyword = params.get("keyword") ?? "";
  const type = params.get("type");
  const { addSearch } = useRecentSearches();

  // Always call hooks - they must be called in the same order every render
  const { data, isPending, isError } = useGlobalSearch(keyword);

  // Save search keyword when user performs a search
  useEffect(() => {
    if (keyword) {
      addSearch(keyword);
    }
  }, [keyword, addSearch]);

  // Show search landing page when no keyword is provided
  if (!keyword && !type) {
    return <SearchLandingPage />;
  }

  // Check if this is a dedicated search view
  if (type) {
    return <DedicatedSearchView type={type} keyword={keyword} />;
  }

  if (isError) {
    return <PageError />;
  }

  const actressMetadata: SectionMetadata = {
    name: t("search_result.actress"),
    icon: actorIcon,
    navigationPath: "/actress",
  };

  const publisherMetadata: SectionMetadata = {
    name: t("search_result.publisher"),
    icon: publisherIcon,
    navigationPath: "/publisher",
  };

  const groupsMetadata: SectionMetadata = {
    name: t("search_result.groups"),
    icon: recomendedListIcon,
    navigationPath: "/group",
  };

  // Check if all results are empty
  const hasNoResults =
    data &&
    (!data.actor?.data || data.actor.data.length === 0) &&
    (!data.publisher?.data || data.publisher.data.length === 0) &&
    (!data.video?.data || data.video.data.length === 0) &&
    (!data.video_groups?.data || data.video_groups.data.length === 0);

  return (
    <>
      <div>
        <header className="border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-sm sm:text-base">
              <Search className="size-4" />
              <span className="font-semibold truncate">
                {isPending
                  ? t("search_result.searching") || "Searching..."
                  : `${keyword} 共有 ${data?.total || 0} ${t("search_result.total_results")}`}
              </span>
            </div>
          </div>
        </header>

        {isPending ? (
          <SearchResultSkeleton />
        ) : hasNoResults ? (
          <div className="p-4 space-y-6">
            <SearchResultsEmpty keyword={keyword} />
            <RecommendedList />
            <YouMayLikeList />
          </div>
        ) : (
          <div className="p-4 space-y-4">
            {data?.actor?.data && data.actor.data.length > 0 && (
              <ProfileResultSection
                total={data?.actor?.total}
                data={data?.actor?.data}
                sectionMetadata={actressMetadata}
                type="actor"
                keyword={keyword}
              />
            )}
            {data?.publisher?.data && data.publisher.data.length > 0 && (
              <ProfileResultSection
                total={data?.publisher?.total}
                data={data?.publisher?.data}
                sectionMetadata={publisherMetadata}
                type="publisher"
                keyword={keyword}
              />
            )}
            {data?.video_groups?.data && data.video_groups.data.length > 0 && (
              <GroupsResultSection
                total={data?.video_groups?.total}
                data={data?.video_groups?.data as GroupDetailResponse[]}
                sectionMetadata={groupsMetadata}
                keyword={keyword}
              />
            )}
            {data.video.data && data.video.data.length > 0 && (
              <VideoResult
                total={data.video.total}
                data={data.video.data}
                keyword={keyword}
              />
            )}
          </div>
        )}
      </div>
    </>
  );
}

type ProfileResultSectionProps = {
  sectionMetadata?: SectionMetadata;
  total: number;
  data: Result[];
  type: "actor" | "publisher";
  keyword?: string;
};

type VideoCardProps = {
  total: number;
  data: object[];
  keyword?: string;
};

type GroupsResultSectionProps = {
  sectionMetadata?: SectionMetadata;
  total: number;
  data: GroupDetailResponse[];
  keyword?: string;
};

type SectionMetadata = {
  name: string;
  icon: string;
  navigationPath: string;
};

type Result = {
  id: number;
  name: string;
  image: string;
  is_subscribe: number; // 0 = Not subscribe 1 = Subscribed
};

export const ProfileResultSection = (props: ProfileResultSectionProps) => {
  const { total, data, sectionMetadata, type, keyword } = props;
  const { t } = useTranslation();
  const navigate = useNavigate();

  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={sectionMetadata?.icon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">
            {sectionMetadata?.name} - {total} {t("search_result.total_results")}
          </span>
        </div>
        {total > data.length && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl text-xs sm:text-sm px-2 sm:px-4"
            onClick={() => {
              navigate(
                `/search?keyword=${keyword}&type=${type === "actor" ? "actress" : "publisher"}`,
              );
              scrollToTop();
            }}
          >
            {t("search_result.more")}
            <ChevronRight className="w-3 h-3 sm:w-4 sm:h-4" />
          </Button>
        )}
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        <div ref={emblaRef} className={cn("embla__viewport")}>
          <div className="embla__container flex touch-pan-y">
            {data?.map((item) => (
              <div
                key={item.id}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-2.5"
              >
                <Link to={`${sectionMetadata?.navigationPath}/${item.id}`}>
                  <ProfileCard
                    item={item}
                    type={type}
                    showVideoCount={false}
                    className="min-h-full w-fit border-transparent hover:border-primary"
                  />
                </Link>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

const VideoResult = (props: VideoCardProps) => {
  const { total, data, keyword = "" } = props;
  const videoData: Video[] = data as unknown as Video[];
  const { t } = useTranslation();
  const navigate = useNavigate();

  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={videoIcon}
            className="size-6"
            alt="new logo icon"
          />
          <span className="font-bold">
            {t("search_result.video")} - {total}{" "}
            {t("search_result.total_results")}
          </span>
        </div>
        {total > data.length && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl text-xs sm:text-sm px-2 sm:px-4"
            onClick={() => {
              navigate(`/search?keyword=${keyword}&type=videos`);
              scrollToTop();
            }}
          >
            {t("search_result.more")}
            <ChevronRight className="w-3 h-3 sm:w-4 sm:h-4" />
          </Button>
        )}
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        <div ref={emblaRef} className={cn("embla__viewport")}>
          <div className="embla__container flex touch-pan-y">
            {videoData.map((item) => (
              <div
                key={item.id}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-3"
              >
                <EnhancedVideoCard
                  video={item}
                  size="sm"
                  layout="vertical"
                  linkState={{
                    from: "search",
                    categoryName: `${t("search_result.search_prefix")}: ${keyword}`,
                  }}
                  linkPrefix="/watch"
                  showBadges={true}
                  showRating={true}
                  showActor={true}
                />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

const GroupsResultSection = (props: GroupsResultSectionProps) => {
  const { total, data, sectionMetadata, keyword = "" } = props;
  const { t } = useTranslation();
  const navigate = useNavigate();

  // Embla carousel setup with free scroll
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  return (
    <div>
      <div className="flex justify-between items-center">
        <div className="flex items-center space-x-2">
          <img
            loading="lazy"
            src={sectionMetadata?.icon}
            className="size-6"
            alt="groups icon"
          />
          <span className="font-bold">
            {sectionMetadata?.name} - {total} {t("search_result.total_results")}
          </span>
        </div>
        {total > data.length && (
          <Button
            variant="outline"
            size="sm"
            className="rounded-2xl text-xs sm:text-sm px-2 sm:px-4"
            onClick={() => {
              navigate(`/search?keyword=${keyword}&type=groups`);
              scrollToTop();
            }}
          >
            {t("search_result.more")}
            <ChevronRight className="w-3 h-3 sm:w-4 sm:h-4" />
          </Button>
        )}
      </div>

      {/* Embla Carousel Container */}
      <div className="embla mt-3.5 w-full overflow-hidden">
        <div ref={emblaRef} className={cn("embla__viewport")}>
          <div className="embla__container flex touch-pan-y">
            {data?.map((item, index) => (
              <div
                key={index}
                className="embla__slide flex-[0_0_auto] min-w-0 mr-3.5"
              >
                <PlaylistCard
                  item={item}
                  index={index}
                  showVideosCount={true}
                />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

// Dedicated Search View Component
interface DedicatedSearchViewProps {
  type: string;
  keyword: string;
}

const DedicatedSearchView = ({ type, keyword }: DedicatedSearchViewProps) => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const [currentPage, setCurrentPage] = useState(1);
  const limit = 24;

  // Use appropriate hook based on type
  let hookResult;
  let sectionTitle = "";
  let sectionIcon = null;
  let ItemComponent;

  switch (type) {
    case "actress":
      hookResult = useActorSearch({ keyword, page: currentPage, limit });
      sectionTitle = t("search_result.actress");
      sectionIcon = (
        <img
          loading="lazy"
          src={actorIcon}
          className="size-6"
          alt="actress icon"
        />
      );
      ItemComponent = ({ item }: { item: any }) => (
        <Link to={`/actress/${item.id}`}>
          <ProfileCard
            item={item}
            type="actor"
            showVideoCount={false}
            className="border-transparent hover:border-primary min-h-0"
          />
        </Link>
      );
      break;

    case "publisher":
      hookResult = usePublisherSearch({ keyword, page: currentPage, limit });
      sectionTitle = t("search_result.publisher");
      sectionIcon = (
        <img
          loading="lazy"
          src={publisherIcon}
          className="size-6"
          alt="publisher icon"
        />
      );
      ItemComponent = ({ item }: { item: any }) => (
        <Link to={`/publisher/${item.id}`}>
          <ProfileCard
            item={item}
            type="publisher"
            showVideoCount={false}
            className="border-transparent hover:border-primary min-h-0"
          />
        </Link>
      );
      break;

    case "groups":
      hookResult = useGroupSearch({ keyword, page: currentPage, limit });
      sectionTitle = t("search_result.groups");
      sectionIcon = (
        <img
          loading="lazy"
          src={recomendedListIcon}
          className="size-6"
          alt="groups icon"
        />
      );
      ItemComponent = ({ item, index }: { item: any; index: number }) => (
        <PlaylistCard item={item} index={index} showVideosCount={true} />
      );
      break;

    case "videos":
      hookResult = useVideoSearch({ keyword, page: currentPage, limit });
      sectionTitle = t("search_result.video");
      sectionIcon = (
        <img
          loading="lazy"
          src={videoIcon}
          className="size-6"
          alt="video icon"
        />
      );
      ItemComponent = ({ item }: { item: any }) => (
        <EnhancedVideoCard
          video={item}
          size="sm"
          layout="vertical"
          linkState={{
            from: "search-videos",
            categoryName: `${t("search_result.search_prefix")}: ${keyword}`,
          }}
          linkPrefix="/watch"
          showBadges={true}
          showRating={true}
          showActor={true}
        />
      );
      break;

    default:
      return <div>Invalid search type</div>;
  }

  const { data, isPending, isError, refetch } = hookResult;
  const itemList = data?.data?.data || [];
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
            {sectionIcon}
            <span>{sectionTitle}</span>
            {total > 0 && (
              <span className="text-sm text-gray-500 ml-2">
                ({total} {t("common.results")})
              </span>
            )}
          </div>
        </div>
      </header>
    </>
  );

  // Show loading state with stable header for initial load or when no previous data
  if (isPending && (currentPage === 1 || itemList.length === 0)) {
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
            sectionTitle={sectionTitle}
            sectionIcon={sectionIcon}
          />
        </div>
      </div>
    );
  }

  // Only show empty state if not loading and actually no data
  if (itemList.length === 0 && !isPending) {
    return (
      <div className="flex flex-col min-h-screen">
        {renderHeader()}
        <div className="flex-1 p-4">
          <VideoListEmpty
            title={t("empty.no_results")}
            description={t("empty.no_results_desc")}
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
        {type === "actress" ? (
          /* Grid layout for actress */
          <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4 mb-4">
            {itemList.map((item: any, index: number) => (
              <div key={item.id || index}>
                <ItemComponent item={item} index={index} />
              </div>
            ))}
          </div>
        ) : type === "publisher" ? (
          /* Grid layout for publisher */
          <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4 mb-4">
            {itemList.map((item: any, index: number) => (
              <div key={item.id || index}>
                <ItemComponent item={item} index={index} />
              </div>
            ))}
          </div>
        ) : (
          /* Grid layout for videos/groups */
          <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4 mb-4">
            {itemList.map((item: any, index: number) => (
              <div key={item.id || index}>
                <ItemComponent item={item} index={index} />
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Sticky Pagination - Always visible on desktop when totalPages > 1 */}
      {totalPages > 1 && (
        <div className="sticky bottom-0 border-t border-border/60 bg-background md:bg-background/95 md:backdrop-blur-sm p-4 transition-colors">
          <div className="relative">
            {/* Loading overlay for pagination area - when we have previous data and loading new page */}
            {isPending && itemList.length > 0 && (
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
};

// Search Landing Page Component
const SearchLandingPage = () => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const [searchInput, setSearchInput] = useState("");
  const { recentSearches, addSearch, removeSearch, clearAll } =
    useRecentSearches();

  const handleSearch = (keyword?: string) => {
    const searchTerm = keyword || searchInput.trim();
    if (searchTerm) {
      addSearch(searchTerm);
      navigate(`/search?keyword=${encodeURIComponent(searchTerm)}`);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter") {
      handleSearch();
    }
  };

  const handleRecentSearchClick = (keyword: string) => {
    handleSearch(keyword);
  };

  const handleRemoveSearch = (e: React.MouseEvent, keyword: string) => {
    e.stopPropagation();
    removeSearch(keyword);
  };

  return (
    <div className="flex flex-col min-h-screen bg-background">
      {/* Header */}
      <header className="border-b px-4">
        <div className="flex h-14 items-center justify-between gap-4">
          <div className="flex flex-1 items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(-1)}
              className="mr-2 p-1 h-8 w-8"
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Search className="size-5 text-muted-foreground" />
            <span className="font-bold text-base">{t("common.search")}</span>
          </div>
        </div>
      </header>

      {/* Search Content */}
      <div className="flex-1 p-4">
        {/* Search Input Section */}
        <div className="mb-6">
          <div className="relative">
            <Input
              autoFocus
              className="h-12 pe-12 ps-4 rounded-full bg-[#EEEEEE] placeholder:text-[#BDBDBD] text-base"
              placeholder={t("navbar.search_placeholder")}
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              onKeyDown={handleKeyDown}
            />
            <Button
              onClick={() => handleSearch()}
              className="absolute inset-y-0 end-0 flex items-center justify-center pe-3 h-full bg-transparent hover:bg-transparent"
              variant="ghost"
              size="icon"
            >
              <SearchIcon className="size-5 text-primary" />
            </Button>
          </div>
        </div>

        {/* Recent Searches Section */}
        <div className="space-y-4">
          {recentSearches.length > 0 ? (
            <div>
              {/* Section Header */}
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Clock className="size-4 text-muted-foreground" />
                  <h3 className="text-sm font-semibold text-foreground">
                    {t("search_result.recent_searches")}
                  </h3>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={clearAll}
                  className="text-xs text-muted-foreground hover:text-foreground h-auto p-1"
                >
                  {t("search_result.clear_all")}
                </Button>
              </div>

              {/* Recent Search Items */}
              <div className="space-y-2">
                {recentSearches.map((keyword, index) => (
                  <div
                    key={index}
                    onClick={() => handleRecentSearchClick(keyword)}
                    className="flex items-center justify-between p-3 rounded-lg bg-muted/50 hover:bg-muted cursor-pointer transition-colors group"
                  >
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <Search className="size-4 text-muted-foreground flex-shrink-0" />
                      <span className="text-sm text-foreground truncate">
                        {keyword}
                      </span>
                    </div>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={(e) => handleRemoveSearch(e, keyword)}
                      className="size-8 flex-shrink-0"
                    >
                      <X className="size-4 text-muted-foreground" />
                    </Button>
                  </div>
                ))}
              </div>
            </div>
          ) : (
            /* Empty State */
            <div className="text-center text-muted-foreground py-12">
              <Search className="size-12 mx-auto mb-3 opacity-30" />
              <p className="text-sm font-medium mb-1">
                {t("search_result.no_recent_searches")}
              </p>
              <p className="text-xs">{t("search_result.start_searching")}</p>
            </div>
          )}
        </div>

        <div className="py-4">
          <RecommendedHorizontalList />
        </div>
      </div>
    </div>
  );
};

export const scrollToTop = () => {
  const sidebarInset = document.querySelector('[data-slot="sidebar-inset"]');
  if (sidebarInset && window.innerWidth >= 768) {
    // Desktop: scroll the SidebarInset container
    sidebarInset.scrollTo({ top: 0, behavior: "smooth" });
  } else {
    // Mobile or fallback: scroll the window
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
};
