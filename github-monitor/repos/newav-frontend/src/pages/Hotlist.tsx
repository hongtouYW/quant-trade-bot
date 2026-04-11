import { useEffect } from "react";
import { useTranslation } from "react-i18next";
import { useInView } from "react-intersection-observer";
import { ChevronLeft } from "lucide-react";
import { Link, useNavigate } from "react-router";
import fireIcon from "@/assets/samples/hot/fire-icon.png";
import appLogoWhite from "@/assets/logo-white.svg";
import { Button } from "@/components/ui/button.tsx";
import { ListError } from "@/components/error-states";
import { Skeleton } from "@/components/ui/skeleton";
import { EndOfContent } from "@/components/EndOfContent";
import { useInfiniteHotlistLists } from "@/hooks/video/useInfiniteHotlistLists";

export default function Hotlist() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const {
    data: infiniteData,
    isPending,
    isError,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteHotlistLists();

  const { ref: inViewRef, inView } = useInView({
    threshold: 1,
    rootMargin: "200px",
  });

  useEffect(() => {
    if (inView && hasNextPage && !isFetchingNextPage) {
      fetchNextPage();
    }
  }, [fetchNextPage, hasNextPage, inView, isFetchingNextPage]);

  const paginatedHotlists = infiniteData?.pages ?? [];
  const hotlistItems =
    paginatedHotlists.flatMap((page) => page.data ?? []) ?? [];
  const totalHotlists = paginatedHotlists[0]?.total ?? 0;

  const isInitialLoading = isPending && hotlistItems.length === 0;
  const showEmptyState = !isPending && !isError && hotlistItems.length === 0;

  return (
    <div className="flex min-h-screen flex-col">
      <header className="border-b px-4">
        <div className="flex h-14 items-center justify-between gap-4">
          <div className="flex flex-1 items-center gap-2 text-base font-bold">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(-1)}
              className="mr-2 h-8 w-8 p-1"
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <img
              loading="lazy"
              src={fireIcon}
              className="size-6"
              alt="hotlist icon"
            />
            <span>{t("popular_rankings.ranking_categories")}</span>
            {totalHotlists > 0 && (
              <span className="ml-2 text-sm text-gray-500">
                ({totalHotlists} {t("common.results")})
              </span>
            )}
          </div>
        </div>
      </header>

      <main className="flex-1 px-4 py-4">
        {isInitialLoading && (
          <div className="grid grid-cols-2 gap-3">
            {Array.from({ length: 4 }).map((_, index) => (
              <div
                key={index}
                className="relative h-52 overflow-hidden rounded-xl"
              >
                <Skeleton className="h-full w-full" />
              </div>
            ))}
          </div>
        )}

        {isError && (
          <ListError
            onRetry={() => refetch()}
            title={t("error.loading_failed")}
            description={t("error.please_try_again")}
            sectionTitle={t("popular_rankings.ranking_categories")}
            sectionIcon={
              <img
                loading="lazy"
                src={fireIcon}
                className="size-6"
                alt="hotlist icon"
              />
            }
          />
        )}

        {showEmptyState && (
          <div className="flex h-full flex-col items-center justify-center rounded-lg border border-dashed border-border p-8 text-center text-muted-foreground">
            {t("common.no_data")}
          </div>
        )}

        {!isInitialLoading && !isError && hotlistItems.length > 0 && (
          <div className="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-4 gap-3 sm:gap-4">
            {hotlistItems.map((item) => (
              <Link key={item.id} to={`/hotlist/${item.id}`} className="group">
                <div className="relative h-52 w-full overflow-hidden rounded-xl shadow-lg transition-transform duration-200 group-hover:scale-[1.01]">
                  <img
                    loading="lazy"
                    src={item.image}
                    alt={item.title}
                    className="h-full w-full object-cover"
                  />
                  <div className="absolute inset-0 bg-black/50 transition-opacity duration-200 group-hover:bg-black/60" />
                  <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 px-4 text-center">
                    <img
                      loading="lazy"
                      className="size-16"
                      src={appLogoWhite}
                      alt=""
                    />
                    <h2 className="text-base font-semibold text-white">
                      {item.title}
                    </h2>
                    <p className="text-sm text-white/80">{item.sub_title}</p>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}

        {hasNextPage && (
          <div
            ref={inViewRef}
            className="flex h-20 items-center justify-center"
          >
            {isFetchingNextPage && (
              <span className="text-muted-foreground">
                {t("common.loading")}
              </span>
            )}
          </div>
        )}

        {!hasNextPage && <EndOfContent />}
      </main>
    </div>
  );
}
