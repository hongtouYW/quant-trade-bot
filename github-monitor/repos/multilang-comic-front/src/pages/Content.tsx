import { useEffect, useState } from "react";
// import { Segmented } from "../components/Segmented";
// import ContentWorksIntro from "./modules/ContentWorksIntro";
// import { API_ENDPOINTS } from "../api/api-endpoint";
// import type { APIResponseType } from "../api/type";
// import { http } from "../api";
import { useNavigate, useParams, useSearchParams } from "react-router";
// import type { ComicContentType } from "./data";
// import { useComic } from "../contexts/comic.context";
import { useUser } from "../contexts/user.context";
// import CharacterIntroduction from "./modules/CharacterIntroduction";
import Pagination from "../components/Pagination";
import { useTranslation } from "react-i18next";
// import { ClassicImage } from "./modules/ClassicImage";
// import { toast } from "react-toastify";
import PurchaseChapterModal from "./user/modules/PurchaseChapterModal";
import InsufficientScoreModal from "./user/modules/InsufficientScoreModal";
import Image from "../components/Image";
import { humanizeNumber, isMobile, isMaxSmScreen, toFmt, isMaxMdScreen } from "../utils/utils";
import useComicRank from "../hooks/useComicRank";
import { COMIC_RANK_TYPE } from "../utils/constant";
import useComicDetail from "../hooks/useComicDetail";
import useComicCheckChapterStatus from "../hooks/useComicCheckChapterStatus";
import useComicFavorite from "../hooks/useComicFavorite";
import useComicUnFavorite from "../hooks/useComicUnfavorite";
import useAddHistory from "../hooks/useAddHistory";
import useComicChapterBuy from "../hooks/useComicChapterBuy";
import ComicDetailsModal from "../components/modules/ComicDetailsModal";

const mobileEpisodeDetailTab = [
  {
    title: "common.episodes",
    value: "episodes",
  },
  {
    title: "common.details",
    value: "details",
  },
];

// const segments = [
//   {
//     id: 1,
//     label: "common.worksIntroduction",
//     value: "1",
//     children: <ContentWorksIntro />,
//   },
//   {
//     id: 2,
//     label: "common.characterIntroduction",
//     value: "2",
//     children: <CharacterIntroduction />,
//   },
//   {
//     id: 3,
//     label: "common.classicImage",
//     value: "3",
//     children: <ClassicImage />,
//   },
// ];

