import { useEffect, useState } from "react";
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar.tsx";
import actor2 from "../assets/samples/actor/actor2.png";
import actor3 from "../assets/samples/actor/actor3.png";
import actor4 from "../assets/samples/actor/actor4.png";
import actor5 from "../assets/samples/actor/actor5.png";
import headIcon from "../assets/samples/actor/head-icon.png";
import bodyIcon from "../assets/samples/actor/body-icon.png";
import { Button } from "@/components/ui/button.tsx";
import {
  ChevronDown,
  ChevronUp,
  Film,
  type LucideIcon,
  PlayCircle,
  Share2,
  TrendingUp,
  UserPlus,
  VideoIcon,
} from "lucide-react";
import { Badge } from "@/components/ui/badge.tsx";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs.tsx";
import { Link, useParams } from "react-router";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area.tsx";
import { useInView } from "react-intersection-observer";
import useEmblaCarousel from "embla-carousel-react";
import { useActorInfo } from "@/hooks/actor/useActorInfo.ts";
import {
  useInfiniteActorVideoList,
  useInfiniteActorSeriesList,
} from "@/hooks/actor/useActorVideoList.ts";
import { useSubscribeToActor } from "@/hooks/actor/useSubscribeActor.ts";
import { cn } from "@/lib/utils.ts";
import type { Video } from "@/types/video.types.ts";
import { PageError } from "@/components/error-states";
import { EntityNotFound } from "@/components/entity-not-found.tsx";
import { ActressInfoSkeleton } from "@/components/skeletons/ActressInfoSkeleton.tsx";
import { useTranslation } from "react-i18next";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { EndOfContent } from "@/components/EndOfContent";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { useWindowSize } from "usehooks-ts";
import { Base64Image } from "@/components/Base64Image";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Separator } from "@/components/ui/separator.tsx";
import { RecommendedHorizontalList } from "@/components/recommended-horizontal-list.tsx";
import { MasonryGallery } from "@/components/MasonryGallery";
import { SocialMediaLinks } from "@/components/SocialMediaLinks";

