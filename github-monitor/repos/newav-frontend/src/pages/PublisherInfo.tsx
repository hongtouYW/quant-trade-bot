import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar.tsx";
import { Button } from "@/components/ui/button.tsx";
import {
  type LucideIcon,
  PlayCircle,
  Share2,
  TrendingUp,
  UserPlus,
  UserRoundIcon,
  Users,
  VideoIcon,
} from "lucide-react";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs.tsx";
import { useNavigate, useParams } from "react-router";
import { useInView } from "react-intersection-observer";
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area.tsx";
import { useInfinitePublisherVideoList } from "@/hooks/actor/useActorVideoList.ts";
import { usePublisherInfo } from "@/hooks/publisher/usePublisherInfo.ts";
import { useEffect } from "react";
import { useSubscribeToPublisher } from "@/hooks/publisher/useSubscribePublisher.ts";
import { useSubscribeToActor } from "@/hooks/actor/useSubscribeActor.ts";
import { useInfiniteActorListByPublisher } from "@/hooks/actor/useActorListByPublisher.ts";
import type { Video } from "@/types/video.types.ts";
import { PageError } from "@/components/error-states";
import { EntityNotFound } from "@/components/entity-not-found.tsx";
import { PublisherInfoSkeleton } from "@/components/skeletons/PublisherInfoSkeleton.tsx";
import { useTranslation } from "react-i18next";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { EndOfContent } from "@/components/EndOfContent";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { useWindowSize } from "usehooks-ts";
import { ProfileCard } from "@/components/profile-card";
import { useInfinitePublisherGroupList } from "@/hooks/group/useGroupList.ts";
import { PlaylistCard } from "@/components/playlist-card";
import { cn } from "@/lib/utils.ts";
import { Separator } from "@/components/ui/separator.tsx";

