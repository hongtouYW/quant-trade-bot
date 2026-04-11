import { useNavigate, useParams } from "react-router";
import { useHotlistDetail } from "@/hooks/video/useHotlistDetail";
import { ChevronLeft, Play } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { InlineError } from "@/components/error-states";
import { VideoListEmpty } from "@/components/empty-states/video-list-empty";
import { useTranslation } from "react-i18next";
import { BackdropContainer } from "@/components/ui/backdrop-container";
import { FixedSidePanel } from "@/components/ui/fixed-side-panel";
import { SeriesImageCard } from "@/components/ui/series-image-card";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { Base64Image } from "@/components/Base64Image.tsx";
import seriesBackdrop from "@/assets/series-backdrop.webp";

export default function HotlistDetail() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { hid } = useParams<{ hid: string }>();
  const hotlistId = hid ? Number(hid) : undefined;
  const { data, isLoading, error } = useHotlistDetail(hotlistId);

  const hotlistVideos = data?.data?.videos || [];

  // Error state with consistent layout
  if (error) {
    return (
      <div className="bg-background text-foreground transition-colors">
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-background">
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
              <span>{t("popular_rankings.ranking_categories")}</span>
            </div>
          </div>
        </div>
        <div className="space-y-4 h-full px-4 lg:px-0">
          <InlineError
            message={`${t("common.error_prefix")}${error.message || t("common.failed_to_load")}`}
          />
        </div>
      </div>
    );
  }

  // Loading state with consistent layout
  if (isLoading) {
    return (
      <div className="bg-background text-foreground transition-colors">
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-background">
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

        <div>
          {/* Mobile Layout - Stacked */}
          <div className="lg:hidden">
            {/* Mobile Playlist Header Skeleton */}
            <div className="relative flex flex-col items-center p-4 overflow-hidden">
              <div
                className="absolute inset-0 bg-overlay-soft"
                aria-hidden="true"
              />
              <div className="relative flex flex-col items-center w-full h-full">
                <Skeleton className="w-full aspect-square rounded-[10px]" />
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
            <FixedSidePanel>
              <BackdropContainer>
                <Skeleton className="w-[320px] h-[320px] rounded-[10px]" />
                <div className="mt-2.5 w-full">
                  <Skeleton className="w-32 h-6 mx-auto" />
                </div>
                <div className="text-sm w-full mb-4">
                  <Skeleton className="w-20 h-4 mx-auto mt-2" />
                </div>
                <Skeleton className="w-full h-12 rounded-full mt-4" />
              </BackdropContainer>
            </FixedSidePanel>

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

  // Empty state with consistent layout
  if (!data?.data || hotlistVideos.length === 0) {
    return (
      <div className="bg-background text-foreground transition-colors">
        <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-background">
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
              <span>{data?.data?.title || t("popular_rankings.ranking_categories")}</span>
            </div>
          </div>
        </div>

        <div>
          <div className="lg:hidden">
            <div className="relative flex flex-col items-center p-4 overflow-hidden">
              <div
                className="absolute inset-0 bg-overlay-soft"
                aria-hidden="true"
              />
              <div className="relative flex flex-col items-center w-full h-full">
                <Base64Image
                  originalUrl={data?.data?.image || seriesBackdrop}
                  alt={data?.data?.title || "Hotlist"}
                  className="w-full aspect-square rounded-[10px] border object-cover"
                />
                <div className="mt-4 w-full">
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0">
                      <div className="text-lg font-semibold tracking-tight">
                        {data?.data?.title || t("popular_rankings.ranking_categories")}
                      </div>
                      <div className="text-sm text-muted-foreground mt-1">
                        0 {t("common.videos")}
                      </div>
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

            <div className="py-4 px-4">
              <div className="flex items-center justify-center min-h-[200px]">
                <VideoListEmpty
                  title={t("tabs.no_videos_found")}
                  description={t("tabs.no_videos_description")}
                />
              </div>
            </div>
          </div>

          <div className="hidden lg:flex relative">
            <FixedSidePanel>
              <BackdropContainer>
                <SeriesImageCard
                  imageSrc={data?.data?.image}
                  imageAlt={data?.data?.title || "Hotlist"}
                  title={data?.data?.title || t("popular_rankings.ranking_categories")}
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

            <div className="ml-[368px] w-[calc(100%-368px)]">
              <div className="py-4 px-4">
                <div className="flex items-center justify-center min-h-[400px]">
                  <VideoListEmpty
                    title={t("tabs.no_videos_found")}
                    description={t("tabs.no_videos_description")}
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
      <div className="sticky top-0 left-0 right-0 z-20 border-b h-14 px-4 bg-background">
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
            <span>{data.data.title}</span>
          </div>
        </div>
      </div>

      <div>
        {/* Mobile Layout - Stacked */}
        <div className="lg:hidden">
          <div className="relative flex flex-col items-center p-4 overflow-hidden">
            <div className="relative flex flex-row md:flex-col gap-4 md:gap-0 md:items-center w-full h-full">
              <Base64Image
                originalUrl={data.data.image}
                alt={data.data.title}
                className="size-[150px] md:aspect-square rounded-[10px] border shrink-0 object-cover"
              />
              <div className="md:mt-4 w-full">
                <div className="flex flex-col md:flex-row h-full justify-between gap-2">
                  <div className="flex-1 min-w-0">
                    <div className="text-lg font-semibold tracking-tight">
                      {data.data.title}
                    </div>
                    <div className="text-sm text-muted-foreground mt-1">
                      {hotlistVideos.length} {t("common.videos")}
                    </div>
                  </div>
                </div>
              </div>

              <Button
                className="hidden md:flex items-center gap-2 border-4 text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
                style={{
                  borderColor: "#EC67FF",
                  background: "#F4A8FFCC",
                  color: "#BA12D3",
                }}
                onClick={() => {
                  if (hotlistVideos.length > 0) {
                    navigate(`/watch/${hotlistVideos[0].id}`, {
                      state: {
                        from: "hotlist-detail",
                        categoryName: data.data.title,
                        hotlistId: hotlistId,
                      },
                    });
                  }
                }}
              >
                <Play className="size-6 stroke-0 fill-[#BA12D3]" />
                {t("common.play_now")}
              </Button>
            </div>
            <Button
              className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
              style={{
                borderColor: "#EC67FF",
                background: "#F4A8FFCC",
                color: "#BA12D3",
              }}
              onClick={() => {
                if (hotlistVideos.length > 0) {
                  navigate(`/watch/${hotlistVideos[0].id}`, {
                    state: {
                      from: "hotlist-detail",
                      categoryName: data.data.title,
                      hotlistId: hotlistId,
                    },
                  });
                }
              }}
            >
              <Play className="size-6 stroke-0 fill-[#BA12D3]" />
              {t("common.play_now")}
            </Button>
          </div>

          <div className="py-4 px-4">
            <div className="flex flex-col gap-3">
              {hotlistVideos.map((video) => (
                <EnhancedVideoCard
                  key={video.id}
                  video={video}
                  layout="horizontal-compact"
                  linkState={{
                    from: "hotlist-detail",
                    categoryName: data.data.title,
                  }}
                  showBadges={true}
                  showRating={false}
                  showActor={true}
                />
              ))}
            </div>
          </div>
        </div>

        {/* Desktop Layout - Fixed Sidebar */}
        <div className="hidden lg:flex relative">
          <FixedSidePanel>
            <BackdropContainer>
              <SeriesImageCard
                imageSrc={data.data.image}
                imageAlt={data.data.title}
                title={data.data.title}
                subtitle={`${hotlistVideos.length} ${t("common.videos")}`}
                actions={
                  <Button
                    className="flex items-center gap-2 border-4 text-base rounded-full w-full h-12 font-semibold shadow-none"
                    style={{
                      borderColor: "#EC67FF",
                      background: "#F4A8FFCC",
                      color: "#BA12D3",
                    }}
                    onClick={() => {
                      if (hotlistVideos.length > 0) {
                        navigate(`/watch/${hotlistVideos[0].id}`, {
                          state: {
                            from: "hotlist-detail",
                            categoryName: data.data.title,
                            hotlistId: hotlistId,
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

          <div className="ml-[368px] w-[calc(100%-368px)]">
            <div className="py-4 px-4">
              <div className="flex flex-col gap-4">
                {hotlistVideos.map((video) => (
                  <EnhancedVideoCard
                    key={video.id}
                    video={video}
                    layout="horizontal-compact"
                    linkState={{
                      from: "hotlist-detail",
                      categoryName: data.data.title,
                    }}
                    showBadges={true}
                    showRating={false}
                    showActor={true}
                  />
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}