export default function ActressInfo() {
  const { t } = useTranslation();
  const { id = "" } = useParams();
  const { width } = useWindowSize();
  const [isBodyDataOpen, setIsBodyDataOpen] = useState(false);

  const { isPending, data: actressInfo, isError } = useActorInfo(id);

  const { mutate: subscribeToActor } = useSubscribeToActor();
  const { executeWithAuth } = useAuthAction();

  // Check if we're on desktop (md breakpoint: 768px and above)
  const isDesktop = width >= 768;

  // Enhanced loading and error handling
  if (isPending) return <ActressInfoSkeleton />;
  if (isError) return <PageError />;

  // Handle business logic errors (API success but with error codes)
  if (actressInfo?.code !== 1) {
    return (
      <EntityNotFound
        entityType="actress"
        message={actressInfo?.msg || t("actress.not_found")}
        errorCode={actressInfo?.code}
      />
    );
  }

  // Ensure data exists before proceeding
  if (!actressInfo?.data) {
    return (
      <EntityNotFound
        entityType="actress"
        message={t("actress.incomplete_data")}
      />
    );
  }

  const actorData = actressInfo.data;
  const formattedBodyMeasurement = `B${actorData.bust}/W${actorData.waist}/H${actorData.hip}`;
  const backgroundImage =
    actorData.background_image || "/banner_fallback_bg.png";

  const handleSubscribe = (actorId: string) => {
    executeWithAuth(() => {
      subscribeToActor({
        aid: actorId,
      });
    });
  };

  // Build social media links from API data, only including those with values
  const socialLinks = [
    ...(actorData.fantia
      ? [
          {
            platform: "fantia" as const,
            url: actorData.fantia,
            label: "Fantia",
          },
        ]
      : []),
    ...(actorData.twitter
      ? [
          {
            platform: "twitter" as const,
            url: actorData.twitter,
            label: "Twitter",
          },
        ]
      : []),
    ...(actorData.tiktok
      ? [
          {
            platform: "tiktok" as const,
            url: actorData.tiktok,
            label: "TikTok",
          },
        ]
      : []),
    ...(actorData.instagram
      ? [
          {
            platform: "instagram" as const,
            url: actorData.instagram,
            label: "Instagram",
          },
        ]
      : []),
    ...(actorData.onlyfans
      ? [
          {
            platform: "onlyfans" as const,
            url: actorData.onlyfans,
            label: "OnlyFans",
          },
        ]
      : []),
    ...(actorData.facebook
      ? [
          {
            platform: "facebook" as const,
            url: actorData.facebook,
            label: "Facebook",
          },
        ]
      : []),
    ...(actorData.youtube
      ? [
          {
            platform: "youtube" as const,
            url: actorData.youtube,
            label: "YouTube",
          },
        ]
      : []),
  ];

  return (
    <>
      <div className="p-4 md:p-0">
        {/* Banner */}
        <div
          style={{ backgroundImage: `url(${backgroundImage})` }}
          className="relative h-[100px] md:h-[180px] rounded-xl md:rounded-t-none md:rounded-b-2xl overflow-hidden bg-gray-500"
        >
          {/* Dark overlay */}
          <div className="absolute inset-0 bg-black/40" />

          {/* Content overlay */}
          <div className="absolute inset-0 flex flex-col gap-2 items-center justify-center text-white p-4">
            <h2 className="text-2xl md:text-4xl font-bold text-center leading-tight">
              {actorData.name}
            </h2>
            <span className="text-sm md:text-xl text-center">
              {actorData.subscribe_count} {t("actress.followers")} •{" "}
              {actorData.video_count} {t("actress.works")}
            </span>
          </div>
        </div>

        {/* Collapsible wrapper for mobile body data */}
        <Collapsible
          open={isDesktop || isBodyDataOpen}
          onOpenChange={setIsBodyDataOpen}
        >
          {/* Actor Bio */}
          <div className="py-4 md:p-4 flex items-center gap-4 flex-nowrap flex-row">
            <Avatar className="ring-white md:-mt-20 rounded-full ring-6 size-[96px] md:size-[150px] bg-gray-400">
              <Base64Image
                originalUrl={actorData.image}
                alt={`${actorData.name} profile picture`}
                className="object-cover w-full h-full rounded-full"
              />
            </Avatar>
            <div className="flex items-start gap-4 w-full flex-wrap flex-col md:flex-row">
              <div className="flex flex-col gap-2 justify-between w-full md:w-auto">
                <p className="font-bold text-xl md:text-3xl leading-none">
                  {actorData.name}
                </p>
                <span className="text-[#757575] text-xs sm:text-sm font-medium">
                  {actorData.subscribe_count} {t("actress.followers")}
                </span>

                {/* actor avatar with collapsible trigger - Mobile Only */}
                <div className="flex md:hidden items-center justify-between w-full">
                  <div className="*:data-[slot=avatar]:ring-background flex -space-x-3.5 *:data-[slot=avatar]:ring-2">
                    <Avatar>
                      <AvatarImage src={actor2} alt="actress image" />
                      <AvatarFallback>{actorData.name}</AvatarFallback>
                    </Avatar>
                    <Avatar>
                      <AvatarImage src={actor3} alt="actress image" />
                      <AvatarFallback>{actorData.name}</AvatarFallback>
                    </Avatar>
                    <Avatar>
                      <AvatarImage src={actor4} alt="actress image" />
                      <AvatarFallback>{actorData.name}</AvatarFallback>
                    </Avatar>
                    <Avatar>
                      <AvatarImage src={actor5} alt="actress image" />
                      <AvatarFallback>{actorData.name}</AvatarFallback>
                    </Avatar>
                  </div>

                  <CollapsibleTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 p-0 hover:bg-accent"
                    >
                      {isBodyDataOpen ? (
                        <ChevronUp className="size-6" />
                      ) : (
                        <ChevronDown className="size-6" />
                      )}
                    </Button>
                  </CollapsibleTrigger>
                </div>

                {/* actor avatar - Desktop Only */}
                <div className="hidden md:flex *:data-[slot=avatar]:ring-background -space-x-1 *:data-[slot=avatar]:ring-2">
                  <Avatar>
                    <AvatarImage src={actor2} alt="actress image" />
                    <AvatarFallback>{actorData.name}</AvatarFallback>
                  </Avatar>
                  <Avatar>
                    <AvatarImage src={actor3} alt="actress image" />
                    <AvatarFallback>{actorData.name}</AvatarFallback>
                  </Avatar>
                  <Avatar>
                    <AvatarImage src={actor4} alt="actress image" />
                    <AvatarFallback>{actorData.name}</AvatarFallback>
                  </Avatar>
                  <Avatar>
                    <AvatarImage src={actor5} alt="actress image" />
                    <AvatarFallback>{actorData.name}</AvatarFallback>
                  </Avatar>
                </div>

                {/* subscribe and share button - Desktop Only */}
                <div className="hidden md:flex gap-2 mt-2 w-full">
                  <Button
                    className={cn(
                      "flex-[4] rounded-full has-[>svg]:px-4",
                      actorData.is_subscribe === 1 &&
                        "bg-[#781938] hover:bg-[#781938]/90",
                    )}
                    size="sm"
                    onClick={() => handleSubscribe(id)}
                  >
                    {actorData.is_subscribe === 1 ? null : <UserPlus />}{" "}
                    {actorData.is_subscribe === 1
                      ? t("actress.following")
                      : t("actress.follow")}
                  </Button>
                  <Button
                    className="flex-1 rounded-full bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF] has-[>svg]:px-4"
                    variant="outline"
                    size="sm"
                  >
                    <Share2 /> {t("actress.share")}
                  </Button>
                </div>

                {/* Social Media Links - Desktop (beside buttons) */}
                {socialLinks.length > 0 && (
                  <div className="hidden md:flex md:mt-2">
                    <SocialMediaLinks links={socialLinks} iconSize={18} />
                  </div>
                )}
              </div>
              {/* Body Data - Desktop View Only */}
              <div className="hidden md:flex flex-col gap-4 text-end flex-1 w-full">
                <div>
                  <div className="md:justify-end justify-start flex items-center space-x-2">
                    <span>{t("actress.personal_profile")}</span>
                    <img
                      loading="lazy"
                      src={headIcon}
                      className="size-6"
                      alt="head icon"
                    />
                  </div>
                  <BadgeCarousel
                    isDesktop={isDesktop}
                    badges={[
                      `${t("actress.birth")} ${actorData.birthday ?? "-"}`,
                      `${t("actress.debut")} ${actorData.debut ?? "-"}`,
                      `${t("actress.constellation")} ${actorData.constellation ?? "-"}`,
                      // `${t("actress.blood_type")} ${actorData.blood_type ?? "-"}`,
                      `${t("actress.nationality")} ${actorData.nationality ?? "-"}`,
                    ]}
                  />
                </div>
                <div>
                  <div className="md:justify-end justify-start flex items-center space-x-2">
                    <span>{t("actress.body_data")}</span>
                    <img
                      loading="lazy"
                      src={bodyIcon}
                      className="size-6"
                      alt="head icon"
                    />
                  </div>
                  <BadgeCarousel
                    isDesktop={isDesktop}
                    badges={[
                      `${t("actress.measurements")} ${formattedBodyMeasurement}`,
                      `${t("actress.cup_size")} ${actorData.bra_size ?? "-"}`,
                      `${t("actress.height")} ${actorData.height ?? "-"}`,
                      `${t("actress.blood_type")} ${actorData.blood_type ?? "-"}`,
                    ]}
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Body Data Collapsible Content - Mobile Only */}
          <CollapsibleContent className="md:hidden space-y-4 mb-6 w-full">
            <div>
              <div className="flex items-center space-x-2 justify-start mb-2">
                <span className="text-sm">{t("actress.personal_profile")}</span>
                <img
                  loading="lazy"
                  src={headIcon}
                  className="size-5"
                  alt="head icon"
                />
              </div>
              <BadgeCarousel
                isDesktop={false}
                badges={[
                  `${t("actress.birth")} ${actorData.birthday ?? "-"}`,
                  `${t("actress.debut")} ${actorData.debut ?? "-"}`,
                  `${t("actress.constellation")} ${actorData.constellation ?? "-"}`,
                  `${t("actress.blood_type")} ${actorData.blood_type ?? "-"}`,
                  `${t("actress.nationality")} ${actorData.nationality ?? "-"}`,
                ]}
              />
            </div>
            <div>
              <div className="flex items-center space-x-2 justify-start mb-2">
                <span className="text-sm">{t("actress.body_data")}</span>
                <img
                  loading="lazy"
                  src={bodyIcon}
                  className="size-5"
                  alt="head icon"
                />
              </div>
              <BadgeCarousel
                isDesktop={false}
                badges={[
                  `${t("actress.measurements")} ${formattedBodyMeasurement}`,
                  `${t("actress.cup_size")} ${actorData.bra_size ?? "-"}`,
                  `${t("actress.height")} ${actorData.height ?? "-"}`,
                  `${t("actress.blood_type")} ${actorData.blood_type ?? "-"}`,
                ]}
              />
            </div>
          </CollapsibleContent>
        </Collapsible>

        {/* subscribe and share button - Mobile Only (below profile) */}
        <div className="md:px-4 flex md:hidden gap-2 w-full">
          <Button
            className={cn(
              "flex-[4] rounded-full has-[>svg]:px-4",
              actorData.is_subscribe === 1 &&
                "bg-[#781938] hover:bg-[#781938]/90",
            )}
            size="sm"
            onClick={() => handleSubscribe(id)}
          >
            {actorData.is_subscribe === 1 ? null : <UserPlus />}{" "}
            {actorData.is_subscribe === 1
              ? t("actress.following")
              : t("actress.follow")}
          </Button>
          <Button
            className="flex-1 rounded-full bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF] has-[>svg]:px-4"
            variant="outline"
            size="sm"
          >
            <Share2 /> {t("actress.share")}
          </Button>
        </div>

        {/* Social Media Links - Mobile Only */}
        {socialLinks.length > 0 && (
          <div className="md:hidden mt-4">
            <SocialMediaLinks links={socialLinks} iconSize={18} />
          </div>
        )}
        <Separator className="block md:hidden mt-4" />

        {/* Actor Video List */}
        <div className="md:px-4">
          <Tabs defaultValue="tab-1" className="w-full">
            <ScrollArea className="md:-mx-4 my-3">
              <TabsList className="text-foreground justify-start w-full h-auto gap-2 rounded-none md:border-y border-x-0 shadow bg-transparent px-0 py-1">
                <TabsTrigger
                  value="tab-1"
                  className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"
                >
                  {t("actress.latest_videos")}
                </TabsTrigger>
                {/*<TabsTrigger*/}
                {/*  value="tab-2"*/}
                {/*  className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"*/}
                {/*>*/}
                {/*  {t("actress.hot_videos")}*/}
                {/*</TabsTrigger>*/}
                {/*<TabsTrigger*/}
                {/*  value="tab-3"*/}
                {/*  className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"*/}
                {/*>*/}
                {/*  {t("tabs.trending_videos")}*/}
                {/*</TabsTrigger>*/}
                <TabsTrigger
                  value="tab-4"
                  className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"
                >
                  {t("actress.series")}
                </TabsTrigger>
                {actorData.photos && actorData.photos.length > 0 && (
                  <TabsTrigger
                    value="tab-5"
                    className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"
                  >
                    {t("tabs.photo_collection")}
                  </TabsTrigger>
                )}
              </TabsList>
              <ScrollBar orientation="horizontal" />
            </ScrollArea>
            <TabsContent value="tab-1">
              <TabContentWithLoader
                actorId={id}
                emptyIcon={VideoIcon}
                emptyTitle={t("tabs.no_videos_found")}
                emptyDescription={t("tabs.no_videos_description")}
              />
            </TabsContent>
            <TabsContent value="tab-2">
              <EmptyState
                icon={TrendingUp}
                title={t("tabs.no_videos_found")}
                description={t("tabs.no_videos_description")}
                size="sm"
                className="py-8"
              />
            </TabsContent>
            <TabsContent value="tab-3">
              <EmptyState
                icon={PlayCircle}
                title={t("tabs.no_videos_found")}
                description={t("tabs.no_videos_description")}
                size="sm"
                className="py-8"
              />
            </TabsContent>
            <TabsContent value="tab-4">
              <TabContentWithLoaderSeries
                actorId={id}
                emptyIcon={Film}
                emptyTitle={t("tabs.no_series_found")}
                emptyDescription={t("tabs.no_series_description")}
              />
            </TabsContent>
            {actorData.photos && actorData.photos.length > 0 && (
              <TabsContent value="tab-5">
                <MasonryGallery
                  photos={actressInfo.data.photos}
                  isLoading={isPending}
                />
              </TabsContent>
            )}
          </Tabs>
        </div>

        <div className="py-4 md:px-4">
          <RecommendedHorizontalList />
        </div>
      </div>
    </>
  );
}

