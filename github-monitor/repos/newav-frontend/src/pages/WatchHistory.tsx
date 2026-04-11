import { useTranslation } from "react-i18next";
import { VideoListEmpty } from "@/components/empty-states/video-list-empty";
import { Button } from "@/components/ui/button";
import { ChevronLeft, History, Play } from "lucide-react";
import { useNavigate } from "react-router";
import { usePlayLog } from "@/hooks/video/usePlayLog";
import { Skeleton } from "@/components/ui/skeleton";
import { BackdropContainer } from "@/components/ui/backdrop-container";
import { FixedSidePanel } from "@/components/ui/fixed-side-panel";
import { EmptyStateCard } from "@/components/ui/empty-state-card";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";

export function WatchHistory() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isPending: isLoading } = usePlayLog();

  const watchHistory = data?.data?.data || [];

  // if (isError) {
  //   return (
  //     <div className="bg-white overflow-hidden">
  //       <div className="sticky top-0 z-10 border-b h-14 px-4 bg-white">
  //         <div className="flex h-14 items-center gap-4">
  //           <Button
  //             variant="ghost"
  //             size="icon"
  //             onClick={() => navigate(-1)}
  //             aria-label="返回"
  //             className="size-8"
  //           >
  //             <ChevronLeft className="size-6" />
  //           </Button>
  //           <div className="flex flex-1 items-center gap-2 text-base font-bold">
  //             <span>{t("navbar.watch_history")}</span>
  //           </div>
  //         </div>
  //       </div>
  //       <div className="space-y-4 h-full px-4 lg:px-0">
  //         <InlineError
  //           message={`${t("common.error_prefix")}${isError.message || t("common.failed_to_load")}`}
  //         />
  //       </div>
  //     </div>
  //   );
  // }

  if (isLoading) {
    return (
      <div className="bg-background text-foreground transition-colors">
        {/* Fixed Header - Always on top */}
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-card transition-colors">
          <div className="flex h-14 items-center gap-4">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => navigate(-1)}
              aria-label="返回"
              className="size-8"
            >
              <ChevronLeft className="size-6" />
            </Button>
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <Skeleton className="w-24 h-6" />
            </div>
          </div>
        </div>

        {/* Content with top padding to account for fixed header */}
        <div>
          {/* Mobile Layout - Stacked */}
          <div className="lg:hidden">
            {/* Mobile Playlist Header Skeleton */}
            <div className="relative flex flex-col items-center p-4 overflow-hidden">
              {/* Overlay for opacity */}
              <div
                className="absolute inset-0 bg-overlay-soft"
                aria-hidden="true"
              />
              {/* Content */}
              <div className="relative flex flex-col items-center w-full h-full">
                <div className="w-full max-w-[200px] aspect-square bg-[#FCE9FF] rounded-[10px] border flex items-center justify-center">
                  <History className="size-20 text-[#F4A8FFCC]" />
                </div>
                <div className="mt-2.5 w-full">
                  <Skeleton className="w-32 h-6 mx-auto" />
                </div>

                <div className="mt-6 w-full">
                  <Skeleton className="w-full h-12 rounded-full" />
                </div>
              </div>
            </div>

            {/* Mobile Video List Skeleton */}
            <div className="py-4 px-4">
              <div className="flex flex-col gap-3">
                {[1, 2, 3, 4, 5].map((index) => (
                  <div key={index} className="flex gap-3 items-center">
                    <Skeleton className="w-4 h-4 min-w-[16px]" />
                    <div className="w-full flex gap-2 p-2">
                      <Skeleton className="min-w-[120px] max-w-[120px] h-[68px] rounded-[8px]" />
                      <div className="flex flex-col justify-between gap-1 min-w-0 flex-1">
                        <div className="space-y-1">
                          <div className="space-x-1">
                            <Skeleton className="w-8 h-4 rounded-full inline-block" />
                          </div>
                          <Skeleton className="w-full h-8" />
                        </div>
                        <div className="flex flex-col gap-1">
                          <Skeleton className="w-20 h-4" />
                          <div className="flex items-center gap-3">
                            <Skeleton className="w-12 h-3" />
                            <Skeleton className="w-16 h-3" />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Desktop Layout - Fixed Sidebar */}
          <div className="hidden lg:flex relative">
            {/* Fixed Playlist Header Area - Left Side Skeleton */}
            <FixedSidePanel>
              <BackdropContainer>
                <EmptyStateCard
                  icon={History}
                  title=""
                  actions={
                    <>
                      <div className="mt-2.5 w-full">
                        <Skeleton className="w-32 h-6 mx-auto" />
                      </div>
                      <div className="text-sm w-full mb-4">
                        <Skeleton className="w-20 h-4 mx-auto mt-2" />
                      </div>
                      <Skeleton className="w-full h-12 rounded-full mt-4" />
                    </>
                  }
                />
              </BackdropContainer>
            </FixedSidePanel>

            {/* Desktop Content Area Skeleton */}
            <div className="ml-[368px] w-[calc(100%-368px)]">
              <div className="py-4 px-4">
                <div className="flex flex-col gap-4">
                  {[1, 2, 3, 4, 5].map((index) => (
                    <div key={index} className="flex gap-4 items-center">
                      <Skeleton className="w-5 h-5 min-w-[20px]" />
                      <div className="w-full flex gap-3 p-2">
                        <Skeleton className="min-w-[168px] max-w-[168px] h-[94px] rounded-[8px]" />
                        <div className="flex flex-col justify-between gap-2 min-w-0 flex-1">
                          <div className="space-y-2">
                            <div className="space-x-2">
                              <Skeleton className="w-10 h-5 rounded-full inline-block" />
                            </div>
                            <Skeleton className="w-full h-10" />
                          </div>
                          <div className="flex items-center justify-between gap-2">
                            <Skeleton className="w-24 h-4" />
                            <div className="flex items-center gap-4">
                              <Skeleton className="w-12 h-3" />
                              <Skeleton className="w-16 h-3" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (watchHistory.length === 0) {
    return (
      <div className="bg-background text-foreground transition-colors">
        {/* Fixed Header - Always on top */}
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-card transition-colors">
          <div className="flex h-14 items-center gap-4">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => navigate(-1)}
              aria-label="返回"
              className="size-8"
            >
              <ChevronLeft className="size-6" />
            </Button>
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <span>{t("navbar.watch_history")}</span>
            </div>
          </div>
        </div>

        {/* Content with top padding to account for fixed header */}
        <div>
          {/* Mobile Layout - Stacked */}
          <div className="lg:hidden">
            {/* Mobile Playlist Header */}
            <div className="relative flex flex-col items-center p-4 overflow-hidden">
              {/* Overlay for opacity */}
              <div
                className="absolute inset-0 bg-overlay-soft"
                aria-hidden="true"
              />
              {/* Content */}
              {/* here */}
              <div className="relative flex flex-col items-center w-full h-full">
                <div className="flex gap-3 w-full">
                  <div className="size-[150px] aspect-square bg-[#FCE9FF] rounded-[10px] flex items-center justify-center">
                    <History className="size-20 text-[#F4A8FFCC]" />
                  </div>
                  <div>
                    <div className="mt-2.5 text-xl font-semibold tracking-tight w-full">
                      {t("navbar.watch_history")}
                    </div>
                  </div>
                </div>

                <Button
                  className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
                  style={{
                    borderColor: "#EC67FF",
                    background: "#F4A8FFCC",
                    color: "#BA12D3",
                  }}
                  disabled
                >
                  <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                  {t("common.play_now")}
                </Button>
              </div>
            </div>

            {/* Mobile Empty State */}
            <div className="py-4 px-4">
              <div className="flex items-center justify-center min-h-[200px]">
                <VideoListEmpty
                  title={t("empty.no_watch_history")}
                  description={t("empty.no_watch_history_desc")}
                />
              </div>
            </div>

            <div className="py-4 px-4">
              <RecommendedHorizontalList />
            </div>
          </div>

          {/* Desktop Layout - Fixed Sidebar */}
          <div className="hidden lg:flex relative">
            {/* Fixed Playlist Header Area - Left Side */}
            <FixedSidePanel>
              <BackdropContainer>
                <EmptyStateCard
                  icon={History}
                  title={t("navbar.watch_history")}
                  subtitle={`0 ${t("common.videos")}`}
                  actions={
                    <Button
                      className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 font-semibold shadow-none"
                      style={{
                        borderColor: "#EC67FF",
                        background: "#F4A8FFCC",
                        color: "#BA12D3",
                      }}
                      disabled
                    >
                      <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                      {t("common.play_now")}
                    </Button>
                  }
                />
              </BackdropContainer>
            </FixedSidePanel>

            {/* Desktop Content Area */}
            <div className="ml-[368px] w-[calc(100%-368px)]">
              <div className="py-4 px-4">
                <div className="flex items-center justify-center min-h-[400px]">
                  <VideoListEmpty
                    title={t("empty.no_watch_history")}
                    description={t("empty.no_watch_history_desc")}
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-background text-foreground transition-colors">
      {/* Fixed Header - Always on top */}
      <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-card transition-colors">
        <div className="flex h-14 items-center gap-4">
          <Button
            variant="ghost"
            size="icon"
            onClick={() => navigate(-1)}
            aria-label="返回"
            className="size-8"
          >
            <ChevronLeft className="size-6" />
          </Button>
          <div className="flex flex-1 items-center gap-2 text-base font-bold">
            <span>{t("navbar.watch_history")}</span>
          </div>
        </div>
      </div>

      {/* Content with top padding to account for fixed header */}
      <div>
        {/* Mobile Layout - Stacked */}
        <div className="lg:hidden">
          {/* Mobile Playlist Header */}
          <div className="relative flex flex-col items-center p-4 overflow-hidden">
            {/* Overlay for opacity */}
            <div
              className="absolute inset-0 bg-overlay-soft"
              aria-hidden="true"
            />
            {/* Content */}
            <div className="relative flex flex-col items-center w-full h-full">
              <div className="flex gap-3 w-full">
                <div className="size-[150px] aspect-square bg-[#FCE9FF] rounded-[10px] flex items-center justify-center">
                  <History className="size-20 text-[#F4A8FFCC]" />
                </div>
                <div>
                  <div className="mt-2.5 text-xl font-semibold tracking-tight w-full">
                    {t("navbar.watch_history")}
                  </div>
                </div>
              </div>

              <Button
                className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
                style={{
                  borderColor: "#EC67FF",
                  background: "#F4A8FFCC",
                  color: "#BA12D3",
                }}
                onClick={() => {
                  // Play first video in history
                  if (watchHistory.length > 0) {
                    navigate(`/watch/${watchHistory[0].id}`, {
                      state: {
                        from: "watch-history",
                        categoryName: t("navbar.watch_history"),
                      },
                    });
                  }
                }}
              >
                <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                {t("common.play_now")}
              </Button>
            </div>
          </div>

          {/* Mobile Video List */}
          <div className="py-4 px-4">
            <div className="flex flex-col gap-3">
              {watchHistory.map((video, index) => (
                <div key={index} className="flex gap-3 items-center">
                  {/*<span className="text-sm font-semibold text-gray-600 min-w-[16px]">*/}
                  {/*  {index + 1}*/}
                  {/*</span>*/}
                  <EnhancedVideoCard
                    className="w-full"
                    video={video}
                    layout="horizontal-compact"
                    linkState={{
                      from: "watch-history",
                      categoryName: t("navbar.watch_history"),
                    }}
                    showBadges={true}
                    showRating={false}
                    showActor={true}
                  />
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Desktop Layout - Fixed Sidebar */}
        <div className="hidden lg:flex relative">
          {/* Fixed Playlist Header Area - Left Side */}
          <FixedSidePanel>
            <BackdropContainer>
              <EmptyStateCard
                icon={History}
                title={t("navbar.watch_history")}
                subtitle={`${watchHistory.length} ${t("common.videos")}`}
                actions={
                  <Button
                    className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 font-semibold shadow-none"
                    style={{
                      borderColor: "#EC67FF",
                      background: "#F4A8FFCC",
                      color: "#BA12D3",
                    }}
                    onClick={() => {
                      // Play first video in history
                      if (watchHistory.length > 0) {
                        navigate(`/watch/${watchHistory[0].id}`, {
                          state: {
                            from: "watch-history",
                            categoryName: t("navbar.watch_history"),
                          },
                        });
                      }
                    }}
                  >
                    <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                    {t("common.play_now")}
                  </Button>
                }
              />
            </BackdropContainer>
          </FixedSidePanel>

          {/* Desktop Content Area */}
          <div className="ml-[368px] w-[calc(100%-368px)]">
            <div className="py-4 px-4">
              <div className="flex flex-col gap-4">
                {watchHistory.map((video, index) => (
                  <div key={index} className="flex gap-4 items-center">
                    <span className="text-base text-center font-semibold text-gray-600 min-w-[20px]">
                      {index + 1}
                    </span>
                    <EnhancedVideoCard
                      className="w-full"
                      video={video}
                      layout="horizontal-compact"
                      linkState={{
                        from: "watch-history",
                        categoryName: t("navbar.watch_history"),
                      }}
                      showBadges={true}
                      showRating={false}
                      showActor={true}
                    />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
