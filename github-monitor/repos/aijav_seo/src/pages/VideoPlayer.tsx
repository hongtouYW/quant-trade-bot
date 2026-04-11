import { Link, useLocation, useNavigate, useParams } from "react-router";
import { useState, useCallback, useEffect, useMemo } from "react";
import type { VideoInfo } from "@/types/video.types.ts";
import type { ApiResponse } from "@/types/api-response.ts";
import { UserRoundIcon } from "lucide-react";
import { useTranslation } from "react-i18next";
import { Avatar, AvatarFallback } from "@/components/ui/avatar.tsx";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu.tsx";
import { PageError } from "@/components/error-states";
import { VideoPlayerHeader } from "@/components/VideoPlayerHeader";
import { VideoNotFound } from "@/components/video-not-found.tsx";
import { VideoPlayerSkeleton } from "@/components/video-player-skeleton.tsx";
import { Separator } from "@/components/ui/separator.tsx";
import { VoucherDialog } from "@/components/VoucherDialog";
import { Base64Image } from "@/components/Base64Image";
import { buildVideoSlug, buildWatchPath, normalizeLang } from "@/lib/watch-url";

// Import our custom components
import { useVideoPlayerState } from "@/hooks/video/useVideoPlayerState";
import { VideoPlayerCore } from "@/components/video-player/VideoPlayerCore";
import { VideoMetadata } from "@/components/video-player/VideoMetadata";
import { VideoActionButtons } from "@/components/video-player/VideoActionButtons";
import { VIPAdsBlock } from "@/components/video-player/VIPAdsBlock";
import { RecommendedList } from "@/components/video-player/RecommendedList";
import { CollectedVideosList } from "@/components/video-player/CollectedVideosList";
import { WatchHistoryList } from "@/components/video-player/WatchHistoryList";
import { SeriesVideosList } from "@/components/video-player/SeriesVideosList";
import { CollapsibleComments } from "@/components/video-player/CollapsibleComments";
import { ThumbnailGallery } from "@/components/video-player/ThumbnailGallery";
import { TagsCarousel } from "@/components/video-player/TagsCarousel";
import { InfoCard } from "@/components/info-card";

// Import hooks
import { useUser } from "@/contexts/UserContext";
import { useConfigContext } from "@/contexts/ConfigContext";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { useVoucherPurchase } from "@/hooks/video/useVoucherPurchase";

interface VideoPlayerProps {
  loaderData?: VideoInfo | null;
}

