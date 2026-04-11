import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { useInView } from "react-intersection-observer";
import { InlineError } from "@/components/error-states";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group.tsx";
import { Button } from "@/components/ui/button.tsx";
import { cn } from "@/lib/utils.ts";
import actorIcon from "@/assets/samples/actor/actor-icon.png";
import badgeIcon from "@/assets/badge-icon.png";
import { ProfileCard } from "@/components/profile-card";
import { Link, useNavigate } from "react-router";
import { useInfiniteActorList } from "@/hooks/actor/useActorList.ts";
import { useVideoList } from "@/hooks/video/useVideoList.ts";
import { ActorListSkeleton } from "@/components/skeletons";
import { RankingCardListSkeleton } from "@/components/skeletons/RankingCardListSkeleton";
import { ActorListEmpty } from "@/components/empty-states";
import { ListError } from "@/components/error-states";
import { TrendingUp } from "lucide-react";
import { useSubscribeToActor } from "@/hooks/actor/useSubscribeActor.ts";
import { useAuthAction } from "@/hooks/auth/useAuthAction";
import { EnhancedVideoCard } from "@/components/ui/enhanced-video-card";
import { EndOfContent } from "@/components/EndOfContent";
import useEmblaCarousel from "embla-carousel-react";
import { Base64Image } from "@/components/Base64Image";