const Content = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { comicId: id } = useParams();

  const [searchParams, setSearchParams] = useSearchParams();

  const { userInfo, setIsOpenUserAuthModal, refreshUserInfo } = useUser();

  const [active, setActive] = useState(1);
  const [isLoading, setIsLoading] = useState(false);
  const [currentChapter, setCurrentChapter] = useState<any>(null);

  const [activeEpisodeDetailTab, setActiveEpisodeDetailTab] = useState("episodes");
  // const [activeSegment, setActiveSegment] = useState<any>(segments?.[0]);
  const [openPointsModal, setOpenPointsModal] = useState<{
    open: boolean;
    type: "points" | "no_points";
  }>({
    open: false,
    type: "no_points",
  });
  const [openComicDetailsModal, setOpenComicDetailsModal] = useState(false);

  const { data: comicRankBestSeller } = useComicRank({
    type: COMIC_RANK_TYPE.BEST_SELLER,
  });

  // 获取漫画详情
  const [comicDetailParams, setComicDetailParams] = useState<{
    mid: string;
    page?: number;
    limit?: number;
    sort?: string;
  }>({
    mid: id || "",
    page: 1,
    limit: 10,
    sort: "asc",
  });
  const { data: comicInfo } = useComicDetail(comicDetailParams);
  // 获取章节状态
  const { data: chapterStatus, refetch: refetchChapterStatus } =
    useComicCheckChapterStatus({
      token: userInfo?.token,
      mid: id || "",
    });

  // 收藏
  const {
    mutate: handleFavorite,
    isSuccess: isSuccessFavorite,
    isPending: isPendingFavorite,
  } = useComicFavorite({
    token: userInfo?.token,
    mid: comicInfo?.comic?.id?.toString() || "",
  });
  // 取消收藏
  const {
    mutate: handleUnFavorite,
    isPending: isPendingUnFavorite,
    isSuccess: isSuccessUnFavorite,
  } = useComicUnFavorite({
    token: userInfo?.token,
    mid: comicInfo?.comic?.id?.toString() || "",
  });
  // 添加阅读记录
  const { mutate: handleAddHistory } = useAddHistory();
  // 购买章节
  const { mutate: handlePurchaseChapter, isSuccess: isSuccessPurchaseChapter } =
    useComicChapterBuy();

  // 点击收藏
  const handleClickFavorite = () => {
    if (isPendingFavorite) return;
    if (chapterStatus?.manhua?.is_favorite === 1) return;

    if (!userInfo?.id) {
      return setIsOpenUserAuthModal({
        type: "login",
        open: true,
      });
    } else {
      setIsLoading(true);
      handleFavorite();
    }
  };

  // 点击取消收藏
  const handleClickUnFavorite = () => {
    if (isPendingUnFavorite) return;
    if (chapterStatus?.manhua?.is_favorite === 0) return;

    if (!userInfo?.id) {
      return setIsOpenUserAuthModal({
        type: "login",
        open: true,
      });
    } else {
      setIsLoading(true);
      handleUnFavorite();
    }
  };

  // 确认购买
  const handleConfirmPurchase = (cid: string) => {
    const is_not_ask = document.querySelector(
      "input[name='is_not_ask']"
    ) as HTMLInputElement;
    if (is_not_ask?.checked) {
      localStorage.setItem("is_not_ask", "true");
    }

    handlePurchaseChapter({
      token: userInfo?.token,
      cid: cid,
    });
  };

  // 开始阅读
  const handleStartReading = (cid: string, index: number) => {
    navigate(`/chapter/${cid}?mid=${id}&chapter_index=${index}`);
    handleAddHistory({
      token: userInfo?.token,
      cid: cid,
    });
  };

  // 点击章节
  const handleClickChapter = (item: any) => {
    if (item?.isvip === 1 && !userInfo?.token) {
      setIsOpenUserAuthModal({
        open: true,
        type: "login",
      });
      return;
    }

    if (item?.isvip === 1 && userInfo?.id === 0) {
      return setIsOpenUserAuthModal({
        type: "login",
        open: true,
      });
    }

    setCurrentChapter(item);
    if (
      item?.isvip === 0 ||
      userInfo?.isvip_status === 1 ||
      chapterStatus?.purchased_chapter_ids?.includes(item?.id)
    ) {
      return handleStartReading(item?.id, item?.sort);
    }

    if (
      userInfo?.score === 0 ||
      (userInfo?.score && userInfo?.score < item?.score)
    ) {
      return setOpenPointsModal({
        type: "no_points",
        open: true,
      });
    } else {
      if (
        localStorage.getItem("is_not_ask") === "true" ||
        userInfo?.auto_buy === 1
      ) {
        return handleConfirmPurchase(item?.id);
      }

      return setOpenPointsModal({
        type: "points",
        open: true,
      });
    }
  };

  // 点击Segmented 切换模块
  // const handleClickSegmentOnChange = (value: any) => {
  //   const currentSegment = segments?.find((item) => {
  //     return item.value?.toString() === value;
  //   });

  //   setActiveSegment(currentSegment);
  // };

  // 分页
  const paginationHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    searchParams.set("page", num.toString());
    setSearchParams(searchParams);
  };

  // 排序
  const handleSort = () => {
    const sort = searchParams.get("sort") || "asc";
    searchParams.set("sort", sort === "asc" || sort === null ? "desc" : "asc");
    setSearchParams(searchParams);
  };

  const getLastViewTitleNumber = (str: string) => {
    const match = str.match(/\d+/);
    return match ? parseInt(match[0], 10) : 1;
  }

  // 当收藏或取消收藏时, 刷新章节状态
  useEffect(() => {
    if (isSuccessFavorite || isSuccessUnFavorite) {
      refetchChapterStatus().then((res) => {
        if (res?.isFetched) {
          setIsLoading(false);
        }
      });
    }
  }, [isSuccessFavorite, isSuccessUnFavorite, refetchChapterStatus]);

  // 当成功购买章节时, 刷新章节状态
  useEffect(() => {
    if (isSuccessPurchaseChapter) {
      setOpenPointsModal({
        open: false,
        type: "points",
      });
      refetchChapterStatus();
      refreshUserInfo();
      navigate(
        `/chapter/${currentChapter?.id}?mid=${id}&chapter_index=${currentChapter?.sort}`
      );
    }
  }, [isSuccessPurchaseChapter]);

  useEffect(() => {
    setComicDetailParams({
      mid: id || "",
      page: 1,
      limit: 10,
      sort: "asc",
    });
    window.scrollTo(0, 0);
  }, [id]);

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    const sort = searchParams.get("sort") || "asc";

    setComicDetailParams((prev: any) => ({
      ...prev,
      page: page,
      sort: sort || "asc",
    }));
    setActive(page);
  }, [searchParams]);

  return (
    <>
      <div id="content-page" className="max-w-screen-xl mx-auto mt-3 lg:mt-6 lg:px-4">
        <div className="flex flex-col gap-2 lg:flex-row lg:gap-6">
          <div className="aspect-[600/394] w-full max-h-[40vh] lg:w-[600px] lg:h-[394px] lg:max-h-[394px]">
            <Image
              className="w-full h-full rounded-none lg:rounded-2xl"
              src={comicInfo?.comic?.cover_horizontal || ""}
              alt="content-1"
              borderRadius="rounded-none"
              blurBg={true}
              loading="lazy"
              size={true}
              imageSize={isMaxSmScreen() ? "800x500" : "600x400"}
            />
          </div>

          {/* <div className="w-[40%] max-xs:hidden">
            <Segmented
              list={segments?.map((item) => ({
                ...item,
                label: t(item.label),
              }))}
              callback={handleClickSegmentOnChange}
            />

            {activeSegment?.children}
          </div> */}

          <div className="w-full p-4 lg:p-0">
            {/* title */}
            <p className="text-xl font-semibold leading-7 lg:text-2xl lg:leading-[32px]">
              <span className="line-clamp-4 lg:line-clamp-2" title={comicInfo?.comic?.title}>
                {comicInfo?.comic?.title}
              </span>
            </p>

            {/* views likes */}
            <div className="mt-1 flex items-center gap-3 text-sm leading-5 text-greyscale-500 lg:text-base lg:leading-6">
              <div className="flex items-center gap-1">
                <img
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/eye-outline.svg`}
                  alt="eye"
                />
                <p>
                  {humanizeNumber(comicInfo?.comic?.view || 0)}{" "}
                  {t("common.view")}
                </p>
              </div>

              <div className="flex items-center gap-1">
                <img
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/heart-outline.svg`}
                  alt="heart"
                />
                <p>
                  {humanizeNumber(comicInfo?.comic?.mark || 0)}
                </p>
              </div>
            </div>

            <div className="hidden mt-6 lg:flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold">
                {t("common.description")}
              </p>
              <p className="text-greyscale-500 font-normal line-clamp-1" title={comicInfo?.comic?.desc}>
                {comicInfo?.comic?.desc}
              </p>
            </div>

            <div className="hidden mt-6 lg:flex flex-col gap-1">
              <p className="text-greyscale-900 font-semibold">
                {t("common.latestUpdate1")}
              </p>
              <p className="text-greyscale-500 font-normal">
                {comicInfo?.comic?.last_chapter_title}
              </p>
            </div>

            <div className="hidden mt-6 lg:block">
              <button
                className="text-primary cursor-pointer"
                onClick={() => setOpenComicDetailsModal(true)}
              >
                {'... ' + t("common.filterMore")}
              </button>
            </div>

            {/* favorite continue buttons */}
            <div className="flex items-center gap-3 bg-white p-4 fixed bottom-0 left-0 right-0 z-[6] lg:static lg:p-0 lg:mt-6">
              {chapterStatus?.manhua?.is_favorite === 1 ? (
                <button
                  className={`bg-white text-primary border-1 border-primary px-4 py-3 rounded-xl w-1/3 font-medium flex items-center justify-center gap-2 ${isLoading
                    ? "cursor-not-allowed opacity-50 pointer-events-none"
                    : "cursor-pointer opacity-100"
                    }`}
                  onClick={handleClickUnFavorite}
                >
                  <img
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-heart-solid.png`}
                    alt="heart"
                    className="w-[15px]"
                  />
                  <p className="text-sm font-semibold">
                    {t("common.unFavorite")}
                  </p>
                </button>
              ) : (
                <button
                  className={`bg-white text-primary border-1 border-primary-dark px-4 py-3 rounded-xl w-1/3 font-semibold flex items-center justify-center gap-2 ${isLoading
                    ? "cursor-not-allowed opacity-50 pointer-events-none"
                    : "cursor-pointer opacity-100"
                    }`}
                  onClick={handleClickFavorite}
                >
                  <img
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-heart-outline.svg`}
                    alt="heart"
                    className="w-[15px]"
                  />
                  <p className="text-sm font-semibold">{t("common.favorite")}</p>
                </button>
              )}
              <button
                className="bg-primary text-white px-4 py-3 rounded-xl w-2/3 font-semibold cursor-pointer"
                onClick={() => {
                  if (!userInfo?.token) {
                    navigate(
                      `/chapter/${comicInfo?.chapters?.data?.[0]?.id}?mid=${comicInfo?.comic?.id}&chapter_index=${comicInfo?.chapters?.data?.[0]?.sort}`
                    );
                    return;
                  }
                  navigate(
                    `/chapter/${chapterStatus?.manhua?.last_view?.capter_id ? chapterStatus?.manhua?.last_view?.capter_id : comicInfo?.chapters?.data?.[0]?.id}?mid=${chapterStatus?.manhua?.last_view?.manhua_id ? chapterStatus?.manhua?.last_view?.manhua_id : comicInfo?.comic?.id}`
                  );
                }}
              >
                {chapterStatus?.manhua?.last_view?.title
                  ? t("common.continueFrom", { ep: getLastViewTitleNumber(chapterStatus.manhua.last_view.title) })
                  : t("common.readNow")}
              </button>
            </div>
          </div>
        </div>

        {/* Episode or Details */}
        <div className="items-center flex sticky top-14 z-[5] bg-white lg:hidden">
          {mobileEpisodeDetailTab?.map((item) => {
            return (
              <div
                className={`w-full border-b-2 cursor-pointer ${activeEpisodeDetailTab === item.value
                  ? "font-semibold border-primary text-primary"
                  : "border-greyscale-200 text-greyscale-900"
                  }`}
                key={item.value}
                onClick={() => setActiveEpisodeDetailTab(item.value)}
              >
                <p className="text-center text-sm py-3">{t(item.title)}</p>
              </div>
            );
          })}
        </div>

        {/* episodes  */}
        {activeEpisodeDetailTab === "episodes" && (
          <div className="flex gap-6">
            <div className="w-full lg:w-3/4">
              <div className="flex justify-between items-center pt-4 px-4 lg:px-0 lg:py-4 lg:mt-6">
                <h4 className="font-semibold lg:text-2xl lg:font-bold">
                  {t("common.readingList")}
                </h4>
                <div
                  className="flex items-center gap-1 cursor-pointer"
                  onClick={handleSort}
                >
                  <p className="text-sm text-primary font-semibold lg:text-xl">{t("common.sort")}</p>
                  <img
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-sort.svg`}
                    alt="sort"
                    className="w-5 h-5 lg:w-6 lg:h-6"
                  />
                </div>
              </div>

              <div className="mb-6 flex flex-col pt-4 px-4 gap-2 h-auto min-h-[540px] lg:mt-4 lg:p-0 lg:gap-4 lg:min-h-[688px]">
                {comicInfo?.chapters?.data?.map((item: any) => {
                  return (
                    <div
                      key={item?.id}
                      className="flex items-center gap-4 border-1 border-greyscale-200 p-2 rounded-lg w-full cursor-pointer hover:bg-white-smoke"
                      onClick={() => handleClickChapter(item)}
                    >
                      <div className="relative w-[78px] h-[109px] min-w-[78px] min-h-[109px] rounded-[4px] overflow-hidden lg:w-[100px] lg:h-[140px] lg:min-w-[100px] lg:min-h-[140px]">
                        <Image
                          className="w-full h-full"
                          src={comicInfo?.comic?.image}
                          alt={item?.title}
                          borderRadius={isMobile() ? "rounded-[4px]" : "rounded-sm"}
                          size={true}
                          imageSize={isMaxSmScreen() || isMaxMdScreen() ? "160x220" : "200x280"}
                          loading="lazy"
                        />

                        {(item?.isvip === 1 && (!userInfo?.token || (userInfo?.isvip_status === 0 && !chapterStatus?.purchased_chapter_ids?.includes(item?.id)))) && (
                          <div className={`absolute top-0 left-0 right-0 bottom-0 w-[78px] h-[109px] min-w-[78px] min-h-[109px] bg-black/70 flex items-center justify-center rounded-[4px] lg:w-[100px] lg:h-[140px] lg:min-w-[100px] lg:min-h-[140px]`}>
                            <img
                              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-lock-white.svg`}
                              alt="lock"
                              className="w-6 h-6"
                            />
                          </div>
                        )}
                      </div>

                      <div className="flex flex-1 items-center gap-4 justify-between">
                        <div className="flex flex-col gap-1">
                          <div className="flex items-center gap-1">
                            <p className="text-sm font-semibold truncate leading-4 text-greyscale-900 lg:text-base lg:leading-6">
                              {t("common.episode", {
                                ep: item.max_sort || item?.sort,
                              })}
                            </p>
                            {chapterStatus?.viewed_chapter_ids?.includes(
                              item?.id
                            ) && (
                                <img
                                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                                    }/assets/images/read.svg`}
                                  alt="read"
                                  className="w-3 h-3"
                                />
                              )}
                          </div>

                          <p className="text-xs text-greyscale-500 leading-[18px] h-9 lg:text-sm">
                            <span className="line-clamp-2">
                              {item?.title}
                            </span>
                          </p>

                          <div className="flex items-center gap-1 text-xs text-greyscale-500">
                            <img
                              src={`${import.meta.env.VITE_INDEX_DOMAIN
                                }/assets/images/icon-calendar-days-2.svg`}
                              className="w-5 h-5"
                              alt="days"
                            />
                            <p className="text-xs lg:text-sm">
                              {toFmt(item?.update_time || 0, "YYYY/MM/DD")}
                            </p>
                          </div>
                        </div>
                        <div
                          className={`font-semibold rounded-xl min-w-max text-sm px-4 py-3 lg:px-6 lg:text-base ${item?.isvip === 0 ||
                            comicInfo?.comic?.xianmian === 1
                            ? "cursor-pointer text-primary border-1 border-primary "
                            : chapterStatus?.purchased_chapter_ids?.includes(
                              item?.id
                            ) || userInfo?.isvip_status === 1
                              ? "cursor-pointer text-[#C29553] border-1 border-[#C29553] "
                              : "cursor-pointer text-primary border-1 border-primary"
                            }`}
                        >
                          {item?.isvip === 0 || comicInfo?.comic?.xianmian === 1
                            ? t("common.free1")
                            : item?.isvip === 1 && item?.score > 0
                              ? chapterStatus?.purchased_chapter_ids?.includes(
                                item?.id
                              ) || userInfo?.isvip_status === 1
                                ? t("common.consumed")
                                : `${item?.score} ${t("common.point")}`
                              : ""}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>

              {comicInfo?.chapters?.last_page &&
                comicInfo?.chapters?.last_page > 1 && (
                  <div className="mb-10">
                    <Pagination
                      active={active}
                      size={comicInfo?.chapters?.last_page || 1}
                      step={1}
                      total={comicInfo?.chapters?.total || 0}
                      onClickHandler={paginationHandler}
                    />
                  </div>
                )}
            </div>

            {/* top ranking */}
            <div className="hidden w-1/4 lg:block">
              <div className="flex justify-between items-center pt-4 px-4 lg:px-0 lg:py-4 lg:mt-6">
                <h4 className="font-semibold lg:text-2xl lg:font-bold">
                  {t("ranking.topRanking")}
                </h4>
              </div>

              <div className="mt-4 flex flex-col gap-6">
                {(comicRankBestSeller?.data?.slice(0, 12) || [])?.map(
                  (item: any, index: number) => {
                    return (
                      <div
                        key={item.id}
                        className="flex items-start bg-white rounded-lg shadow-md overflow-hidden cursor-pointer"
                        onClick={() => {
                          navigate(`/content/${item.id}`);
                        }}
                      >
                        <div className="flex-shrink-0">
                          <Image
                            className="w-[100px] h-[148px] object-cover"
                            src={
                              item?.image ||
                              `${import.meta.env.VITE_INDEX_DOMAIN
                              }/assets/images/content-1.png`
                            }
                            alt={item.title}
                            size={true}
                            imageSize={"200x300"}
                            loading="lazy"
                          />
                        </div>

                        <div className="flex flex-col gap-1 p-3">
                          <div className={`font-bold text-[40px]
                            ${index + 1 === 1 && 'text-[#FE9901]'}
                            ${index + 1 === 2 && 'text-[#649CD8]'}
                            ${index + 1 === 3 && 'text-[#C37150]'}
                            ${index && index + 1 > 3 && 'text-greyscale-600'}
                            `}
                          >
                            {index + 1}
                          </div>

                          <p className="font-semibold text-greyscale-800 line-clamp-1">
                            {item.title}
                          </p>

                          <div className="flex items-center gap-1">
                            <span className="text-sm text-primary">
                              {t("common.ep", {
                                ep: item.max_sort || item?.sort,
                              })}
                            </span>
                            <div className="flex items-center">
                              <img
                                src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/eye.svg`}
                                alt="views"
                                className="w-6 h-6"
                              />
                              <span className="text-xs text-greyscale-500">
                                {item.view ? humanizeNumber(item.view) : "0"}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  }
                )}
              </div>
            </div>
          </div>
        )}

        {/* mobile details */}
        {activeEpisodeDetailTab === "details" && (
          <div className="p-4 lg:hidden">
            <div className="flex flex-col gap-4 mb-10">
              <div className="flex flex-col gap-2">
                <p className="text-sm text-greyscale-900 font-semibold leading-4">
                  {t("common.author")}
                </p>
                <p className="text-sm text-greyscale-500 font-normal leading-4">
                  {comicInfo?.comic?.auther}
                </p>
              </div>
              <div className="flex flex-col gap-2">
                <p className="text-sm text-greyscale-900 font-semibold leading-4">
                  {t("common.description")}
                </p>
                <p className="text-sm text-greyscale-500 font-normal leading-4">
                  {comicInfo?.comic?.desc}
                </p>
              </div>
              <div className="flex flex-col gap-2">
                <p className="text-sm text-greyscale-900 font-semibold leading-4">
                  {t("common.updatedDate")}
                </p>
                <p className="text-sm text-greyscale-500 font-normal leading-4">
                  {toFmt((comicInfo?.comic?.update_time || 0) * 1000 || 0, "DD/MM/YYYY")}
                </p>
              </div>
              <div className="flex flex-col gap-2">
                <p className="text-sm text-greyscale-900 font-semibold leading-4">
                  {t("common.latestUpdate1")}
                </p>
                <p className="text-sm text-greyscale-500 font-normal leading-4">
                  {comicInfo?.comic?.last_chapter_title}
                </p>
              </div>
              <div className="flex flex-col gap-2">
                <p className="text-sm text-greyscale-900 font-semibold leading-4">
                  {t("common.genre")}
                </p>
                <div className="mt-1">
                  <p className="text-xs text-greyscale-600 py-[6px] px-2 bg-greyscale-200 rounded-md w-max">
                    #{comicInfo?.comic?.ticai_name}
                  </p>
                </div>
              </div>
              <div className="flex flex-col gap-2">
                <p className="text-sm text-greyscale-900 font-semibold leading-4">
                  {t("common.keywords")}
                </p>
                <div className="mt-1">
                  <p className="text-xs text-greyscale-600 py-[6px] px-2 bg-greyscale-200 rounded-md w-max max-w-[280px] truncate">
                    #{comicInfo?.comic?.keyword}
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      <PurchaseChapterModal
        open={openPointsModal?.open && openPointsModal?.type === "points"}
        onClose={() =>
          setOpenPointsModal((prev: any) => ({ ...prev, open: false }))
        }
        onConfirm={() => handleConfirmPurchase(currentChapter?.id)}
        purchaseChapter={currentChapter}
        userInfo={userInfo}
      />

      <InsufficientScoreModal
        open={openPointsModal?.open && openPointsModal?.type === "no_points"}
        onClose={() =>
          setOpenPointsModal((prev: any) => ({ ...prev, open: false }))
        }
        purchaseChapter={currentChapter}
        userInfo={userInfo}
      />

      <ComicDetailsModal
        open={openComicDetailsModal}
        onClose={() => setOpenComicDetailsModal(false)}
        comicInfo={comicInfo}
      />
    </>
  );
};

export default Content;
