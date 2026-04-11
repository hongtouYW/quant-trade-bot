import { useState, useEffect, useRef } from "react";
import { NavLink, useNavigate } from "react-router";

import { useTranslation } from "react-i18next";

import { Swiper, SwiperSlide } from "swiper/react";
import { Autoplay, Pagination } from "swiper/modules";

import { useResourceContext } from "../contexts/resource.context";
import { useUser } from "../contexts/user.context";

import Post from "../components/Post";
import Banner from "../components/Banner";
import PostList from "../components/PostList";
import Image from "../components/Image";

import useComicHomepage from "../hooks/useComicHomepage";

import { COMIC_LIST_TYPE, COMIC_RANK_TYPE } from "../utils/constant";
import type { ComicHomepageListsType, ComicHomepageRankingType, ComicHomepageType } from "../utils/type";

const tabsList = [
  {
    id: COMIC_RANK_TYPE.BEST_SELLER,
    locale: "ranking.hot",
    value: COMIC_RANK_TYPE.BEST_SELLER,
    children: "bestSeller",
  },
  {
    id: COMIC_RANK_TYPE.POPULAR,
    locale: "ranking.popular",
    value: COMIC_RANK_TYPE.POPULAR,
    children: "popular",
  },
  {
    id: COMIC_RANK_TYPE.FINISHED,
    locale: "ranking.finished",
    value: COMIC_RANK_TYPE.FINISHED,
    children: "finished",
  },
  {
    id: COMIC_RANK_TYPE.SUBSCRIBE,
    locale: "ranking.topSubscribe",
    value: COMIC_RANK_TYPE.SUBSCRIBE,
    children: "subscribe",
  },
];

const POSTLIST_MAP: Record<string, { more?: boolean, morePath?: string, showTag?: boolean }> = {
  // key: module-params.type
  "indexLists-1": { // New Release
    more: true,
    morePath: "/new",
    showTag: true,
  },
  "rank-1": { // Best Seller
    more: true,
    morePath: "/ranking",
    showTag: true,
  },
  "indexLists-4": { // Limited Free
    more: true,
    morePath: "/free",
    showTag: true,
  },
  "indexLists-3": { // Featured
    more: true,
    morePath: "/finished?type=7",
    showTag: true,
  }
}