export default function Ranking() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [selected, setSelected] = useState("1");
  const { executeWithAuth } = useAuthAction();
  const { mutate: subscribeToActor } = useSubscribeToActor();
  const {
    data: topActorsInfinite,
    isPending: isTopActorsLoading,
    isError: isTopActorsError,
    refetch: refetchTopActors,
    fetchNextPage: fetchNextTopActors,
    hasNextPage: hasNextTopActors,
    isFetchingNextPage: isFetchingNextTopActors,
  } = useInfiniteActorList(
    {
      order: 1,
      limit: 10,
    },
    1,
  );
  const {
    data: bestActorsInfinite,
    isPending: isBestActorsLoading,
    isError: isBestActorsError,
    refetch: refetchBestActors,
    fetchNextPage: fetchNextBestActors,
    hasNextPage: hasNextBestActors,
    isFetchingNextPage: isFetchingNextBestActors,
  } = useInfiniteActorList(
    {
      order: 5,
      limit: 10,
    },
    5,
  );
  const {
    data: allActorsInfinite,
    isPending: isAllActorsLoading,
    isError: isAllActorsError,
    refetch: refetchAllActors,
    fetchNextPage: fetchNextAllActors,
    hasNextPage: hasNextAllActors,
    isFetchingNextPage: isFetchingNextAllActors,
  } = useInfiniteActorList(
    {
      limit: 30,
    },
    100,
  );

  // Flatten all pages of actors for each tab and deduplicate by ID
  const topActors = Array.from(
    new Map(
      (topActorsInfinite?.pages?.flatMap((page) => page.data?.data) ?? []).map(
        (actor) => [actor.id, actor],
      ),
    ).values(),
  );
  const bestActors = Array.from(
    new Map(
      (bestActorsInfinite?.pages?.flatMap((page) => page.data?.data) ?? []).map(
        (actor) => [actor.id, actor],
      ),
    ).values(),
  );
  const allActors = Array.from(
    new Map(
      (allActorsInfinite?.pages?.flatMap((page) => page.data?.data) ?? []).map(
        (actor) => [actor.id, actor],
      ),
    ).values(),
  );

  // Intersection observers for infinite scroll for each tab
  const { ref: topActorsRef, inView: topActorsInView } = useInView({
    threshold: 0.1,
    rootMargin: "100px",
  });
  const { ref: bestActorsRef, inView: bestActorsInView } = useInView({
    threshold: 0.1,
    rootMargin: "100px",
  });
  const { ref: allActorsRef, inView: allActorsInView } = useInView({
    threshold: 0.1,
    rootMargin: "100px",
  });

  // Trigger fetch when sentinel elements are in view
  useEffect(() => {
    if (topActorsInView && hasNextTopActors && !isFetchingNextTopActors) {
      fetchNextTopActors();
    }
  }, [
    topActorsInView,
    hasNextTopActors,
    isFetchingNextTopActors,
    fetchNextTopActors,
  ]);

  useEffect(() => {
    if (bestActorsInView && hasNextBestActors && !isFetchingNextBestActors) {
      fetchNextBestActors();
    }
  }, [
    bestActorsInView,
    hasNextBestActors,
    isFetchingNextBestActors,
    fetchNextBestActors,
  ]);

  useEffect(() => {
    if (allActorsInView && hasNextAllActors && !isFetchingNextAllActors) {
      fetchNextAllActors();
    }
  }, [
    allActorsInView,
    hasNextAllActors,
    isFetchingNextAllActors,
    fetchNextAllActors,
  ]);

  const handleActorClick = (actor: any) => {
    navigate(`/actress/${actor.id}`, {
      state: { from: "ranking", categoryName: t("ranking.all_actresses") },
    });
  };

  const handleSubscribe = (actorId: string | number) => {
    executeWithAuth(() => {
      subscribeToActor({
        aid: String(actorId),
      });
    });
  };

  const items = [
    { value: "1", label: t("ranking.bestseller") },
    { value: "2", label: t("ranking.actress_ranking") },
    { value: "3", label: t("ranking.all_actresses") },
  ];

  return (
    <div className="bg-background text-foreground transition-colors">
      <div className="sticky top-[112px] md:top-0 z-40 bg-background">
        {/* Header - desktop only */}
        <header className="hidden md:block border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <img
                loading="lazy"
                src={actorIcon}
                className="size-6"
                alt="new logo icon"
              />
              <span className="">{t("navbar.ranking")}</span>
            </div>
          </div>
        </header>

        {/* Filter section - always visible */}
        <div className="border-b px-4">
          <div className="flex py-3 items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2 text-base font-bold">
              <fieldset className="space-y-4">
                <RadioGroup
                  className="grid grid-cols-3 gap-2 sm:grid-flow-col sm:gap-3 sm:grid-cols-none"
                  value={selected}
                  onValueChange={setSelected}
                >
                  {items.map((item) => (
                    <label
                      key={`${item.value}`}
                      className="border-input has-data-[state=checked]:bg-primary/50 has-data-[state=checked]:text-white has-focus-visible:border-ring has-focus-visible:ring-ring/50 relative flex cursor-pointer flex-col items-center gap-3 rounded-full border px-2 py-3 text-center shadow-xs transition-[color,box-shadow] outline-none has-focus-visible:ring-[3px] has-data-disabled:cursor-not-allowed has-data-disabled:opacity-50"
                    >
                      <RadioGroupItem
                        id={`${item.value}`}
                        value={item.value}
                        className="sr-only after:absolute after:inset-0"
                      />
                      <p className="px-2 sm:px-8.5 text-xs sm:text-sm leading-none font-medium">
                        {item.label}
                      </p>
                    </label>
                  ))}
                </RadioGroup>
              </fieldset>
            </div>
          </div>
        </div>
      </div>

      <div className="mt-6 px-4 space-y-4">
        {selected === "1" && (
          <>
            {isTopActorsLoading && (
              <RankingCardListSkeleton
                sectionTitle={t("ranking.bestseller")}
                sectionIcon={<TrendingUp size={20} />}
              />
            )}
            {isTopActorsError && (
              <ListError
                title={t("ranking.actors_error")}
                description={t("ranking.actors_error_desc")}
                onRetry={() => refetchTopActors()}
                sectionTitle={t("ranking.bestseller")}
                sectionIcon={<TrendingUp size={20} />}
              />
            )}
            {!isTopActorsLoading &&
              !isTopActorsError &&
              (!topActors || topActors.length === 0) && (
                <ActorListEmpty
                  title={t("ranking.no_bestsellers")}
                  description={t("ranking.no_bestsellers_desc")}
                  sectionTitle={t("ranking.bestseller")}
                  sectionIcon={<TrendingUp size={20} />}
                />
              )}
            {!isTopActorsLoading &&
              !isTopActorsError &&
              topActors &&
              topActors.length > 0 && (
                <>
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5">
                    {topActors.map((item, index) => (
                      <div
                        key={`top-${item.id}`}
                        className="bg-card text-card-foreground w-full relative mb-2 flex gap-1.5 md:gap-3 items-stretch border border-border rounded-2xl px-1.5 py-2.5 md:p-4 transition-colors isolate"
                      >
                        {/* Ranking Badge - positioned relative to card */}
                        <div className="absolute -top-3 left-4 z-20">
                          <div className="py-1.5 px-3 sm:px-4 border border-primary dark:border-primary/60 rounded-full flex items-center gap-2 text-xs font-medium shadow-sm bg-brand-light-purple text-brand-accent dark:bg-primary/50 dark:text-primary-foreground">
                            <img
                              loading="lazy"
                              src={badgeIcon}
                              className="size-3"
                              alt="ranking badge"
                            />
                            <span className="font-semibold">
                              NO. {index + 1}
                            </span>
                          </div>
                        </div>

                        <div
                          className="flex-shrink-0 max-w-[80px] md:max-w-full py-2 md:py-4"
                          style={{ margin: "auto 0" }}
                        >
                          <Link
                            to={`/actress/${item.id}`}
                            className="flex flex-col items-center"
                            state={{
                              from: "ranking",
                              categoryName: t("ranking.bestseller"),
                            }}
                          >
                            {item.image ? (
                              <Base64Image
                                originalUrl={item.image}
                                alt="Actress avatar"
                                className="size-12 md:size-20 rounded-full object-cover border-2 border-primary"
                              />
                            ) : (
                              <img
                                loading="lazy"
                                src={actorIcon}
                                alt="Actress avatar"
                                className="size-12 md:size-20 rounded-full object-cover border-2 border-primary"
                              />
                            )}

                            <div className="flex flex-col items-center gap-1 justify-center mt-2">
                              <h2 className="text-sm font-bold text-center px-1 truncate max-w-[100px]">
                                {item.name}
                              </h2>
                              <Button
                                className={cn(
                                  item?.is_subscribe === 1
                                    ? "bg-[#781938] text-white"
                                    : "bg-[#EA1E61] text-white",
                                  "rounded-2xl py-1.5 px-3 text-xs h-6 min-w-[70px] hover:opacity-90 transition-colors",
                                )}
                                size="sm"
                                onClick={(event) => {
                                  event.stopPropagation();
                                  event.preventDefault();
                                  handleSubscribe(item.id);
                                }}
                              >
                                {item?.is_subscribe === 1
                                  ? t("common.followed")
                                  : t("common.follow")}
                              </Button>
                            </div>
                          </Link>
                        </div>

                        <div className="flex-1 min-w-0 overflow-x-auto flex items-center">
                          <MinimalVideoList actorId={item.id} />
                        </div>
                      </div>
                    ))}
                  </div>

                  {/* Sentinel element for infinite scroll - Tab 1 */}
                  {hasNextTopActors && (
                    <div
                      ref={topActorsRef}
                      className="h-16 flex items-center justify-center mt-6"
                    >
                      {isFetchingNextTopActors && (
                        <span className="text-muted-foreground">
                          {t("common.loading")}
                        </span>
                      )}
                    </div>
                  )}

                  {/* End of content indicator - Tab 1 */}
                  {!hasNextTopActors && <EndOfContent />}
                </>
              )}
          </>
        )}
        {selected === "2" && (
          <>
            {isBestActorsLoading && (
              <RankingCardListSkeleton
                sectionTitle={t("ranking.actress_ranking")}
                sectionIcon={<TrendingUp size={20} />}
              />
            )}
            {isBestActorsError && (
              <ListError
                title={t("ranking.actors_error")}
                description={t("ranking.actors_error_desc")}
                onRetry={() => refetchBestActors()}
                sectionTitle={t("ranking.actress_ranking")}
                sectionIcon={<TrendingUp size={20} />}
              />
            )}
            {!isBestActorsLoading &&
              !isBestActorsError &&
              (!bestActors || bestActors.length === 0) && (
                <ActorListEmpty
                  title={t("ranking.no_best_actors")}
                  description={t("ranking.no_best_actors_desc")}
                  sectionTitle={t("ranking.actress_ranking")}
                  sectionIcon={<TrendingUp size={20} />}
                />
              )}
            {!isBestActorsLoading &&
              !isBestActorsError &&
              bestActors &&
              bestActors.length > 0 && (
                <>
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5">
                    {bestActors.map((item, index) => (
                      <div
                        key={`best-${item.id}`}
                        className="bg-card text-card-foreground w-full relative mb-3 flex gap-2 md:gap-3 items-stretch border border-border rounded-2xl px-2 py-4 md:p-4 transition-colors isolate"
                      >
                        {/* Ranking Badge - positioned relative to card */}
                        <div className="absolute -top-3 left-4 z-20">
                          <div className="py-1.5 px-3 sm:px-4 border border-primary dark:border-primary/60 rounded-full flex items-center gap-2 text-xs font-medium shadow-sm bg-brand-light-purple text-brand-accent dark:bg-primary/20 dark:text-primary-foreground">
                            <img
                              loading="lazy"
                              src={badgeIcon}
                              className="size-3"
                              alt="ranking badge"
                            />
                            <span className="font-semibold">
                              NO. {index + 1}
                            </span>
                          </div>
                        </div>

                        <div
                          className="flex-shrink-0 max-w-[80px] md:max-w-full"
                          style={{ margin: "auto 0" }}
                        >
                          <Link
                            to={`/actress/${item.id}`}
                            className="flex flex-col items-center"
                            state={{
                              from: "ranking",
                              categoryName: t("ranking.actress_ranking"),
                            }}
                          >
                            {item.image ? (
                              <Base64Image
                                originalUrl={item.image}
                                alt="Actress avatar"
                                className="size-12 md:size-20 rounded-full object-cover border-2 border-primary"
                              />
                            ) : (
                              <img
                                loading="lazy"
                                src={actorIcon}
                                alt="Actress avatar"
                                className="size-12 md:size-20 rounded-full object-cover border-2 border-primary"
                              />
                            )}

                            <div className="flex flex-col items-center gap-1 justify-center mt-2">
                              <h2 className="text-sm font-bold text-center px-1 truncate max-w-[100px]">
                                {item.name}
                              </h2>
                              <Button
                                className={cn(
                                  item?.is_subscribe === 1
                                    ? "bg-[#781938] text-white"
                                    : "bg-[#EA1E61] text-white",
                                  "rounded-2xl py-1.5 px-3 text-xs h-6 min-w-[70px] hover:opacity-90 transition-colors",
                                )}
                                size="sm"
                                onClick={(event) => {
                                  event.stopPropagation();
                                  event.preventDefault();
                                  handleSubscribe(item.id);
                                }}
                              >
                                {item?.is_subscribe === 1
                                  ? t("common.followed")
                                  : t("common.follow")}
                              </Button>
                            </div>
                          </Link>
                        </div>

                        <div className="flex-1 min-w-0 overflow-x-auto flex items-center">
                          <MinimalVideoList actorId={item.id} />
                        </div>
                      </div>
                    ))}
                  </div>

                  {/* Sentinel element for infinite scroll - Tab 2 */}
                  {hasNextBestActors && (
                    <div
                      ref={bestActorsRef}
                      className="h-16 flex items-center justify-center mt-6"
                    >
                      {isFetchingNextBestActors && (
                        <span className="text-muted-foreground">
                          {t("common.loading")}
                        </span>
                      )}
                    </div>
                  )}

                  {/* End of content indicator - Tab 2 */}
                  {!hasNextBestActors && <EndOfContent />}
                </>
              )}
          </>
        )}
        {selected === "3" && (
          <>
            {isAllActorsLoading && (
              <ActorListSkeleton
                sectionTitle={t("ranking.all_actresses")}
                sectionIcon={<TrendingUp size={20} />}
              />
            )}
            {isAllActorsError && (
              <ListError
                title={t("ranking.actors_error")}
                description={t("ranking.actors_error_desc")}
                onRetry={() => refetchAllActors()}
                sectionTitle={t("ranking.all_actresses")}
                sectionIcon={<TrendingUp size={20} />}
              />
            )}
            {!isAllActorsLoading &&
              !isAllActorsError &&
              (!allActors || allActors.length === 0) && (
                <ActorListEmpty
                  title={t("ranking.no_actresses")}
                  description={t("ranking.no_actresses_desc")}
                  sectionTitle={t("ranking.all_actresses")}
                  sectionIcon={<TrendingUp size={20} />}
                />
              )}
            {!isAllActorsLoading &&
              !isAllActorsError &&
              allActors &&
              allActors.length > 0 && (
                <>
                  <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-10 gap-4 sm:gap-5">
                    {allActors.map((item) => (
                      <div key={`all-${item.id}`}>
                        <ProfileCard
                          item={item}
                          type="actor"
                          onClick={handleActorClick}
                        />
                      </div>
                    ))}
                  </div>

                  {/* Sentinel element for infinite scroll - Tab 3 */}
                  {hasNextAllActors && (
                    <div
                      ref={allActorsRef}
                      className="h-16 flex items-center justify-center mt-6"
                    >
                      {isFetchingNextAllActors && (
                        <span className="text-muted-foreground">
                          {t("common.loading")}
                        </span>
                      )}
                    </div>
                  )}

                  {/* End of content indicator - Tab 3 */}
                  {!hasNextAllActors && <EndOfContent />}
                </>
              )}
          </>
        )}
      </div>
    </div>
  );
}

