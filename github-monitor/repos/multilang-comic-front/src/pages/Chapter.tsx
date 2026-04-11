import { useEffect, useRef, useState, useCallback } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router";
import type { APIResponseType } from "../api/type";
import { http } from "../api";
import { API_ENDPOINTS } from "../api/api-endpoint";
import { ToastContainer, Slide } from "react-toastify";
import { throttle, uniqBy } from "lodash";
// import Footer from "../components/Footer";
import { useUser } from "../contexts/user.context";
import FloatButton from "../components/FloatButton";
import PurchaseChapterModal from "./user/modules/PurchaseChapterModal";
import { useTranslation } from "react-i18next";
import Image from "../components/Image";
import i18n from "../utils/i18n";
import InsufficientScoreModal from "./user/modules/InsufficientScoreModal";
import useAddHistory from "../hooks/useAddHistory";
import useComicChapterBuy from "../hooks/useComicChapterBuy";
import useComicCheckChapterStatus from "../hooks/useComicCheckChapterStatus";
import {
  useComicChapterInfo,
  useComicChapterInfoMutation,
} from "../hooks/useComicChapterInfo";
// import useComicFavorite from "../hooks/useComicFavorite";

import { getToken, isMaxSmScreen } from "../utils/utils";

const Chapter = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const { id } = useParams();

  const pageSize = 50;
  const [searchParams] = useSearchParams();
  const { userInfo, setIsOpenUserAuthModal, refreshUserInfo } = useUser();

  const myDivRef = useRef<HTMLDivElement>(null);
  const chapterListRef = useRef<HTMLDivElement>(null);
  const hasInitializedRef = useRef(false);
  // 添加手动导航标志，防止滚动事件覆盖状态
  const isManualNavigationRef = useRef(false);

  const [allChapters, setAllChapters] = useState<any>([]);
  const [currentPage, setCurrentPage] = useState(0);
  const [showList, setShowList] = useState(false);

  const [purchaseChapter, setPurchaseChapter] = useState<any>({});
  const [openPointsModal, setOpenPointsModal] = useState<{
    open: boolean;
    type: "points" | "no_points";
    chapterId?: string;
  }>({
    open: false,
    type: "no_points",
    chapterId: "",
  });

  // 获取章节状态
  const { data: chapterStatus, refetch: refetchChapterStatus } =
    useComicCheckChapterStatus({
      token: userInfo?.token || getToken(),
      mid: searchParams.get("mid") || "",
    });

  // 获取章节信息
  const {
    data: chapterInfo,
    isSuccess: isSuccessChapterInfo,
    isFetched: isFetchedChapterInfo,
  } = useComicChapterInfo({
    cid: id || "",
  });
  // console.log("chapterInfo", chapterInfo);

  // 获取漫画信息
  const {
    mutate: manualHandleGetChapterInfo,
    data: manualChapterInfo,
    isSuccess: isSuccessManualChapterInfo,
  } = useComicChapterInfoMutation();
  // 添加阅读记录
  const { mutate: handleAddHistory } = useAddHistory();

  // 购买章节
  const {
    mutate: handlePurchaseChapter,
    isSuccess: isSuccessPurchaseChapter,
    data: purchaseChapterData,
  } = useComicChapterBuy();

  // // 收藏
  // const { mutate: handleFavorite, isSuccess: isSuccessFavorite } =
  //   useComicFavorite({
  //     token: userInfo?.token,
  //     mid: searchParams.get("mid") || "",
  //   });

  // const { data: comicInfo, isSuccess: isSuccessComicInfo } = useComicDetail({
  //   mid: searchParams.get("mid") || "",
  //   limit: pageSize,
  //   page: Math.ceil(Number(searchParams.get("chapter_index")) / pageSize) || 1,
  // });
  // 滚动到指定图片
  const handleScrollToImage = (
    index: number,
    behavior: "smooth" | "instant" = "smooth",
    block: "start" | "center" = "center"
  ) => {
    const currentImage = document.getElementById(`image-${index}`);
    if (currentImage) {
      currentImage.scrollIntoView({
        behavior: behavior,
        block: block,
        inline: "nearest",
      });
    }
  };

  // 上一页
  const handlePrevPage = () => {
    if (currentPage > 0) {
      const prevPage = currentPage - 1;
      // 设置手动导航标志
      isManualNavigationRef.current = true;
      setCurrentPage(prevPage);

      // 延迟重置标志，给滚动动画足够时间
      setTimeout(() => {
        isManualNavigationRef.current = false;
      }, 500);

      handleScrollToImage(prevPage);
    }
  };

  // 下一页
  const handleNextPage = () => {
    if (currentPage < chapterInfo?.chaInfo?.imagelist?.length - 1) {
      const nextPage = currentPage + 1;
      // 设置手动导航标志
      isManualNavigationRef.current = true;
      setCurrentPage(nextPage);

      // 延迟重置标志，给滚动动画足够时间
      setTimeout(() => {
        isManualNavigationRef.current = false;
      }, 500);

      handleScrollToImage(nextPage);
    }
  };

  // 上一话
  const handlePrevChapter = () => {
    const mid = searchParams.get("mid");
    const currentChapter = allChapters?.findIndex((item: any) => item?.id?.toString() === id?.toString());
    const prevChapter = chapterInfo?.prevChaInfo || allChapters?.[currentChapter - 1] || null;
    const prevChapterId = prevChapter?.id;

    if (prevChapter?.isvip === 1) {
      if (
        chapterStatus?.purchased_chapter_ids?.includes(prevChapterId) ||
        userInfo?.isvip_status === 1
      ) {
        navigate(
          `/chapter/${prevChapterId}?mid=${mid}&chapter_index=${prevChapter?.sort}`
        );
        handleAddHistory({
          token: userInfo?.token,
          cid: prevChapterId,
        });
        return;
      } else {
        if (
          (prevChapter?.score > userInfo && userInfo?.score) ||
          userInfo?.score === 0
        ) {
          setOpenPointsModal({
            open: true,
            type: "no_points",
            chapterId: prevChapterId,
          });
          setPurchaseChapter(prevChapter);
          return;
        } else {
          if (
            userInfo?.auto_buy === 1 ||
            localStorage.getItem("is_not_ask") === "true"
          ) {
            handleConfirmPurchase(prevChapterId);
            return;
          }
          setOpenPointsModal({
            open: true,
            type: "points",
            chapterId: prevChapterId,
          });
          setPurchaseChapter(prevChapter);
          return;
        }
      }
    } else {
      navigate(
        `/chapter/${prevChapterId}?mid=${mid}&chapter_index=${prevChapter?.sort}`
      );
      handleAddHistory({
        token: userInfo?.token,
        cid: prevChapterId,
      });
    }
  };

  // 下一话
  const handleNextChapter = () => {
    const mid = searchParams.get("mid");
    const currentChapter = allChapters?.findIndex((item: any) => item?.id?.toString() === id?.toString());
    const nextChapter = chapterInfo?.nextChaInfo || allChapters?.[currentChapter + 1] || null;
    const nextChapterId = nextChapter?.id;

    if (nextChapter?.isvip === 1) {
      if (
        chapterStatus?.purchased_chapter_ids?.includes(nextChapterId) ||
        userInfo?.isvip_status === 1
      ) {
        navigate(
          `/chapter/${nextChapterId}?mid=${mid}&chapter_index=${nextChapter?.sort}`
        );
        handleAddHistory({
          token: userInfo?.token,
          cid: nextChapterId,
        });
        return;
      } else {
        if (
          (nextChapter?.score > userInfo && userInfo?.score) ||
          userInfo?.score === 0
        ) {
          setOpenPointsModal({
            open: true,
            type: "no_points",
            chapterId: nextChapterId,
          });
          setPurchaseChapter(nextChapter);
          return;
        } else {
          if (
            userInfo?.auto_buy === 1 ||
            localStorage.getItem("is_not_ask") === "true"
          ) {
            handleConfirmPurchase(nextChapterId);
            return;
          }
          setOpenPointsModal({
            open: true,
            type: "points",
            chapterId: nextChapterId,
          });
          setPurchaseChapter(nextChapter);
          return;
        }
      }
    } else {
      navigate(
        `/chapter/${nextChapterId}?mid=${mid}&chapter_index=${nextChapter?.sort}`
      );
      handleAddHistory({
        token: userInfo?.token,
        cid: nextChapterId,
      });
    }
  };

  // 回顶部
  const handleBackToTop = () => {
    document
      .getElementById("image-0")
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
    document
      .getElementById("fullScreenImage-0")
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  // 点击章节
  useEffect(() => {
    if (isSuccessManualChapterInfo) {
      const chapterInfo = manualChapterInfo?.chaInfo;
      if (
        chapterInfo?.isvip === 0 ||
        chapterStatus?.purchased_chapter_ids?.includes(chapterInfo?.id) ||
        userInfo?.isvip_status === 1
      ) {
        setShowList(false);
        // 设置手动导航标志
        isManualNavigationRef.current = true;
        setCurrentPage(0);

        // 延迟重置标志
        setTimeout(() => {
          isManualNavigationRef.current = false;
        }, 1000); // 给页面跳转足够时间

        navigate(
          `/chapter/${chapterInfo?.id}?mid=${searchParams.get(
            "mid"
          )}&chapter_index=${chapterInfo?.sort}`
        );
      } else {
        if (
          (chapterInfo?.chaInfo?.score > userInfo && userInfo?.score) ||
          userInfo?.score === 0
        ) {
          setOpenPointsModal({
            open: true,
            type: "no_points",
          });

          return;
        } else {
          if (
            userInfo?.auto_buy === 1 ||
            localStorage.getItem("is_not_ask") === "true"
          ) {
            handleConfirmPurchase(chapterInfo?.id);
            return;
          }
          setOpenPointsModal({
            open: true,
            type: "points",
            chapterId: chapterInfo?.id,
          });
          setPurchaseChapter(chapterInfo);
        }
      }
    }
  }, [isSuccessManualChapterInfo, manualChapterInfo]);

  // 获取作品信息
  const handleGetComicInfo = async (mid: string, page?: number) => {
    if (!mid) return;

    try {
      const params = {
        mid: mid,
        page:
          page ||
          Math.ceil(Number(searchParams.get("chapter_index")) / pageSize) ||
          1,
        limit: pageSize,
      };
      // console.log("params-handleGetComicInfo", params);
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.comicInfo,
        params
      );
      // console.log("res-comic", res);
      if (res?.data?.code === 1) {
        const data = res?.data?.data;

        return data;
      }
    } catch (err) {
      console.error("err", err);
    }
  };

  const initialGetComicInfo = async (mid: string) => {
    const data = await handleGetComicInfo(mid);
    if (
      data?.chapters?.total ===
      data?.chapters?.data[data?.chapters?.data?.length - 1]?.sort
    ) {
      const result = await handleGetComicInfo(
        mid,
        data?.chapters?.last_page - 1
      );
      const resultData = result?.chapters?.data;
      const dataData = data?.chapters?.data;

      setAllChapters(uniqBy([...resultData, ...dataData], "id"));
    } else {
      if (data?.chapters?.data) {
        const chaptersData = data.chapters.data;
        if (chaptersData.length > 0) {
          const uniqueChapters = uniqBy(chaptersData, "id");
          setAllChapters(uniqueChapters);
        }
      }
    }
  };

  // 滚动事件处理 - 简化的版本
  const handleScroll = useCallback(() => {
    // 如果是手动导航，不处理滚动事件
    if (isManualNavigationRef.current) {
      return;
    }

    if (myDivRef.current && chapterInfo?.chaInfo?.imagelist) {
      const container = myDivRef.current;
      const containerRect = container.getBoundingClientRect();
      const images = chapterInfo.chaInfo.imagelist;

      let currentVisibleImageIndex = 0;

      // 找到当前可见的图片
      for (let i = 0; i < images.length; i++) {
        const imageElement = document.getElementById(`image-${i}`);
        if (imageElement) {
          const imageRect = imageElement.getBoundingClientRect();

          // 检查图片是否在容器可视范围内
          const isVisible =
            imageRect.top <= containerRect.bottom &&
            imageRect.bottom >= containerRect.top;

          if (isVisible) {
            currentVisibleImageIndex = i;
            break;
          }
        }
      }

      // 更新当前页面
      setCurrentPage(currentVisibleImageIndex);
    }
  }, [chapterInfo?.chaInfo?.imagelist]);

  // 节流版本的滚动处理
  const throttledScrollHandler = useCallback(throttle(handleScroll, 200), [
    handleScroll,
  ]);

  // 点击收藏
  // const handleClickFavorite = () => {
  //   if (chapterStatus?.manhua?.is_favorite === 1) return;
  //   if (!userInfo?.token) {
  //     return setIsOpenUserAuthModal({
  //       type: "login",
  //       open: true,
  //     });
  //   }

  //   handleFavorite();
  // };

  // 确认购买
  const handleConfirmPurchase = async (cid?: string) => {
    const is_not_ask = document.querySelector(
      "input[name='is_not_ask']"
    ) as HTMLInputElement;
    if (is_not_ask?.checked) {
      localStorage.setItem("is_not_ask", "true");
    }
    if (!userInfo?.token) {
      setIsOpenUserAuthModal({
        open: true,
        type: "login",
      });
      return;
    }

    handlePurchaseChapter({
      token: userInfo?.token,
      cid: cid || "",
    });
  };

  // 章节列表滚动
  const handleChapterListScroll = async (type: "top" | "bottom") => {
    if (type === "bottom") {
      const listLastChapterSort =
        allChapters && allChapters[allChapters.length - 1]?.sort;

      const page =
        listLastChapterSort && Math.ceil(listLastChapterSort / pageSize) + 1;
      // console.log("page", page);
      if (page > 1) {
        try {
          const params = {
            mid: searchParams.get("mid"),
            page,
            limit: pageSize,
          };
          // console.log("params", params);
          const res = await http.post<APIResponseType>(
            API_ENDPOINTS.comicInfo,
            params
          );
          // console.log("res-comic", res);
          if (res?.data?.code === 1) {
            const data = res?.data?.data;

            setAllChapters((prev: any) => {
              return uniqBy([...prev, ...data.chapters.data], "id");
            });
          }
        } catch (error) {
          console.error("error", error);
        }
      }
    } else {
      const listFirstChapterSort = allChapters && allChapters[0]?.sort;
      const page =
        listFirstChapterSort && Math.ceil(listFirstChapterSort / pageSize) - 1;
      if (page >= 1) {
        try {
          const params = {
            mid: searchParams.get("mid"),
            page,
            limit: pageSize,
          };
          // console.log("params", params);
          const res = await http.post<APIResponseType>(
            API_ENDPOINTS.comicInfo,
            params
          );
          // console.log("res-comic", res);
          if (res?.data?.code === 1) {
            const data = res?.data?.data;
            setAllChapters((prev: any) => {
              return uniqBy([...data.chapters.data, ...prev], "id");
            });

            const currentChapter = document.getElementById(
              `chapter-${allChapters[0]?.id}`
            );
            if (currentChapter) {
              currentChapter.scrollIntoView();
            }
          }
        } catch (error) {
          console.error("error", error);
        }
      }
    }
  };

  useEffect(() => {
    if (isSuccessChapterInfo && isFetchedChapterInfo) {
      setShowList(false);
      setCurrentPage(0);

      handleScrollToImage(0, "instant", "start");
    }
  }, [id, isSuccessChapterInfo, isFetchedChapterInfo]);

  // 当收藏或取消收藏时, 刷新章节状态
  // useEffect(() => {
  //   if (isSuccessFavorite) {
  //     refetchChapterStatus();
  //   }
  // }, [isSuccessFavorite, refetchChapterStatus]);

  // 当成功购买章节时, 刷新章节状态
  useEffect(() => {
    if (isSuccessPurchaseChapter) {
      const id = purchaseChapterData?.data?.data?.chaInfo?.id;

      setOpenPointsModal({
        open: false,
        type: "points",
      });
      setShowList(false);
      refetchChapterStatus();
      refreshUserInfo();
      navigate(
        `/chapter/${id}?mid=${searchParams.get("mid")}&chapter_index=${purchaseChapterData?.data?.data?.chaInfo?.sort
        }`
      );
    }
  }, [isSuccessPurchaseChapter]);

  // 列表滚动
  useEffect(() => {
    if (showList) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "auto";
    }
    // 👇 cleanup on unmount
    return () => {
      document.body.style.overflow = "auto";
    };
  }, [showList]);

  useEffect(() => {
    const mid = searchParams.get("mid");

    if (mid && !hasInitializedRef.current) {
      hasInitializedRef.current = true;
      initialGetComicInfo(mid);
    }
  }, [searchParams]);

  // 滚动
  useEffect(() => {
    const div = myDivRef.current; // capture current element

    if (div) {
      div.addEventListener("scroll", throttledScrollHandler);
    }

    return () => {
      if (div) {
        div.removeEventListener("scroll", throttledScrollHandler);
      }
    };
  }, [chapterInfo?.chaInfo?.imagelist, throttledScrollHandler]);

  // 组件卸载时清理手动导航标志
  useEffect(() => {
    return () => {
      isManualNavigationRef.current = false;
    };
  }, []);

  // 全屏滚动
  // useEffect(() => {
  //   if (myFullScreenDivRef.current) {
  //     myFullScreenDivRef?.current?.addEventListener("scroll", () =>
  //       handleScroll(myFullScreenDivRef, "fullScreenImage")
  //     );
  //   }

  //   return () => {
  //     if (myFullScreenDivRef?.current) {
  //       myFullScreenDivRef?.current?.removeEventListener("scroll", () =>
  //         handleScroll(myFullScreenDivRef, "fullScreenImage")
  //       );
  //     }
  //   };
  // }, [isFullScreen]);

  useEffect(() => {
    const language = localStorage.getItem("language");
    if (language) {
      i18n.changeLanguage(language);
    }
  }, []);

  // 章节列表滚动监听
  useEffect(() => {
    const div = chapterListRef.current; // capture the element at this render

    const handleScroll = () => {
      if (!div) return;

      const { scrollTop, scrollHeight, clientHeight } = div;

      if (scrollTop === 0) {
        handleChapterListScroll("top");
      }

      if (scrollTop + clientHeight >= scrollHeight - 1) {
        handleChapterListScroll("bottom");
      }
    };

    if (div) {
      div.addEventListener("scroll", handleScroll);
    }

    return () => {
      if (div) {
        div.removeEventListener("scroll", handleScroll);
      }
    };
  }, [showList, allChapters]);

  useEffect(() => {
    const currentChapter = document.getElementById(`chapter-${id}`);
    if (currentChapter) {
      currentChapter.scrollIntoView({ block: "center" });
    }
  }, [id, showList]);

  return (
    <>
      <div className="relative">
        {/* Header - 桌面版使用 sticky，手机版使用 fixed */}
        <div className="flex justify-center gap-10 bg-greyscale-900 p-4 text-white fixed top-0 left-0 right-0 z-10 lg:p-6">
          <div className="flex items-center justify-between gap-4 w-full max-w-5xl mx-auto">
            <div className="flex gap-2 lg:gap-4">
              <div className="w-6 h-6 shrink-0 flex items-center justify-center cursor-pointer" onClick={() => {
                navigate(`/content/${chapterInfo?.comicInfo?.id}`);
              }}>
                <img
                  className="w-3"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-arrow-left-white.svg`}
                  alt="arrow-left"

                />
              </div>
              <div className="flex flex-col lg:gap-1">
                <p className="text-sm leading-5 font-semibold lg:text-xl lg:font-bold">
                  <span className="line-clamp-1">
                    {t("common.episode", {
                      ep:
                        chapterInfo?.chaInfo?.max_sort ||
                        chapterInfo?.chaInfo?.sort ||
                        0,
                    })}
                  </span>
                </p>
                <p className="text-xs lg:text-sm">
                  <span className="line-clamp-1">
                    {chapterInfo?.comicInfo?.title}
                  </span>
                </p>
              </div>
            </div>
            <div className="flex items-center gap-3 lg:gap-6">
              <div className="flex flex-col items-center cursor-pointer">
                <div className="w-5 h-5 shrink-0 flex items-center justify-center cursor-pointer" onClick={() => {
                  navigate("/");
                }}>
                  <img
                    className="w-5"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-home.svg`}
                    alt="arrow-right"
                  />
                </div>
                <p className="text-xs">
                  {t("common.home")}
                </p>
              </div>
              {allChapters?.length > 1 && (
                <div
                  className="flex flex-col items-center cursor-pointer"
                  onClick={() => {
                    setShowList((prev: any) => !prev);
                  }}
                >
                  <div className="w-5 h-5 shrink-0 flex items-center justify-center">
                    <img
                      className="w-5"
                      src={`${import.meta.env.VITE_INDEX_DOMAIN
                        }/assets/images/icon-order-list.svg`}
                      alt="inbox"
                    />
                  </div>
                  <p className="text-xs">
                    {t("common.list")}
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* 图片列表容器 */}
        <div className="mt-[68px] flex relative z-[1] w-full lg:mt-[92px]">
          <div className="flex-5 overflow-hidden h-[calc(100vh-68px-52px)] lg:h-[calc(100vh-92px-76px)]">
            <div
              className="scroll-smooth h-full overflow-y-auto"
              ref={myDivRef}
            >
              {!chapterInfo?.chaInfo?.imagelist ? (
                <div className="flex items-center justify-center h-32 text-gray-500">
                  <p>Loading images...</p>
                </div>
              ) : chapterInfo?.chaInfo?.imagelist?.length === 0 ? (
                <div className="flex items-center justify-center h-32 text-gray-500">
                  <p>No images found</p>
                </div>
              ) : (
                chapterInfo?.chaInfo?.imagelist?.map(
                  (item: any, index: number) => {
                    return (
                      <div
                        key={item}
                        className="image-container w-full block mx-auto xs:max-w-sm lg:max-w-xl xl:max-w-2xl"
                      >
                        <Image
                          className="w-full object-cover block"
                          src={item}
                          alt="cover"
                          loading="lazy"
                          id={`image-${index}`}
                          blurBg={false}
                          borderRadius="rounded-sm"
                          size={false}
                          imageSize={"640x2400"}
                          scrollContainerRef={myDivRef}
                          eagerLoad={index < 5}
                        />
                      </div>
                    );
                  }
                )
              )}
            </div>
          </div>
        </div>
        <div className="fixed bg-greyscale-900 bottom-0 w-full p-4 text-white z-10 lg:p-6">
          <div className="mx-auto w-full flex justify-between gap-4 max-w-5xl lg:justify-center lg:gap-6">
            <div
              className={`flex items-center gap-2 ${allChapters?.findIndex(
                (item: any) => item?.id?.toString() === id?.toString()
              ) > 0
                ? "pointer-events-auto opacity-100"
                : "pointer-events-none opacity-20 cursor-not-allowed"
                }`}
              onClick={() => handlePrevChapter()}
              style={{
                cursor: "pointer",
                userSelect: "none",
                WebkitUserSelect: "none",
                touchAction: "manipulation",
              }}
            >
              <img
                className="w-5 h-5 lg:w-6 lg:h-6"
                src={`${import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/arrow-circle-up.svg`}
                alt="up"
              />
              <p className="text-sm font-semibold lg:text-xl">
                {t("common.previousChapter1")}
              </p>
            </div>
            <div className="flex items-center justify-center gap-4">
              <div
                className={`flex items-center gap-1 ${currentPage + 1 === 1 ? 'cursor-auto' : 'cursor-pointer'}`}
                onClick={() => handlePrevPage()}
              >
                <img
                  className={`w-3 h-3 ${currentPage + 1 === 1 ? 'opacity-20' : ''}`}
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-arrow-left-white.svg`}
                  alt="up"
                />
                <p className={`text-sm font-semibold ${currentPage + 1 === 1 ? 'text-greyscale-700' : ''} lg:text-xl`}>
                  {t("common.previousPage1")}
                </p>
              </div>
              <p className="text-sm lg:text-xl">
                {currentPage + 1}/{chapterInfo?.chaInfo?.imagelist?.length}
              </p>
              <div
                className={`flex items-center gap-1 cursor-pointer`}
                onClick={() => handleNextPage()}
              >
                <p className="text-sm font-semibold lg:text-xl">
                  {t("common.nextPage1")}
                </p>
                <img
                  className="w-3 h-3"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-arrow-right-white.svg`}
                  alt="up"
                />
              </div>
            </div>
            <div
              className={`flex items-center gap-2 ${allChapters?.findIndex(
                (item: any) => item?.id?.toString() === id?.toString()
              ) <
                allChapters?.length - 1
                ? "pointer-events-auto opacity-100"
                : "pointer-events-none opacity-20 cursor-not-allowed"
                }`}
              onClick={() => handleNextChapter()}
              style={{
                cursor: "pointer",
                userSelect: "none",
                WebkitUserSelect: "none",
                touchAction: "manipulation",
              }}
            >
              <p className="text-sm lg:text-xl">
                {t("common.nextChapter1")}
              </p>
              <img
                className="w-5 h-5 lg:w-6 lg:h-6"
                src={`${import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/arrow-circle-down.svg`}
                alt="up"
              />
            </div>
          </div>
        </div>
      </div>

      {/* chapter list */}
      <div
        className={`bg-[#212121]/90 transition-all duration-300 h-full w-full z-20 flex justify-end ${showList ? "-translate-x-0 fixed top-0 right-0" : "translate-x-full"
          }`}
        onClick={(e) => {
          e.stopPropagation();
          setShowList(false);
        }}
      >
        <div
          ref={chapterListRef}
          className="overflow-y-auto w-max bg-[#212121] h-full px-5"
          onClick={(e) => {
            e.stopPropagation();
          }}
        >
          {showList &&
            allChapters?.map((item: any) => {
              const isCurrentChapter = id?.toString() === item?.id?.toString();

              return (
                <div
                  key={item?.id}
                  id={`chapter-${item?.id}`}
                  className={`relative w-[218px] h-[120px] rounded-lg my-5 max-xs:w-[130px] max-xs:h-[70px] cursor-pointer ${isCurrentChapter
                    ? "border-2 border-[#F54336]"
                    : "border-2 border-greyscale-500"
                    } `}
                  onClick={() => manualHandleGetChapterInfo({ cid: item?.id })}
                >
                  {/* <img
                    className="w-full h-full object-cover rounded-md"
                    src={item?.image}
                    alt="cover"
                  /> */}
                  <Image
                    className="w-full h-full object-cover rounded-md"
                    src={chapterInfo?.comicInfo?.cover_horizontal || chapterInfo?.comicInfo?.cover || ""}
                    alt="cover"
                    loading="lazy"
                    size={true}
                    imageSize={isMaxSmScreen() ? "260x140" : "430x240"}
                  />
                  {item?.isvip === 1 &&
                    !chapterStatus?.purchased_chapter_ids?.includes(item?.id) &&
                    userInfo?.isvip_status === 0 && (
                      <div className="absolute top-0 left-0 w-full h-full bg-black/70 flex justify-center items-center">
                        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                          <img
                            className="w-9"
                            src={`${import.meta.env.VITE_INDEX_DOMAIN
                              }/assets/images/lock-closed.svg`}
                            alt="lock"
                          />
                        </div>
                      </div>
                    )}
                  <p
                    className={`w-full absolute bottom-0 left-0 text-white text-sm px-2 py-1 truncate rounded-b max-xs:text-[10px] max-xs:px-1 max-xs:py-0.5 ${isCurrentChapter ? "bg-[#F54336]/70" : "bg-black/70"
                      }`}
                  >
                    {t("common.ep", {
                      ep: item.max_sort || item?.sort,
                    })}
                  </p>
                </div>
              );
            })}
        </div>
      </div>
      {/* {isFullScreen && (
        <div
          className="fixed top-0 left-0 w-full h-full bg-black/85 z-30"
          onClick={() => {
            setIsFullScreen(false);
            setTimeout(() => {
              document
                .getElementById(`image-${currentPage}`)
                ?.scrollIntoView({ behavior: "smooth", block: "start" });
            }, 100);
          }}
        >
          <div
            className="w-[50%] h-full mx-auto overflow-auto max-xs:w-full"
            ref={myFullScreenDivRef}
          >
            {chapterInfo?.chaInfo?.imagelist?.map(
              (item: any, index: number) => {
                return (
                  // <img
                  //   className="w-full object-cover"
                  //   src={`${config?.image_host}${item}`}
                  //   alt="cover"
                  //   key={item}
                  //   loading="lazy"
                  //   id={`fullScreenImage-${index}`}
                  // />
                  <Image
                    className="w-full object-cover"
                    src={item}
                    alt="cover"
                    key={item}
                    loading="lazy"
                    id={`fullScreenImage-${index}`}
                  />
                );
              }
            )}
          </div>
        </div>
      )} */}
      {/* <Footer /> */}
      <ToastContainer
        position="top-center"
        theme="colored"
        autoClose={1500}
        hideProgressBar={true}
        pauseOnHover
        className="toast_notification"
        transition={Slide}
        closeButton={false}
      />
      <FloatButton onClick={handleBackToTop} />
      <PurchaseChapterModal
        // noAsk={true}
        open={openPointsModal?.open && openPointsModal?.type === "points"}
        onClose={() =>
          setOpenPointsModal((prev: any) => ({ ...prev, open: false }))
        }
        onConfirm={() => handleConfirmPurchase(purchaseChapter?.id)}
        purchaseChapter={purchaseChapter}
        userInfo={userInfo}
      />
      <InsufficientScoreModal
        open={openPointsModal?.open && openPointsModal?.type === "no_points"}
        onClose={() =>
          setOpenPointsModal((prev: any) => ({ ...prev, open: false }))
        }
        purchaseChapter={purchaseChapter}
        userInfo={userInfo}
      />
    </>
  );
};

export default Chapter;