// Reusable component for tab content with infinite loading
type TabContentWithLoaderProps = {
  actorId: string;
  emptyIcon: LucideIcon;
  emptyTitle: string;
  emptyDescription: string;
};

const TabContentWithLoader = ({
  actorId,
  emptyIcon: EmptyIcon,
  emptyTitle,
  emptyDescription,
}: TabContentWithLoaderProps) => {
  const { t } = useTranslation();
  const { width } = useWindowSize();

  // Use infinite query for video list
  const {
    data: infiniteData,
    isPending,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteActorVideoList(actorId);

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

  // Check if we're on desktop (lg breakpoint: 1024px and above)
  const isDesktop = width >= 1024;

  if (isPending) {
    return (
      <div className="my-6 grid lg:grid-cols-4 grid-cols-1 gap-2.5">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="relative bg-white w-full h-full rounded-lg overflow-hidden"
          >
            <Skeleton className="aspect-video rounded-lg w-full" />
            <div className="pt-1.5 px-1.5 flex justify-between items-center">
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-3 w-8" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        icon={EmptyIcon}
        title={t("common.error")}
        description={t("common.error_loading")}
        size="sm"
        className="py-8"
      />
    );
  }

  if (!allVideos?.length) {
    return (
      <EmptyState
        icon={EmptyIcon}
        title={emptyTitle}
        description={emptyDescription}
        size="sm"
        className="py-8"
      />
    );
  }

  return (
    <div className="">
      <div className="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-2.5">
        {allVideos?.map((video: Video) => (
          <EnhancedVideoCard
            key={video.id}
            video={video}
            layout={isDesktop ? "vertical-large" : "horizontal-compact"}
            linkState={{
              from: "actress",
              categoryName: t("actress.latest_videos"),
            }}
            size="md"
            showBadges={true}
            showRating={!isDesktop}
            showActor={false}
            className="w-full h-full"
          />
        ))}
      </div>

      {/* Sentinel element for infinite scroll */}
      {hasNextPage && (
        <div ref={inViewRef} className="h-16 flex items-center justify-center">
          {isFetchingNextPage && (
            <span className="text-muted-foreground">{t("common.loading")}</span>
          )}
        </div>
      )}

      {/* End of content indicator */}
      {!hasNextPage && <EndOfContent />}
    </div>
  );
};

// Badge Carousel Component
type BadgeCarouselProps = {
  isDesktop: boolean;
  badges: string[];
};

const TabContentWithLoaderSeries = (props: TabContentWithLoaderProps) => {
  const { t } = useTranslation();

  // Use infinite query for series list
  const {
    data: infiniteData,
    isPending,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteActorSeriesList(props.actorId);

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

  if (isPending) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="flex items-center justify-between p-3 rounded-lg bg-[#F4A8FF]/5 border border-[#EC67FF]/10"
          >
            <div className="flex-1 min-w-0 space-y-2">
              <Skeleton className="h-3 w-1/3" />
              <Skeleton className="h-4 w-2/3" />
            </div>
            <Skeleton className="h-3 w-24 ml-3 flex-shrink-0" />
          </div>
        ))}
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        icon={props.emptyIcon}
        title={t("common.error")}
        description={t("common.error_loading")}
        size="sm"
        className="py-8"
      />
    );
  }

  if (!allVideos?.length) {
    return (
      <EmptyState
        icon={props.emptyIcon}
        title={props.emptyTitle}
        description={props.emptyDescription}
        size="sm"
        className="py-8"
      />
    );
  }

  return (
    <div className="space-y-2">
      {allVideos?.map((video: Video) => (
        <Link
          key={video.id}
          to={`/watch/${video.id}`}
          state={{
            from: "actress",
            categoryName: t("actress.series"),
          }}
          className="flex items-center gap-3 p-3 rounded-lg bg-[#F4A8FF]/5 border border-[#EC67FF]/10 hover:bg-[#F4A8FF]/10 hover:border-[#EC67FF]/30 transition-colors duration-200 group"
        >
          <div className="flex-1 min-w-0">
            <p className="text-xs text-[#757575] mb-1">
              {video.mash || t("common.unknown")}
            </p>
            <h3 className="text-sm font-semibold text-foreground truncate group-hover:text-[#EC67FF] transition-colors">
              {video.title}
            </h3>
          </div>
          <div className="text-xs text-[#757575] ml-3 flex-shrink-0">
            {video.publish_date || "-"}
          </div>
        </Link>
      ))}

      {/* Sentinel element for infinite scroll */}
      {hasNextPage && (
        <div ref={inViewRef} className="h-16 flex items-center justify-center">
          {isFetchingNextPage && (
            <span className="text-muted-foreground text-sm">
              {t("common.loading")}
            </span>
          )}
        </div>
      )}

      {/* End of content indicator */}
      {!hasNextPage && <EndOfContent />}
    </div>
  );
};

