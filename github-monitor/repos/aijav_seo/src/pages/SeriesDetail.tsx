import { useNavigate, useParams } from "react-router";
import { useGroupDetail } from "@/hooks/group/useGroupDetail";
import { useToggleGroupCollection } from "@/hooks/group/useToggleGroupCollection";
import { ChevronLeft, Play, Bookmark, Share2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { InlineError } from "@/components/error-states";
import { VideoListEmpty } from "@/components/empty-states/video-list-empty";
import { useTranslation } from "react-i18next";
import { BackdropContainer } from "@/components/ui/backdrop-container";
import { FixedSidePanel } from "@/components/ui/fixed-side-panel";
import { SeriesImageCard } from "@/components/ui/series-image-card";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { ShareDialog } from "@/components/ShareDialog";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { SeriesPurchaseDialog } from "@/components/SeriesPurchaseDialog";
import seriesBackdrop from "@/assets/series-backdrop.webp";
import diamondIcon from "@/assets/diamond-icon.png";
import { useState } from "react";
import { Base64Image } from "@/components/Base64Image.tsx";

export default function SeriesDetail() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { executeWithAuth } = useAuthAction();
  const { mutate: toggleCollection, isPending: isCollecting } =
    useToggleGroupCollection();
  const { gid } = useParams<{ gid: string }>();
  const groupId = gid ? Number(gid) : undefined;
  const { data, isLoading, error } = useGroupDetail(groupId);
  const [showPurchaseDialog, setShowPurchaseDialog] = useState(false);

  const seriesVideos = data?.videos || [];

  const handleCollect = () => {
    executeWithAuth(() => {
      if (groupId) {
        toggleCollection({ gid: groupId });
      }
    });
  };

  const handlePurchaseClick = () => {
    executeWithAuth(() => {
      setShowPurchaseDialog(true);
    });
  };

  const handlePlayClick = () => {
    if (data?.is_purchase === 0) {
      handlePurchaseClick();
    } else if (seriesVideos.length > 0) {
      navigate(`/watch/${seriesVideos[0].id}`, {
        state: {
          from: "series-detail",
          categoryName: data?.title,
          seriesId: groupId,
        },
      });
    }
  };

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
              <span>{t("series.series_list")}</span>
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
                <div className="mt-4 w-full">
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
                    <div className="w-4 h-4 min-w-[16px] flex items-center justify-center">
                      <Skeleton className="w-3 h-3 rounded" />
                    </div>
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
                <div className="mt-4 w-full">
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
                    <div key={index} className="flex gap-2 items-center">
                      <div className="w-5 h-5 min-w-[20px] flex items-center justify-center">
                        <Skeleton className="w-4 h-4 rounded" />
                      </div>
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
  if (!data || seriesVideos.length === 0) {
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
              <span>{data?.title || t("series.series_list")}</span>
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
                  originalUrl={data?.image || seriesBackdrop}
                  alt={data?.title || "Series"}
                  className="w-full aspect-square rounded-[10px] border object-cover"
                />
                <div className="mt-4 w-full">
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1 min-w-0">
                      <div className="text-lg font-semibold tracking-tight">
                        {data?.title || t("series.series_list")}
                      </div>
                      <div className="text-sm text-muted-foreground mt-1">
                        0 {t("common.videos")}
                      </div>
                    </div>
                    <div className="flex items-start gap-2 flex-shrink-0">
                      <div className="flex flex-col items-center gap-1">
                        <Button
                          size="icon"
                          className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                          onClick={handleCollect}
                          disabled={isCollecting}
                        >
                          <Bookmark
                            className={`size-4 ${
                              data?.is_collect === 1
                                ? "fill-foreground text-foreground"
                                : "text-foreground"
                            }`}
                          />
                        </Button>
                        <span className="text-xs font-medium text-gray-600">
                          {data?.is_collect === 1
                            ? t("video_player.collected")
                            : t("video_player.collect")}
                        </span>
                      </div>
                      <ShareDialog>
                        <div className="flex flex-col items-center gap-1">
                          <Button
                            size="icon"
                            className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                          >
                            <Share2 className="size-4 text-foreground" />
                          </Button>
                          <span className="text-xs font-medium  text-gray-600">
                            {t("video_player.share")}
                          </span>
                        </div>
                      </ShareDialog>
                    </div>
                  </div>
                </div>
                <Button className="flex items-center gap-2 border-4 border-primary bg-[#F4A8FF]/80 text-brand-accent text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none">
                  {data?.is_purchase === 0 ? (
                    <>
                      {t("common.purchase_series")}{" "}
                      <img
                        src={diamondIcon}
                        className="size-4"
                        alt="diamond icon"
                      />{" "}
                      {data?.amount}
                    </>
                  ) : (
                    <>
                      <Play className="size-6 stroke-0 fill-brand-accent" />
                      {t("common.play_now")}
                    </>
                  )}
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
                  imageSrc={data?.image}
                  imageAlt={data?.title || "Series"}
                  title={data?.title || t("series.series_list")}
                  subtitle={`0 ${t("common.videos")}`}
                  titleActions={
                    <div className="flex items-start gap-2">
                      <div className="flex flex-col items-center gap-1">
                        <Button
                          size="icon"
                          className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                          onClick={handleCollect}
                          disabled={isCollecting}
                        >
                          <Bookmark
                            className={`size-4 ${
                              data?.is_collect === 1
                                ? "fill-foreground text-foreground"
                                : "text-foreground"
                            }`}
                          />
                        </Button>
                        <span className="text-xs font-medium text-gray-600">
                          {data?.is_collect === 1
                            ? t("video_player.collected")
                            : t("video_player.collect")}
                        </span>
                      </div>
                      <ShareDialog>
                        <div className="flex flex-col items-center gap-1">
                          <Button
                            size="icon"
                            className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                          >
                            <Share2 className="size-4 text-foreground" />
                          </Button>
                          <span className="text-xs font-medium text-gray-600">
                            {t("video_player.share")}
                          </span>
                        </div>
                      </ShareDialog>
                    </div>
                  }
                  actions={
                    <Button className="flex items-center gap-2 border-4 border-primary bg-[#F4A8FF]/80 text-brand-accent text-base rounded-full w-full h-12 font-semibold shadow-none">
                      {data?.is_purchase === 0 ? (
                        <>
                          {t("common.purchase_series")}{" "}
                          <img
                            src={diamondIcon}
                            className="size-4"
                            alt="diamond icon"
                          />{" "}
                          {data?.amount}
                        </>
                      ) : (
                        <>
                          <Play className="size-6 stroke-0 fill-brand-accent" />
                          {t("common.play_now")}
                        </>
                      )}
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
            <span>{data?.title}</span>
          </div>
        </div>
      </div>

      <div>
        {/* Mobile Layout - Stacked */}
        <div className="lg:hidden">
          <div className="relative flex flex-col items-center p-4 overflow-hidden">
            <div className="relative flex flex-row md:flex-col gap-4 md:gap-0 md:items-center w-full h-full">
              <Base64Image
                originalUrl={data.image}
                alt={data.title}
                className="size-[150px] md:aspect-square rounded-[10px] border shrink-0 object-cover"
              />
              <div className="md:mt-4 w-full">
                <div className="flex flex-col md:flex-row h-full justify-between gap-2">
                  <div className="flex-1 min-w-0">
                    <div className="text-xl font-semibold tracking-tight">
                      {data.title}
                    </div>
                    <div className="text-sm text-gray-600 dark:text-gray-300 mt-1">
                      {seriesVideos.length} {t("common.videos")}
                    </div>
                  </div>
                  <div className="flex items-start gap-2 flex-shrink-0 self-end md:self-auto">
                    <div className="flex flex-col items-center gap-1">
                      <Button
                        size="icon"
                        className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                        onClick={handleCollect}
                        disabled={isCollecting}
                      >
                        <Bookmark
                          className={`size-4 ${
                            data?.is_collect === 1
                              ? "fill-foreground text-foreground"
                              : "text-foreground"
                          }`}
                        />
                      </Button>
                      <span className="text-xs font-medium text-gray-600 dark:text-gray-200">
                        {data?.is_collect === 1
                          ? t("video_player.collected")
                          : t("video_player.collect")}
                      </span>
                    </div>
                    <ShareDialog>
                      <div className="flex flex-col items-center gap-1">
                        <Button
                          size="icon"
                          className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                        >
                          <Share2 className="size-4 text-foreground" />
                        </Button>
                        <span className="text-xs font-medium text-gray-600 dark:text-gray-200">
                          {t("video_player.share")}
                        </span>
                      </div>
                    </ShareDialog>
                  </div>
                </div>
              </div>

              <Button
                className="hidden lg:flex items-center gap-2 border-4 border-primary bg-[#F4A8FF]/80 text-brand-accent text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
                onClick={handlePlayClick}
              >
                {data?.is_purchase === 0 ? (
                  <>
                    {t("common.purchase_series")}{" "}
                    <img
                      src={diamondIcon}
                      className="size-4"
                      alt="diamond icon"
                    />{" "}
                    {data.amount}
                  </>
                ) : (
                  <>
                    <Play className="size-6 stroke-0 fill-brand-accent" />
                    {t("common.play_now")}
                  </>
                )}
              </Button>
            </div>
            <Button
              className="flex items-center gap-2 border-4 border-primary bg-[#F4A8FF]/80 text-brand-accent text-base rounded-full w-full h-12 mt-6 font-semibold shadow-none"
              onClick={handlePlayClick}
            >
              {data?.is_purchase === 0 ? (
                <>
                  {t("common.purchase_series")}{" "}
                  <img
                    src={diamondIcon}
                    className="size-4"
                    alt="diamond icon"
                  />{" "}
                  {data.amount}
                </>
              ) : (
                <>
                  <Play className="size-6 stroke-0 fill-brand-accent" />
                  {t("common.play_now")}
                </>
              )}
            </Button>
          </div>

          <div className="py-4 px-4">
            <div className="flex flex-col gap-3">
              {seriesVideos.map((video) => (
                <div key={video.id} className="flex gap-3 items-center">
                  <div className="flex-1">
                    <EnhancedVideoCard
                      video={video}
                      layout="horizontal-compact"
                      linkState={{
                        from: "series-detail",
                        categoryName: data?.title,
                        seriesId: String(groupId!),
                      }}
                      showBadges={true}
                      showRating={false}
                      showActor={true}
                    />
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
              <SeriesImageCard
                imageSrc={data.image}
                imageAlt={data.title}
                title={data.title}
                subtitle={`${seriesVideos.length} ${t("common.videos")}`}
                titleActions={
                  <div className="flex items-start gap-2">
                    <div className="flex flex-col items-center gap-1">
                      <Button
                        size="icon"
                        className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                        onClick={handleCollect}
                        disabled={isCollecting}
                      >
                        <Bookmark
                          className={`size-4 ${
                            data?.is_collect === 1
                              ? "fill-foreground text-foreground"
                              : "text-foreground"
                          }`}
                        />
                      </Button>
                      <span className="text-xs font-medium  text-gray-600">
                        {data?.is_collect === 1
                          ? t("video_player.collected")
                          : t("video_player.collect")}
                      </span>
                    </div>
                    <ShareDialog>
                      <div className="flex flex-col items-center gap-1">
                        <Button
                          size="icon"
                          className="size-9 rounded-full border-0 bg-muted text-foreground hover:bg-muted/80 hover:opacity-100 transition-colors"
                        >
                          <Share2 className="size-4 text-foreground" />
                        </Button>
                        <span className="text-xs font-medium  text-gray-600">
                          {t("video_player.share")}
                        </span>
                      </div>
                    </ShareDialog>
                  </div>
                }
                actions={
                  <Button
                    className="flex items-center gap-2 border-4 border-primary bg-[#F4A8FF]/80 text-brand-accent text-base rounded-full w-full h-12 font-semibold shadow-none"
                    onClick={handlePlayClick}
                    style={{
                      borderColor: "#EC67FF",
                      background: "#F4A8FFCC",
                      color: "#BA12D3",
                    }}
                  >
                    {data?.is_purchase === 0 ? (
                      <>
                        {t("common.purchase_series")}{" "}
                        <img
                          src={diamondIcon}
                          className="size-4"
                          alt="diamond icon"
                        />{" "}
                        {data.amount}
                      </>
                    ) : (
                      <>
                        <Play className="size-6 stroke-0 fill-brand-accent" />
                        {t("common.play_now")}
                      </>
                    )}
                  </Button>
                }
              />
            </BackdropContainer>
          </FixedSidePanel>

          <div className="ml-[368px] w-[calc(100%-368px)]">
            <div className="py-4 px-4">
              <div className="flex flex-col gap-4">
                {seriesVideos.map((video, index) => (
                  <div key={video.id} className="flex gap-2 items-center">
                    <div className="w-5 h-5 min-w-[20px] flex items-center justify-center font-medium text-gray-600">
                      {index + 1}
                    </div>
                    <div className="flex-1">
                      <EnhancedVideoCard
                        video={video}
                        layout="horizontal-compact"
                        linkState={{
                          from: "series-detail",
                          categoryName: data?.title,
                          seriesId: String(groupId!),
                        }}
                        showBadges={true}
                        showRating={false}
                        showActor={true}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Series Purchase Dialog */}
      {data && (
        <SeriesPurchaseDialog
          open={showPurchaseDialog}
          onClose={() => setShowPurchaseDialog(false)}
          seriesId={groupId!}
          seriesTitle={data.title}
          seriesImage={data.image}
          diamondCost={data.amount}
        />
      )}
    </div>
  );
}