export default function PublisherInfo() {
  const { t } = useTranslation();
  const { id = "" } = useParams();

  const { data: publisherInfo, isPending, isError } = usePublisherInfo(id);

  const { mutate: subscribeToPublisher } = useSubscribeToPublisher();
  const { executeWithAuth } = useAuthAction();

  // Enhanced loading and error handling
  if (isPending) return <PublisherInfoSkeleton />;
  if (isError) return <PageError />;

  // Handle business logic errors (API success but with error codes)
  if (publisherInfo?.code !== 1) {
    return (
      <EntityNotFound
        entityType="publisher"
        message={publisherInfo?.msg || t("publisher.not_found")}
        errorCode={publisherInfo?.code}
      />
    );
  }

  // Ensure data exists before proceeding
  if (!publisherInfo?.data) {
    return (
      <EntityNotFound
        entityType="publisher"
        message={t("publisher.incomplete_data")}
      />
    );
  }

  const publisherData = publisherInfo.data;
  const backgroundImage = publisherData.image || "/banner_fallback_bg.png";
  const isFallbackImage = !publisherData.image;

  const handleSubscribe = (publisherId: string) => {
    executeWithAuth(() => {
      subscribeToPublisher({
        pid: publisherId,
      });
    });
  };

  return (
    <div className="p-4 md:p-0">
      {/* Banner */}
      <div className="relative h-[100px] md:h-[180px] rounded-xl md:rounded-t-none md:rounded-b-2xl overflow-hidden">
        <img
          loading="lazy"
          src={backgroundImage}
          className={cn(
            "h-full w-full",
            isFallbackImage ? "object-cover" : "object-cover",
          )}
          alt=""
        />
        {/* Dark overlay */}
        <div className="absolute inset-0 bg-black/40" />

        {/* Content overlay */}
        <div className="absolute inset-0 flex flex-col gap-2 items-center justify-center text-white">
          <h2 className="text-2xl md:text-4xl font-bold text-center leading-tight">
            {publisherData.name}
          </h2>
          <span className="text-sm md:text-xl text-center">
            {publisherData.subscribe_count} {t("publisher.followers_count")} •{" "}
            {publisherData.total_video} {t("publisher.total_videos")}
          </span>
        </div>
      </div>

      {/* Actor Bio */}
      <div className="py-4 md:p-4 flex items-start gap-4 flex-nowrap flex-row">
        <Avatar className="ring-white md:-mt-20 rounded-full ring-6 size-[96px] md:size-[150px] bg-white">
          <AvatarImage
            className="object-contain"
            src={publisherData.image}
            alt={publisherData.name}
          />
          <AvatarFallback>
            <UserRoundIcon
              size={16}
              className="opacity-60"
              aria-hidden="true"
            />
          </AvatarFallback>
        </Avatar>
        <div className="flex items-start gap-4 py-2 md:py-0 w-full flex-wrap flex-col md:flex-row">
          <div className="flex flex-col gap-2 justify-between w-full md:w-auto">
            <p className="font-bold text-xl md:text-3xl leading-none">
              {publisherData.name}
            </p>
            <span className="text-[#757575] text-xs sm:text-sm font-medium">
              {publisherData.subscribe_count} {t("publisher.followers")}
            </span>
            {/*<div className="*:data-[slot=avatar]:ring-background flex -space-x-1 *:data-[slot=avatar]:ring-2">*/}
            {/*  <Avatar>*/}
            {/*    <AvatarImage src={actor2} alt="actress image" />*/}
            {/*    <AvatarFallback>{actressInfo.name}</AvatarFallback>*/}
            {/*  </Avatar>*/}
            {/*  <Avatar>*/}
            {/*    <AvatarImage src={actor3} alt="actress image" />*/}
            {/*    <AvatarFallback>{actressInfo.name}</AvatarFallback>*/}
            {/*  </Avatar>*/}
            {/*  <Avatar>*/}
            {/*    <AvatarImage src={actor4} alt="actress image" />*/}
            {/*    <AvatarFallback>{actressInfo.name}</AvatarFallback>*/}
            {/*  </Avatar>*/}
            {/*  <Avatar>*/}
            {/*    <AvatarImage src={actor5} alt="actress image" />*/}
            {/*    <AvatarFallback>{actressInfo.name}</AvatarFallback>*/}
            {/*  </Avatar>*/}
            {/*</div>*/}

            {/* subscribe and share button - Desktop Only */}
            <div className="hidden md:flex gap-2 mt-2 w-full">
              <Button
                className={cn(
                  "flex-[4] rounded-full has-[>svg]:px-4",
                  publisherData.is_subscribe === 1 &&
                    "bg-[#781938] hover:bg-[#781938]/90",
                )}
                size="sm"
                onClick={() => handleSubscribe(id)}
              >
                {publisherData.is_subscribe === 1 ? null : <UserPlus />}{" "}
                {publisherData.is_subscribe === 1
                  ? t("publisher.subscribed")
                  : t("publisher.subscribe")}
              </Button>
              <Button
                className="flex-1 rounded-full bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF] has-[>svg]:px-4"
                variant="outline"
                size="sm"
              >
                <Share2 /> {t("publisher.share")}
              </Button>
            </div>
          </div>
        </div>
      </div>

      {/* subscribe and share button - Mobile Only (below profile) */}
      <div className="md:px-4 flex md:hidden gap-2 w-full">
        <Button
          className={cn(
            "flex-[4] rounded-full has-[>svg]:px-4",
            publisherData.is_subscribe === 1 &&
              "bg-[#781938] hover:bg-[#781938]/90",
          )}
          size="sm"
          onClick={() => handleSubscribe(id)}
        >
          {publisherData.is_subscribe === 1 ? null : <UserPlus />}{" "}
          {publisherData.is_subscribe === 1
            ? t("publisher.subscribed")
            : t("publisher.subscribe")}
        </Button>
        <Button
          className="flex-1 rounded-full bg-[#F4A8FF]/20 text-[#EC67FF] border-[#EC67FF] hover:text-[#EC67FF] has-[>svg]:px-4"
          variant="outline"
          size="sm"
        >
          <Share2 /> {t("publisher.share")}
        </Button>
      </div>

      <Separator className="block md:hidden mt-6" />

      {/* Actor Video List */}
      <div className="md:px-4">
        <Tabs defaultValue="tab-1" className="w-full">
          <ScrollArea className="md:-mx-4 my-3">
            <TabsList className="text-foreground justify-start w-full h-auto gap-2 rounded-none md:border-y border-x-0 shadow bg-transparent px-0 py-1">
              <TabsTrigger
                value="tab-1"
                className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"
              >
                {t("publisher.latest_videos")}
              </TabsTrigger>
              <TabsTrigger
                value="tab-2"
                className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"
              >
                {t("publisher.series_list")}
              </TabsTrigger>
              {/*<TabsTrigger*/}
              {/*  value="tab-3"*/}
              {/*  className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"*/}
              {/*>*/}
              {/*  推薦系列*/}
              {/*</TabsTrigger>*/}
              <TabsTrigger
                value="tab-4"
                className="text-[#616161] px-4 md:px-8 hover:bg-accent hover:text-foreground rounded-full data-[state=active]:bg-primary data-[state=active]:text-white data-[state=active]:hover:bg-primary/90 data-[state=active]:shadow-none md:rounded-none md:data-[state=active]:bg-transparent md:data-[state=active]:text-primary md:data-[state=active]:hover:bg-accent md:relative md:after:absolute md:after:inset-x-0 md:after:bottom-0 md:after:-mb-1 md:after:h-0.5 md:data-[state=active]:after:bg-primary whitespace-nowrap"
              >
                {t("publisher.exclusive_actresses")}
              </TabsTrigger>
            </TabsList>
            <ScrollBar orientation="horizontal" />
          </ScrollArea>
          <TabsContent value="tab-1">
            <TabContentWithLoader
              publisherId={id}
              emptyIcon={VideoIcon}
              emptyTitle={t("tabs.no_videos_found")}
              emptyDescription={t("tabs.no_videos_description")}
            />
          </TabsContent>
          <TabsContent value="tab-2">
            <PublisherGroupList publisherId={id} />
          </TabsContent>
          <TabsContent value="tab-3">
            <EmptyState
              icon={PlayCircle}
              title={t("tabs.no_series_found")}
              description={t("tabs.no_series_description")}
              size="sm"
              className="py-8"
            />
          </TabsContent>
          <TabsContent value="tab-4">
            <ActorListByPublisher publisherId={id} />
          </TabsContent>
        </Tabs>
      </div>
    </div>
  );
}

