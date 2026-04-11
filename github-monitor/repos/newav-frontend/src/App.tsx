import "./App.css";

import axios from "axios";
import { Route, Routes } from "react-router";
import MainLayout from "@/layouts/MainLayout.tsx";
import Home from "@/pages/Home.tsx";
import About from "@/pages/About.tsx";
import VideoPlayer from "@/pages/VideoPlayer.tsx";
import ActressInfo from "@/pages/ActressInfo.tsx";
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
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";
import PublisherInfo from "@/pages/PublisherInfo.tsx";
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
import { UserProvider } from "@/contexts/UserContext";
import { LanguageProvider } from "@/contexts/LanguageContext";
import { ConfigProvider } from "@/contexts/ConfigContext";
import { GlobalImageProvider } from "@/contexts/GlobalImageContext";
import { AuthDialogProvider } from "@/contexts/AuthDialogContext";
import { IdentityCardProvider } from "@/contexts/IdentityCardContext";
import { ProtectedRoute } from "@/components/ProtectedRoute";
import { Toaster } from "@/components/ui/sonner";
import { TokenValidator } from "@/components/TokenValidator";
import "@/i18n"; // Initialize i18next
import { ScrollToTop } from "@/components/ScrollToTop.tsx";
import { LanguageRouteSync } from "@/components/LanguageRouteSync";
import {
  RATE_LIMIT_ERROR_CODE,
  RATE_LIMIT_MAX_RETRIES,
  RATE_LIMIT_RETRY_DELAY,
} from "@/constants/network.ts";

const isRateLimitError = (error: unknown) =>
  axios.isAxiosError(error) &&
  error.response?.data?.code === RATE_LIMIT_ERROR_CODE;

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: (failureCount, error) =>
        isRateLimitError(error) && failureCount < RATE_LIMIT_MAX_RETRIES,
      retryDelay: () => RATE_LIMIT_RETRY_DELAY,
    },
  },
});

function App() {
  return (
    <>
      <QueryClientProvider client={queryClient}>
        <LanguageProvider>
          <ConfigProvider>
            <GlobalImageProvider>
              <UserProvider>
                <TokenValidator />
                <IdentityCardProvider>
                  <AuthDialogProvider>
                    <ScrollToTop />
                    <LanguageRouteSync />
                    <Routes>
                    {/* QR Code Auto-Login Route (outside MainLayout) */}
                    <Route path="/auth_login" element={<AuthLogin />} />

                    <Route path="/" element={<MainLayout />}>
                      <Route index element={<Home />} />
                      <Route path="about" element={<About />} />

                      {/* Public navigation routes */}
                      <Route path="latest" element={<Latest />} />
                      <Route path="latest-videos" element={<LatestVideos />} />
                      <Route path="free-videos" element={<FreeVideos />} />
                      <Route path="category/:id" element={<CategoryVideos />} />
                      <Route path="actresses" element={<ActressList />} />
                      <Route
                        path="publishers"
                        element={<PublisherListPage />}
                      />
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

                      {/* Existing dynamic routes */}
                      <Route
                        path=":lang/watch/:id/:slug"
                        element={<VideoPlayer />}
                      />
                      <Route path=":lang/watch/:id" element={<VideoPlayer />} />
                      <Route path="watch/:id" element={<VideoPlayer />} />
                      <Route path="actress/:id" element={<ActressInfo />} />
                      <Route path="publisher/:id" element={<PublisherInfo />} />
                      <Route path="search" element={<SearchResult />} />
                      <Route path="video/list" element={<VideoListPage />} />

                      <Route path="*" element={<PageNotFound />} />
                    </Route>
                    </Routes>
                  </AuthDialogProvider>
                </IdentityCardProvider>
              </UserProvider>
            </GlobalImageProvider>
          </ConfigProvider>
        </LanguageProvider>
        <Toaster />
        <ReactQueryDevtools initialIsOpen={false} />
      </QueryClientProvider>
    </>
  );
}

export default App;
