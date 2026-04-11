import "./App.css";

import { Route, Routes } from "react-router";
import MainLayout from "@/layouts/MainLayout.tsx";
import Home from "@/pages/Home.tsx";
import About from "@/pages/About.tsx";
import Following from "./pages/Following";
import Series from "@/pages/Series.tsx";
import Ranking from "@/pages/Ranking.tsx";
import Hotlist from "@/pages/Hotlist.tsx";
import Latest from "@/pages/Latest.tsx";
import LatestVideos from "@/pages/LatestVideos.tsx";
import FreeVideos from "@/pages/FreeVideos.tsx";
import CategoryVideos from "@/pages/CategoryVideos.tsx";
import Categories from "@/pages/Categories.tsx";
import Plans from "@/pages/Plans.tsx";
import PageNotFound from "@/pages/PageNotFound.tsx";
import SearchResult from "@/pages/SearchResult.tsx";
import MyChannels from "@/pages/MyChannels.tsx";
import { WatchHistory } from "@/pages/WatchHistory.tsx";
import MyFavourites from "@/pages/MyFavourites.tsx";
import { CollectedVideos } from "@/pages/CollectedVideos.tsx";
import SeriesDetail from "@/pages/SeriesDetail.tsx";
import HotlistDetail from "@/pages/HotlistDetail.tsx";
import PurchaseHistory from "@/pages/PurchaseHistory.tsx";
import VideoListPage from "@/pages/VideoListPage.tsx";
import ActressList from "@/pages/ActressList.tsx";
import PublisherListPage from "@/pages/PublisherListPage.tsx";
import Notifications from "@/pages/Notifications.tsx";
import ExchangeCode from "@/pages/ExchangeCode.tsx";
import StackedAccordionDemo from "@/pages/StackedAccordionDemo.tsx";
import AuthLogin from "@/pages/AuthLogin.tsx";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { ScrollToTop } from "@/components/ScrollToTop.tsx";
import { RouteErrorFallback } from "@/components/RouteErrorFallback";

function App() {
  return (
    <>
      <ScrollToTop />
      <Routes>
        {/* QR Code Auto-Login Route (outside MainLayout) */}
        <Route path="/auth_login" element={<AuthLogin />} />

        <Route path="/" element={<MainLayout />} errorElement={<RouteErrorFallback />}>
          <Route index element={<Home />} />
          <Route path="about" element={<About />} />

          {/* Public navigation routes */}
          <Route path="latest" element={<Latest />} />
          <Route path="latest-videos" element={<LatestVideos />} />
          <Route path="free-videos" element={<FreeVideos />} />
          <Route path="category/:id" element={<CategoryVideos />} />
          <Route path="actresses" element={<ActressList />} />
          <Route path="publishers" element={<PublisherListPage />} />
          <Route path="hotlist" element={<Hotlist />} />
          <Route path="series" element={<Series />} />
          <Route path="ranking" element={<Ranking />} />
          <Route path="categories" element={<Categories />} />
          <Route path="plans" element={<Plans />} />
          <Route
            path="stacked-accordion-demo"
            element={<StackedAccordionDemo />}
          />

          {/* Auth-required routes */}
          <Route
            path="following"
            element={
              <ProtectedRoute>
                <Following />
              </ProtectedRoute>
            }
          />
          <Route
            path="my-channels"
            element={
              <ProtectedRoute>
                <MyChannels />
              </ProtectedRoute>
            }
          />
          <Route
            path="my-favorites"
            element={
              <ProtectedRoute>
                <MyFavourites />
              </ProtectedRoute>
            }
          />
          <Route
            path="collected-videos"
            element={
              <ProtectedRoute>
                <CollectedVideos />
              </ProtectedRoute>
            }
          />
          <Route
            path="watch-history"
            element={
              <ProtectedRoute>
                <WatchHistory />
              </ProtectedRoute>
            }
          />
          <Route
            path="purchase-history"
            element={
              <ProtectedRoute>
                <PurchaseHistory />
              </ProtectedRoute>
            }
          />
          <Route
            path="notifications"
            element={
              <ProtectedRoute>
                <Notifications />
              </ProtectedRoute>
            }
          />
          <Route
            path="exchange-code"
            element={
              <ProtectedRoute>
                <ExchangeCode />
              </ProtectedRoute>
            }
          />
          <Route path="series/:gid" element={<SeriesDetail />} />
          <Route path="hotlist/:hid" element={<HotlistDetail />} />

          {/* actress/:id, publisher/:id, watch/:id, and / are handled by
              SSR routes (see src/routes.ts) and never reach this SPA catchall.
              They 301-redirect unprefixed URLs to /zh/... or /en/... at the
              server layer, so these paths are no longer declared here. */}
          <Route path="search" element={<SearchResult />} />
          <Route path="video/list" element={<VideoListPage />} />

          <Route path="*" element={<PageNotFound />} />
        </Route>
      </Routes>
    </>
  );
}

export default App;