type ActorListByPublisherProps = {
  publisherId: string;
};

// Reusable component for tab content with infinite loading
type TabContentWithLoaderProps = {
  publisherId: string;
  emptyIcon: LucideIcon;
  emptyTitle: string;
  emptyDescription: string;
};

const TabContentWithLoader = ({
  publisherId,
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
  } = useInfinitePublisherVideoList(publisherId);

  // Flatten all pages of videos
  const allVideos =
    infiniteData?.pages?.flatMap((page) => page.data?.data) ?? [];

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

  // Check if we're on desktop (lg breakpoint: 1024px and above)
  const isDesktop = width >= 1024;

  if (isPending) {
    return (
      <div className="my-6 grid lg:grid-cols-4 grid-cols-2 gap-2.5">
        {Array.from({ length: 8 }).map((_, i) => (
          <div
            key={i}
            className="relative bg-white w-full h-full rounded-lg overflow-hidden"
          >
            <Skeleton className="aspect-video rounded-lg w-full" />
            <div className="pt-1.5 px-1.5 flex justify-between items-center">
              <Skeleton className="h-4 w-3/4" />
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
              from: "publisher",
              categoryName: t("publisher.latest_videos"),
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
        <div
          ref={inViewRef}
          className="h-16 flex items-center justify-center mt-6"
        >
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

const ActorListByPublisher = ({ publisherId }: ActorListByPublisherProps) => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const {
    data: infiniteData,
    isPending: isLoading,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteActorListByPublisher({
    pid: Number(publisherId),
  });
  const { mutate: subscribeToActor } = useSubscribeToActor();
  const { executeWithAuth } = useAuthAction();

  // Flatten all pages of actors
  const allActors =
    infiniteData?.pages?.flatMap((page) => page.data?.data) ?? [];

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

  const handleSubscribeToActor = (item: any) => {
    executeWithAuth(() => {
      subscribeToActor({
        aid: String(item.id),
      });
    });
  };

  const handleActorClick = (item: any) => {
    navigate(`/actress/${item.id}`, {
      state: {
        from: "publisher",
        categoryName: t("publisher.exclusive_actresses"),
      },
    });
  };

  if (isLoading) {
    return (
      <div className="grid grid-cols-3 xl:grid-cols-10 gap-2">
        {Array.from({ length: 9 }).map((_, i) => (
          <div key={i} className="h-[200px] min-w-[120px]">
            <div className="border border-transparent rounded-xl overflow-hidden p-2.5">
              <Skeleton className="w-[100px] h-[100px] rounded-full mx-auto" />
              <div className="flex flex-col items-center gap-1 justify-center mt-3">
                <Skeleton className="h-4 w-20" />
                <Skeleton className="h-6 w-16 rounded-2xl" />
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        icon={Users}
        title={t("common.error")}
        description={t("common.error_loading")}
        size="sm"
        className="py-8"
      />
    );
  }

  if (!allActors?.length) {
    return (
      <EmptyState
        icon={Users}
        title={t("tabs.no_actresses_found")}
        description={t("tabs.no_actresses_description")}
        size="sm"
        className="py-8"
      />
    );
  }

  return (
    <div className="mb-4">
      <div className="grid grid-cols-3 xl:grid-cols-10 gap-2">
        {allActors.map((item) => (
          <ProfileCard
            key={item.id}
            item={item}
            type="actor"
            onClick={handleActorClick}
            onSubscribe={handleSubscribeToActor}
            showSubscribeButton={true}
            showVideoCount={false}
            className="border-transparent hover:border-transparent hover:bg-transparent"
          />
        ))}
      </div>

      {/* Sentinel element for infinite scroll */}
      {hasNextPage && (
        <div
          ref={inViewRef}
          className="h-16 flex items-center justify-center mt-6"
        >
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

type PublisherGroupListProps = {
  publisherId: string;
};

const PublisherGroupList = ({ publisherId }: PublisherGroupListProps) => {
  const { t } = useTranslation();
  const {
    data: infiniteData,
    isPending: isLoading,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfinitePublisherGroupList(publisherId);

  // Flatten all pages of groups
  const allGroups =
    infiniteData?.pages?.flatMap((page) => page.data?.data) ?? [];

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

  if (isLoading) {
    return (
      <div className="grid grid-cols-2 lg:grid-cols-4 2xl:grid-cols-8 gap-4 my-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <div key={i} className="space-y-2">
            <Skeleton className="h-[180px] w-full rounded-xl" />
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-3 w-1/2" />
          </div>
        ))}
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        icon={TrendingUp}
        title={t("common.error")}
        description={t("common.error_loading")}
        size="sm"
        className="py-8"
      />
    );
  }

  if (!allGroups?.length) {
    return (
      <EmptyState
        icon={TrendingUp}
        title={t("tabs.no_series_found")}
        description={t("tabs.no_series_description")}
        size="sm"
        className="py-8"
      />
    );
  }

  return (
    <div className="mb-4">
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-4 sm:gap-5">
        {allGroups.map((group) => (
          <PlaylistCard
            key={group.id}
            item={group}
            index={0}
            linkState={{
              from: "publisher",
              categoryName: t("publisher.series_list"),
            }}
            imageSize="responsive"
            showVideosCount={true}
          />
        ))}
      </div>

      {/* Sentinel element for infinite scroll */}
      {hasNextPage && (
        <div
          ref={inViewRef}
          className="h-16 flex items-center justify-center mt-6"
        >
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