export default function VideoPlayer({ loaderData }: VideoPlayerProps) {
  const { t, i18n } = useTranslation();
  const { id, lang } = useParams();
  const location = useLocation();
  const navigate = useNavigate();

  // Wrap loader data into ApiResponse envelope for React Query initialData
  const initialVideoData: ApiResponse<VideoInfo> | undefined = loaderData
    ? { code: 1, msg: "ok", timestamp: 0, data: loaderData }
    : undefined;
  const state = useVideoPlayerState(id, initialVideoData);

  // SSR flag: starts true (matches server), flips false after hydration
  const [isSSR, setIsSSR] = useState(true);
  useEffect(() => { setIsSSR(false); }, []);
  const [voucherDialogOpen, setVoucherDialogOpen] = useState(false);
  const { user } = useUser();
  const { configList } = useConfigContext();
  const { executeWithAuth } = useAuthAction();
  const videoIdNumeric = state.videoInfo?.data?.id;
  const hasValidVideoId =
    typeof videoIdNumeric === "number" && !Number.isNaN(videoIdNumeric);
  const voucherCost = Number(state.videoInfo?.data?.video_point ?? 1);
  const userPoints = user?.point ?? 0;
  const {
    purchaseVoucher: autoPurchaseVoucher,
    isPurchasing: isAutoPurchasing,
  } = useVoucherPurchase({
    vid: videoIdNumeric,
    videoKey: id,
  });
  const videoContent = state.videoContent;

  const resolvedLang =
    normalizeLang(lang) || normalizeLang(i18n.language) || "zh";
  const canonicalId = videoContent?.id ?? id;
  const canonicalSlug = buildVideoSlug(videoContent?.mash, videoContent?.title);
  const canonicalPath =
    canonicalId && canonicalSlug
      ? buildWatchPath({
          lang: resolvedLang,
          id: canonicalId,
          slug: canonicalSlug,
        })
      : null;

  useEffect(() => {
    if (!canonicalPath) return;
    if (location.pathname === canonicalPath) return;
    navigate(`${canonicalPath}${location.search}`, {
      replace: true,
      state: location.state,
    });
  }, [
    canonicalPath,
    location.pathname,
    location.search,
    location.state,
    navigate,
  ]);

  const attemptVoucherPurchase = useCallback(async () => {
    if (typeof window === "undefined" || !hasValidVideoId) {
      setVoucherDialogOpen(true);
      return;
    }

    const shouldSkipDialog =
      localStorage.getItem("voucher_dont_ask") === "true";

    if (!shouldSkipDialog) {
      setVoucherDialogOpen(true);
      return;
    }

    if (userPoints < voucherCost) {
      setVoucherDialogOpen(true);
      return;
    }

    if (isAutoPurchasing) return;

    const result = await autoPurchaseVoucher();

    if (result.status === "insufficient") {
      localStorage.removeItem("voucher_dont_ask");
      setVoucherDialogOpen(true);
    } else if (result.status === "error") {
      setVoucherDialogOpen(true);
    }
  }, [
    autoPurchaseVoucher,
    isAutoPurchasing,
    userPoints,
    voucherCost,
    hasValidVideoId,
  ]);

  const handleVoucherRequest = useCallback(() => {
    executeWithAuth(() => {
      void attemptVoucherPurchase();
    });
  }, [attemptVoucherPurchase, executeWithAuth]);

  // Memoize sidebar component to prevent remounting on every render
  const sidebarComponent = useMemo(() => {
    const from = location.state?.from;
    const currentVideoId = id;

    switch (from) {
      case "collected-videos":
        return <CollectedVideosList currentVideoId={currentVideoId} />;
      case "watch-history":
        return <WatchHistoryList currentVideoId={currentVideoId} />;
      case "series-detail":
        return (
          <SeriesVideosList
            currentVideoId={currentVideoId}
            seriesTitle={location.state?.categoryName}
            seriesId={location.state?.seriesId}
          />
        );
      default:
        return <RecommendedList />;
    }
  }, [id, location.state?.from, location.state?.categoryName, location.state?.seriesId]);

  // Early returns for loading/error states
  if (state.isVideoInfoPending) return <VideoPlayerSkeleton />;
  if (state.isError) return <PageError />;

  // Handle business logic errors (API success but with error codes)
  if (state.videoInfo?.code !== 1) {
    return (
      <VideoNotFound
        message={state.videoInfo?.msg || t("video_player.video_not_found")}
        errorCode={state.videoInfo?.code}
      />
    );
  }

  // Ensure data exists before proceeding
  if (!state.videoContent) {
    return <VideoNotFound message={t("video_player.incomplete_data")} />;
  }

  const actorList = Array.isArray(state.videoContent.actor)
    ? state.videoContent.actor
    : [];
  const primaryActors = actorList.slice(0, 2);
  const remainingActors = actorList.slice(2);
  const firstActor = primaryActors[0];
  const allActorNames = actorList
    .map((actor) => actor?.name)
    .filter(Boolean)
    .join(", ");

  return (
    <div className="min-h-screen">
      {/* Video Player Header - Sticky on mobile */}
      <VideoPlayerHeader />

      <div className="flex flex-col xl:flex-row gap-4 xl:h-[calc(100vh-3.5rem)] xl:p-4 md:p-4 sm:p-0 xs:p-0">
        <div className="flex-1 xl:pr-2 xl:overflow-y-auto xl:h-full scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
          <div className="relative">
            <VideoPlayerCore
              videoUrl={state.videoUrl || null}
              videoContent={state.videoContent}
              shouldShowAuthOverlay={state.shouldShowAuthOverlay}
              onLoginClick={state.handleLoginClick}
              overlayErrorType={state.overlayErrorType}
              overlayErrorMessage={state.overlayErrorMessage}
              onPurchaseClick={handleVoucherRequest}
              vipVideoDuration={configList?.vip_video_duration}
              isSSR={isSSR}
            />

            <VideoMetadata videoContent={state.videoContent} />
          </div>
          <Separator className="my-6 mb-4 h-4" />

          {/* Video Metadata */}
          <div className="p-4 md:px-4 sm:px-4 xs:px-2">
            <div className="flex items-center justify-between gap-4 xs:gap-2 overflow-hidden">
              <div className="flex items-center gap-3 xs:gap-2 flex-1 min-w-0 overflow-hidden">
                {primaryActors.length > 0 ? (
                  <>
                    <Avatar className="size-6 md:size-10 rounded-full xs:size-8 flex-shrink-0">
                      {firstActor?.image ? (
                        <Base64Image
                          originalUrl={firstActor.image}
                          alt={`${firstActor?.name ?? "actor"} avatar`}
                          className="w-full h-full object-cover rounded-full"
                        />
                      ) : (
                        <AvatarFallback>
                          <UserRoundIcon
                            size={16}
                            className="opacity-60 xs:size-3"
                            aria-hidden="true"
                          />
                        </AvatarFallback>
                      )}
                    </Avatar>
                    <div
                      className="flex items-center gap-2 min-w-0 overflow-hidden text-muted-foreground"
                      title={allActorNames}
                    >
                      {primaryActors.map((actor, index) => (
                        <span
                          key={actor.id}
                          className="flex items-center gap-1 whitespace-nowrap text-sm font-semibold capitalize text-foreground"
                        >
                          {index > 0 && (
                            <span className="text-muted-foreground">,</span>
                          )}
                          <Link
                            to={`/actress/${actor.id}`}
                            className="underline underline-offset-3 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 rounded-sm"
                          >
                            {actor.name}
                          </Link>
                        </span>
                      ))}
                      {remainingActors.length > 0 && (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <button
                              type="button"
                              className="text-sm font-semibold text-primary underline underline-offset-3 whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 rounded-sm"
                              aria-label={`View ${remainingActors.length} more actors`}
                              title={allActorNames}
                            >
                              +{remainingActors.length}
                            </button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent
                            align="start"
                            className="max-h-60 overflow-y-auto"
                          >
                            {remainingActors.map((actor) => (
                              <DropdownMenuItem key={actor.id} asChild>
                                <Link
                                  to={`/actress/${actor.id}`}
                                  className="capitalize"
                                >
                                  {actor.name}
                                </Link>
                              </DropdownMenuItem>
                            ))}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      )}
                    </div>
                  </>
                ) : (
                  <div className="flex items-center gap-3 xs:gap-2">
                    <Avatar className="size-10 rounded-full xs:size-8 flex-shrink-0">
                      <AvatarFallback>
                        <UserRoundIcon
                          size={16}
                          className="opacity-60 xs:size-3"
                          aria-hidden="true"
                        />
                      </AvatarFallback>
                    </Avatar>
                    <span className="font-semibold text-sm sm:text-xl capitalize text-foreground whitespace-nowrap">
                      {t("video_player.unknown_actor")}
                    </span>
                  </div>
                )}
              </div>

              <VideoActionButtons
                className="flex-shrink-0"
                videoContent={state.videoContent}
                currentCollectState={state.currentCollectState}
                isCollecting={state.isCollecting}
                onCollect={state.handleCollect}
                onVoucherClick={handleVoucherRequest}
              />
            </div>

            {/* Video Details Section */}
            <div className="flex pt-4 flex-col gap-3 text-muted-foreground">
              {/* Thumbnail Gallery Section */}
              <div>
                {state.videoContent?.thumb &&
                state.videoContent?.thumb_series ? (
                  <ThumbnailGallery
                    thumb={state.videoContent.thumb}
                    thumbSeries={state.videoContent.thumb_series}
                  />
                ) : null}
              </div>

              <div>
                <p className="text-md sm:text-lg font-medium">
                  <span className="text-foreground mr-4">
                    {t("video_player.publisher")}
                  </span>
                  {state.videoContent?.publisher?.id ? (
                    <Link
                      to={`/publisher/${state.videoContent.publisher.id}`}
                      className="text-primary underline underline-offset-2"
                    >
                      {state.videoContent.publisher?.name ||
                        t("video_player.unknown_publisher")}
                    </Link>
                  ) : (
                    <span className="text-muted-foreground/50">
                      {t("video_player.unknown_publisher")}
                    </span>
                  )}
                </p>
                <p className="text-md sm:text-lg font-medium mt-2">
                  <span className="text-foreground mr-4">
                    {t("video_player.serial_number")}
                  </span>
                  <span className="text-muted-foreground/50">
                    {state.videoContent?.mash || "-"}
                  </span>
                </p>
                <p className="text-md sm:text-lg font-medium mt-2">
                  <span className="text-foreground mr-4">
                    {t("video_player.director")}
                  </span>
                  <span className="text-muted-foreground/50">-</span>
                </p>
                {state.videoContent?.tags &&
                state.videoContent.tags.length > 0 ? (
                  <TagsCarousel tags={state.videoContent.tags} />
                ) : (
                  <span className="text-muted-foreground/50 mt-4 block">
                    {t("video_player.no_tags")}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Info Card Section */}
          <div id="info-card" className="px-4 md:px-4 sm:px-4 xs:px-2">
            <InfoCard
              sections={[
                {
                  title: "Aijav 最新国内地址",
                  links: [
                    {
                      label: "https://aij-av.com/",
                      url: "https://aij-av.com/",
                    },
                  ],
                },
                {
                  title: "Aijav 永久地址(需翻墙)",
                  links: [
                    {
                      label: "https://aijav.com/",
                      url: "https://aijav.com/",
                    },
                    {
                      label: "https://aijav.cc/",
                      url: "https://aijav.cc/",
                    },
                    {
                      label: "https://aijav.vip/",
                      url: "https://aijav.vip/",
                    },
                    {
                      label: "https://aijav.top/",
                      url: "https://aijav.top/",
                    },
                  ],
                },
                {
                  title: "推荐使用Edge/chrome/safari浏览器访问网站",
                  content: "",
                },
                {
                  title: "Telegram",
                  links: [
                    {
                      label: "@japanav",
                      url: "https://t.me/japanav",
                    },
                  ],
                },
              ]}
              useRandomImage={true}
            />
          </div>

          <Separator className="my-6 h-4" />

          {/* Collapsible Comment Section */}
          <div className="px-4 md:px-4 sm:px-4 xs:px-2">
            <CollapsibleComments
              videoId={id}
              expanded={state.commentsExpanded}
              onToggle={state.handleToggleComments}
            />
          </div>
        </div>

        <div className="flex flex-col gap-2 w-full xl:w-auto xl:min-w-[400px] xl:max-w-[400px] mt-6 xl:mt-0 xl:overflow-y-auto xl:h-full px-4 sm:px-0 pb-4 sm:pb-0 scroll-smooth [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none]">
          <VIPAdsBlock />
          {sidebarComponent}
        </div>
      </div>

      {/* Voucher Dialog */}
      {hasValidVideoId && (
        <VoucherDialog
          open={voucherDialogOpen}
          onClose={() => setVoucherDialogOpen(false)}
          voucherCost={voucherCost}
          vid={videoIdNumeric}
        />
      )}
    </div>
  );
}
