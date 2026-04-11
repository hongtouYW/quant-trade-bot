import { lazy, Suspense } from "react";
import { useTranslation } from "react-i18next";
import LatestVideoList from "@/components/latest-video-list.tsx";
import { BannerCarousel } from "@/components/banner-carousel.tsx";
import { PopupBannerContainer } from "@/components/popup-banner-container.tsx";
import { PlaylistCollection } from "@/components/playlist-collection.tsx";
import { UserActionMenu } from "@/components/user-action-menu.tsx";
import { FreeList } from "@/components/free-list";
// Eagerly load skeletons for immediate display as Suspense fallbacks
import { HotListSkeleton } from "@/components/skeletons/HotListSkeleton.tsx";
import { ActorListSkeleton } from "@/components/skeletons/ActorListSkeleton.tsx";
import { PublisherListSkeleton } from "@/components/skeletons/PublisherListSkeleton.tsx";
import { InfiniteListSkeleton } from "@/components/skeletons/InfiniteListSkeleton.tsx";
// Icons for skeleton section headers
import fireIcon from "@/assets/samples/hot/fire-icon.png";
import actorIcon from "@/assets/samples/actor/actor-icon.png";
import producerIcon from "@/assets/samples/producers/producer-icon.png";

// Lazy load below-fold components for faster initial load
const HotList = lazy(() =>
  import("@/components/hot-list.tsx").then((m) => ({ default: m.HotList }))
);
const ActorList = lazy(() =>
  import("@/components/actor-list.tsx").then((m) => ({ default: m.ActorList }))
);
const PublisherList = lazy(() =>
  import("@/components/producers-list.tsx").then((m) => ({
    default: m.PublisherList,
  }))
);
const InfiniteList = lazy(() =>
  import("@/components/infinite-list.tsx").then((m) => ({
    default: m.InfiniteList,
  }))
);

export default function Home() {
  const { t } = useTranslation();

  return (
    <div className="p-3 sm:p-4 space-y-4 sm:space-y-6">
      {/* Popup Banners - Floating buttons in bottom right */}
      <div className="invisible">
        <PopupBannerContainer />
      </div>

      {/* Top Banner - Multiple slides per view */}
      <BannerCarousel
        desktopHeight="h-full"
        position={1}
        slidesPerView="multiple"
      />

      <LatestVideoList />

      <FreeList />
      <PlaylistCollection />
      <UserActionMenu />

      {/* Middle Banner - Single slide per view */}
      <BannerCarousel
        position={2}
        slidesPerView="single"
        aspectRatio="aspect-[1280/224]"
        mobileAspectRatio="aspect-auto"
        desktopHeight="max-h-full"
        showDots={false}
        autoScroll
      />

      <Suspense
        fallback={
          <HotListSkeleton
            sectionTitle={t("popular_rankings.ranking_categories")}
            sectionIcon={
              <img src={fireIcon} className="size-6" alt="fire icon" />
            }
          />
        }
      >
        <HotList />
      </Suspense>
      <Suspense
        fallback={
          <ActorListSkeleton
            sectionTitle={t("homepage.actress_selection")}
            sectionIcon={
              <img src={actorIcon} className="size-6" alt="actor icon" />
            }
          />
        }
      >
        <ActorList />
      </Suspense>
      {/*<ChannelList /> Temporary disable until channel endpoint is available*/}
      <Suspense
        fallback={
          <PublisherListSkeleton
            sectionTitle={t("homepage.publisher_selection")}
            sectionIcon={
              <img src={producerIcon} className="size-6" alt="producer icon" />
            }
          />
        }
      >
        <PublisherList />
      </Suspense>

      {/* Bottom Banner - Single slide per view */}
      <BannerCarousel
        position={3}
        slidesPerView="single"
        aspectRatio="aspect-[1280/224]"
        mobileAspectRatio="aspect-auto"
        desktopHeight="max-h-full"
        showDots={false}
        autoScroll
      />

      <Suspense fallback={<InfiniteListSkeleton />}>
        <InfiniteList />
      </Suspense>
    </div>
  );
}