type MinimalVideoListProps = {
  actorId: number;
};

const MinimalVideoList = ({ actorId }: MinimalVideoListProps) => {
  const { t } = useTranslation();
  const { data, isPending, isError } = useVideoList(
    {
      actor_id: actorId,
      limit: 6,
      page: 1,
    },
    actorId,
  );

  // Embla carousel setup with free scroll - each instance gets its own carousel
  const [emblaRef] = useEmblaCarousel({
    align: "start",
    containScroll: "trimSnaps",
    dragFree: true,
    slidesToScroll: "auto",
    duration: 20,
    skipSnaps: true,
  });

  if (isPending) {
    return (
      <div className="overflow-hidden" ref={emblaRef}>
        <div className="flex gap-2 sm:gap-3 px-2 h-full items-stretch">
          {Array.from({ length: 6 }).map((_, index) => (
            <div
              key={index}
              className="relative bg-gray-200 animate-pulse rounded-lg overflow-hidden flex-shrink-0"
              style={{ width: "140px", minHeight: "200px" }}
            />
          ))}
        </div>
      </div>
    );
  }

  if (isError) return <InlineError />;

  if (data.data.total === 0) {
    return (
      <div className="flex items-center justify-center p-4 text-xs sm:text-sm text-gray-500">
        {t("ranking.no_videos")}
      </div>
    );
  }

  return (
    <div className="overflow-hidden w-full" ref={emblaRef}>
      <div className="flex gap-0.5 sm:gap-3 px-2">
        {data?.data.data.map((video) => (
          <EnhancedVideoCard
            key={video.id}
            video={video}
            layout="vertical"
            size="xs"
            linkState={{
              from: "ranking",
              categoryName: t("ranking.bestseller"),
            }}
            showBadges={true}
            showRating={false}
            showActor={false}
            imageOnly={true}
            className="flex-shrink-0  min-w-[90px]! w-[90px]! h-[128px]! md:w-full! md:min-w-[135px]! md:h-[190px]!"
          />
        ))}
      </div>
    </div>
  );
};
