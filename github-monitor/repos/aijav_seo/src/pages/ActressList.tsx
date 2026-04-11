import { useEffect } from "react";
import { useNavigate } from "react-router";
import { useTranslation } from "react-i18next";
import { useInView } from "react-intersection-observer";
import { ChevronLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useInfiniteActorList } from "@/hooks/actor/useActorList";
import { ActorListEmpty } from "@/components/empty-states";
import { ListError } from "@/components/error-states";
import { ProfileCard } from "@/components/profile-card";
import { EndOfContent } from "@/components/EndOfContent";
import actorIcon from "../assets/samples/actor/actor-icon.png";
import type { ActorList as ActorType } from "@/types/actor.types";

export default function ActressList() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const {
    data,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
    isPending,
    isError,
    refetch,
  } = useInfiniteActorList(
    {
      order: 5,
      limit: 30,
    },
    99,
  );

  // Flatten all pages into a single array
  const allActors = data?.pages?.flatMap((page) => page.data?.data || []) || [];

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

  const handleGoBack = () => {
    navigate("/");
  };

  const handleActorClick = (actor: ActorType) => {
    navigate(`/actress/${actor.id}`, {
      state: {
        from: "actresses",
        categoryName: t("homepage.actress_selection"),
      },
    });
  };

  if (isPending) {
    return (
      <div>
        <header className="border-b px-4">
          <div className="flex h-14 items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <Button
                variant="ghost"
                size="sm"
                onClick={handleGoBack}
                className="flex items-center gap-2"
              >
                <ChevronLeft className="w-4 h-4" />
              </Button>

              <div className="flex flex-row justify-center items-center py-2 px-5 gap-2.5">
                <img
                  loading="lazy"
                  src={actorIcon}
                  className="size-5"
                  alt="actress icon"
                />
                <h5 className="text-base font-bold leading-none text-primary">
                  {t("homepage.actress_selection")}
                </h5>
              </div>
            </div>
          </div>
        </header>

        {/* Loading skeleton with header */}
        <div className="mt-6 px-4">
          <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-5">
            {Array.from({ length: 30 }).map((_, index) => (
              <div
                key={index}
                className="flex flex-col items-center border border-accent rounded-xl overflow-hidden p-[10px]"
              >
                {/* Avatar skeleton matching ProfileCard */}
                <div className="size-20 sm:size-24">
                  <Skeleton className="w-full h-full rounded-full border-2 border-accent" />
                </div>

                {/* Content skeleton matching ProfileCard */}
                <div className="flex flex-col w-full items-center gap-1 justify-center">
                  {/* Name skeleton */}
                  <Skeleton className="mt-2 h-3 sm:h-4 w-3/4" />

                  {/* Video count skeleton */}
                  <Skeleton className="h-3 sm:h-4 w-1/2" />

                  {/* Subscribe button skeleton */}
                  <Skeleton className="mt-1 h-7 w-full rounded-2xl" />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div>
      <header className="border-b px-4">
        <div className="flex h-14 items-center justify-between gap-4">
          <div className="flex items-center">
            <Button
              variant="ghost"
              size="sm"
              onClick={handleGoBack}
              className="flex items-center gap-2"
            >
              <ChevronLeft className="w-4 h-4" />
            </Button>

            <div className="flex flex-row justify-center items-center py-2 px-5 gap-2.5 rounded-full">
              <img
                loading="lazy"
                src={actorIcon}
                className="size-5"
                alt="actress icon"
              />
              <h5 className="text-base font-bold leading-none">
                {t("homepage.actress_selection")}
              </h5>
            </div>
          </div>
        </div>
      </header>

      <div className="mt-6 px-4 space-y-4">
        {isError && (
          <ListError
            title={t("empty.no_actresses")}
            description={t("empty.no_actresses_desc")}
            onRetry={() => refetch()}
            sectionTitle={t("homepage.actress_selection")}
            sectionIcon={
              <img
                loading="lazy"
                src={actorIcon}
                className="size-6"
                alt="actress icon"
              />
            }
          />
        )}
        {!isError && (!allActors || allActors.length === 0) && (
          <ActorListEmpty
            title={t("empty.no_actresses")}
            description={t("empty.no_actresses_desc")}
            onRefresh={() => refetch()}
            sectionTitle={t("homepage.actress_selection")}
            sectionIcon={
              <img
                loading="lazy"
                src={actorIcon}
                className="size-6"
                alt="actress icon"
              />
            }
          />
        )}
        {!isError && allActors && allActors.length > 0 && (
          <>
            <div className="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-5">
              {allActors.map((actor) => (
                <div key={actor.id}>
                  <ProfileCard
                    item={actor}
                    type="actor"
                    onClick={handleActorClick}
                  />
                </div>
              ))}
            </div>

            {/* Sentinel element for infinite scroll */}
            {hasNextPage && (
              <div
                ref={inViewRef}
                className="h-16 flex items-center justify-center mt-6"
              >
                {isFetchingNextPage && (
                  <span className="text-muted-foreground">
                    {t("common.loading")}
                  </span>
                )}
              </div>
            )}

            {/* End of content indicator */}
            {!hasNextPage && <EndOfContent />}
          </>
        )}
      </div>
    </div>
  );
}