const BadgeCarousel = ({ isDesktop, badges }: BadgeCarouselProps) => {
  // Embla carousel setup - only enable on mobile
  const [emblaRef] = useEmblaCarousel(
    isDesktop
      ? { watchDrag: false, watchResize: false, watchSlides: false }
      : {
          align: "start",
          containScroll: "trimSnaps",
          dragFree: true,
          slidesToScroll: "auto",
          duration: 20,
          skipSnaps: true,
        },
  );

  if (isDesktop) {
    // Desktop: regular flex layout with proper alignment
    return (
      <div className="mt-3 flex md:justify-end justify-start gap-2">
        {badges.map((badge, index) => (
          <Badge
            key={index}
            className="rounded-full text-sm text-[#424242] bg-[#FFE5D3] whitespace-nowrap"
          >
            {badge}
          </Badge>
        ))}
      </div>
    );
  }

  // Mobile: Embla carousel
  return (
    <div className="embla mt-3 w-full overflow-hidden">
      <div ref={emblaRef} className="embla__viewport">
        <div className="embla__container flex touch-pan-y">
          {badges.map((badge, index) => (
            <div
              key={index}
              className="embla__slide flex-[0_0_auto] min-w-0 mr-2"
            >
              <Badge className="rounded-full text-sm text-[#424242] bg-[#FFE5D3] whitespace-nowrap">
                {badge}
              </Badge>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