const Home = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const swiperRef = useRef<any>(null);
  const swiperMobileRef = useRef<any>(null);

  // const allCategory = { id: "0", name: t("common.all") };
  const [currentActiveTab, setCurrentActiveTab] = useState<any>(tabsList?.[0]);

  const { bannersHome, banners456, notice } = useResourceContext();
  const { setIsOpenNoticeModal } = useUser();
  const { data: comicHomepageData } = useComicHomepage({ page: 1, limit: 20 });
  const comicRankData = comicHomepageData?.total ? comicHomepageData.data?.find(item => item.module === 'allRank') : null

  const comicRankLists = comicRankData ? {
    bestSeller: (comicRankData.data as ComicHomepageRankingType)?.hot?.data,
    popular: (comicRankData.data as ComicHomepageRankingType)?.popular?.data,
    finished: (comicRankData.data as ComicHomepageRankingType)?.finished?.data,
    subscribe: (comicRankData.data as ComicHomepageRankingType)?.subscribe?.data,
  } : {
    bestSeller: [],
    popular: [],
    finished: [],
    subscribe: [],
  };

  // 排行榜 TabsOnChange
  const handleClickTabOnChange = (value: any) => {
    const currenTab = tabsList?.find((item) => {
      return item.id?.toString() === value;
    });
    if (currenTab) {
      setCurrentActiveTab(currenTab);
    }
  };

  // 每次访问Home页面时自动弹出notice modal
  useEffect(() => {
    if (notice && notice.length > 0) {
      // if modal was closed within the last hour
      const closedAt = localStorage.getItem('noticeModal_closedAt');
      if (closedAt) {
        const oneHourInMs = 60 * 60 * 1000;
        const timeSinceClosed = Date.now() - parseInt(closedAt);

        // open if more than 1 hour has passed
        if (timeSinceClosed > oneHourInMs) {
          setIsOpenNoticeModal(true);
        }
      } else {
        // first time viewing
        setIsOpenNoticeModal(true);
      }
    }
  }, [notice, setIsOpenNoticeModal]);

  useEffect(() => {
    if (swiperRef.current && currentActiveTab) {
      swiperRef.current.slideTo(Number(currentActiveTab.id) - 1);
    }
    if (swiperMobileRef.current && currentActiveTab) {
      swiperMobileRef.current.slideTo(Number(currentActiveTab.id) - 1);
    }
  }, [currentActiveTab]);

  const computedMorePath = (pl: ComicHomepageListsType) => {
    if (pl.module === "lists") {
      return `/finished?type=${COMIC_LIST_TYPE.BROWSE}&tag=${pl.params.tag || 0}&ticai_id=${pl.params.ticai_id || 0}`
    }

    return POSTLIST_MAP?.[`${pl.module}-${pl.params.type}`]?.morePath || "/";
  };

  return (
    <div className="mb-10 mt-[49px] xs:mt-[63px] lg:mt-0">
      <h1 className="hidden">
        18Toon - Free + Premium Hentai | Adult Manga Online
      </h1>

      {/* 轮播图 - Banner */}
      <Banner pagination={true} banners={bannersHome} />

      {/* 首页自定义模板 */}
      <div className="mx-4">
        {comicHomepageData?.total && comicHomepageData.data?.map(pl => {
          return (
            (pl.data as ComicHomepageType)?.total !== 0 && (pl.module === 'allRank' ? (
              <div key={pl.id} className="">
                <div className="max-w-screen-xl mx-auto">
                  <div className="flex items-end justify-between mb-4">
                    <div className="flex flex-col gap-4 w-full justify-between">
                      <div className="flex items-center gap-2">
                        <h4 className="text-2xl font-semibold text-center max-sm:text-base">
                          {t("common.ranking")}
                        </h4>
                      </div>
                      <div className="w-full">
                        <div className="flex items-center gap-2 text-center">
                          {tabsList?.map((item) => {
                            return (
                              <div
                                key={item.id}
                                className={`text-sm py-1 px-2 rounded-lg cursor-pointer ${currentActiveTab?.id === item.id
                                  ? "text-primary bg-primary-100 font-semibold"
                                  : "text-greyscale-900 bg-greyscale-200"
                                  }`}
                                onClick={() => {
                                  handleClickTabOnChange(item.id);
                                }}
                              >
                                {t(item.locale)}
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    </div>
                    <div
                      className="hidden xs:flex items-center gap-1 shrink-0 cursor-pointer"
                      onClick={() => {
                        navigate("/ranking");
                      }}
                    >
                      <p className="text-primary font-medium text-sm lg:text-xl">
                        {t("common.more")}
                      </p>
                      <img
                        src={`${import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-cheveron-right.svg`}
                        alt="right"
                        className="max-sm:w-4 max-sm:h-4"
                      />
                    </div>
                  </div>
                  <Swiper
                    onSwiper={(swiper) => {
                      swiperMobileRef.current = swiper; // capture the instance
                    }}
                    centeredSlides
                    centeredSlidesBounds={true}
                    slidesPerView={1}
                    mousewheel={true}
                    modules={[Autoplay, Pagination]}
                    onSlideChange={(swiper) => {
                      const currenTab = tabsList[swiper?.activeIndex];
                      if (currenTab) {
                        setCurrentActiveTab(currenTab);
                      }
                    }}
                  >
                    {Object.entries(comicRankLists)?.map(([category, list]) => {
                      return (
                        <SwiperSlide key={category}>
                          <div className="grid grid-cols-3 gap-2 pb-2 xs:grid-cols-5 lg:gap-6 xl:grid-cols-6">
                            {list?.slice(0, 6)?.map((item: any, index: number) => {
                              return (
                                <Post
                                  key={item.id}
                                  item={item}
                                  fixedHeight={true}
                                  index={index + 1}
                                  showRank={true}
                                />
                              );
                            })}
                          </div>
                        </SwiperSlide>
                      );
                    })}
                  </Swiper>
                </div>
              </div>
            ) : (
              <div key={pl.id} className={`${pl.is_highlight && 'bg-[#FFF7EB] -mx-4 px-4'}`}>
                <PostList
                  key={pl.id}
                  title={pl.name}
                  isGoldTitle={pl.is_highlight === 1}
                  morePath={computedMorePath(pl)}
                  more={
                    (pl?.name && POSTLIST_MAP?.[`${pl.module}-${pl.params.type}`]?.more) ||
                      pl.module === 'lists'
                      ? true
                      : false
                  }
                  list={(pl.data as ComicHomepageType).data}
                  showTag={
                    (pl?.name && POSTLIST_MAP?.[`${pl.module}-${pl.params.type}`]?.showTag) ||
                      pl.module === 'lists'
                      ? true
                      : false
                  }
                />

                {/* Banner - 1 */}
                {pl.module === 'indexLists' && ['最近更新', 'New Release'].includes(pl.name) && (
                  <div className="mb-4 w-full max-h-[310px] overflow-hidden max-w-screen-xl mx-auto">
                    {banners456?.[0]?.image && (
                      <NavLink
                        to={
                          banners456?.[0]?.mid > 0
                            ? `/content/${banners456?.[0]?.mid}`
                            : banners456?.[0]?.link || ""
                        }
                        target="_blank"
                      >
                        <Image
                          src={banners456?.[0]?.image}
                          alt="forum"
                          className="w-full"
                          imageSize="1440x300"
                        />
                      </NavLink>
                    )}
                  </div>
                )}

                {/* Banner - 2 */}
                {pl.module === 'indexLists' && ['精选推荐', 'Featured'].includes(pl.name) && (
                  <div className="mb-4 w-full max-h-[310px] overflow-hidden max-w-screen-xl mx-auto">
                    <div className="w-full">
                      {banners456?.[1]?.image && (
                        <NavLink
                          to={
                            banners456?.[1]?.mid > 0
                              ? `/content/${banners456?.[1]?.mid}`
                              : banners456?.[1]?.link || ""
                          }
                          target="_blank"
                        >
                          <Image
                            src={banners456?.[1]?.image}
                            alt="ribbon"
                            className="w-full"
                            imageSize="1440x300"
                          />
                        </NavLink>
                      )}
                    </div>
                  </div>
                )}
              </div>
            ))
          )
        })}
      </div>

      {/* Banner - 3 */}
      <div className="relative max-w-screen-xl mx-4 xl:mx-auto">
        {banners456?.[2]?.image && (
          <NavLink
            to={
              banners456?.[2]?.mid > 0
                ? `/content/${banners456?.[2]?.mid}`
                : banners456?.[2]?.link || ""
            }
            target="_blank"
          >
            <Image
              src={banners456?.[2]?.image}
              alt="housekeeping-banner"
              className="w-full"
              imageSize="1440x300"
            />
          </NavLink>
        )}
      </div>
    </div>
  );
};

export default Home;
