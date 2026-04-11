import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { useNavigate, useParams } from "react-router";

import { useUser } from "../../contexts/user.context";

import { humanizeNumber } from "../../utils/utils";

import useComicDetail from "../../hooks/useComicDetail";
import useComicCheckChapterStatus from "../../hooks/useComicCheckChapterStatus";
import useComicFavorite from "../../hooks/useComicFavorite";
import useComicUnFavorite from "../../hooks/useComicUnfavorite";

const ContentWorksIntro = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { comicId: id } = useParams();

  const { userInfo, setIsOpenUserAuthModal } = useUser();
  const { data: comicInfo } = useComicDetail({ mid: id || "" });
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

  const firstChapterId = comicInfo?.chapters?.data?.[0]?.id;

  const [isLoading, setIsLoading] = useState(false);

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

  useEffect(() => {
    if (isSuccessFavorite || isSuccessUnFavorite) {
      refetchChapterStatus().then((res) => {
        if (res?.isFetched) {
          setIsLoading(false);
        }
      });
    }
  }, [isSuccessFavorite, isSuccessUnFavorite, refetchChapterStatus]);

  // useEffect(() => {
  //   setMarkValue(info?.mark);
  //   setSubscribeValue(info?.subscribe);
  // }, [info]);

  return (
    <div className="flex flex-col gap-2 mt-5 max-xs:mt-3 max-xs:gap-2">
      {/* 标题 */}
      <div className="max-xs:flex max-xs:items-center max-xs:justify-between">
        <p
          className="text-xl font-medium max-sm:text-lg leading-6"
          title={comicInfo?.comic?.title}
        >
          {comicInfo?.comic?.title?.length &&
          comicInfo?.comic?.title?.length > 120
            ? comicInfo?.comic?.title?.slice(0, 120) + "..."
            : comicInfo?.comic?.title}
        </p>
        {chapterStatus?.manhua?.is_favorite === 1 ? (
          <img
            src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-heart-solid.png`}
            alt="heart-solid"
            className={`hidden max-xs:block w-7 h-6  ${
              isLoading
                ? "opacity-50 cursor-not-allowed pointer-events-none"
                : "opacity-100 cursor-pointer"
            }`}
            onClick={handleClickUnFavorite}
          />
        ) : (
          <img
            src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-heart-outline.svg`}
            alt="heart-outline"
            className={`hidden max-xs:block w-6 h-6 ${
              isLoading
                ? "opacity-20 cursor-not-allowed pointer-events-none"
                : "opacity-100 cursor-pointer"
            }`}
            onClick={handleClickFavorite}
          />
        )}
      </div>
      {/* 数据 */}
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-1 text-sm">
          <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/eye-pink.svg`} alt="eye" />
          <p>
            {comicInfo?.comic?.view
              ? humanizeNumber(comicInfo?.comic?.view)
              : "0"}{" "}
            {t("common.watch")}
          </p>
        </div>
        <div className="flex items-center gap-1 text-sm">
          <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-heart-pink.svg`} alt="heart" />
          <p>{humanizeNumber(comicInfo?.comic?.mark || 0)}</p>
        </div>
        <div className="flex items-center gap-1 text-sm">
          <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-full-star-pink.svg`} alt="star" />
          <p>{comicInfo?.comic?.subscribe}</p>
        </div>
      </div>
      {/* 更新时间 */}
      <div className="flex items-center gap-1 my-1">
        <div className="w-6 h-6 bg-[#FF981F] rounded-full text-[8px] text-white/90 flex justify-center items-center truncate">
          {t("common.update")}
        </div>
        {/* <img className="w-6" src="/assets/images/updated.png" alt="updated" /> */}
        <p className="font-medium leading-4">
          {/* <span className="text-primary">每周五</span>更新 */}
          {comicInfo?.comic?.last_chapter_title}
        </p>
      </div>
      {/* 简介 */}
      <p>
        {t("common.author")} : {comicInfo?.comic?.auther}
      </p>
      <p className="text-greyscale-800 text-[15px]">
        {comicInfo?.comic?.desc?.length && comicInfo?.comic?.desc?.length > 380
          ? comicInfo?.comic?.desc?.slice(0, 380) + "..."
          : comicInfo?.comic?.desc}
      </p>
      {/* <div className="flex items-center flex-wrap gap-2">
        {comicInfo?.comic?.tags?.map((item: any) => (
          <p className="text-xs text-primary-dark bg-[#FEE0EA] px-2 py-1 rounded">
            #{item}
          </p>
        ))}
      </div> */}
      <div className="flex items-center gap-2 mt-4 max-xs:mt-1">
        <button
          className="w-full bg-primary text-white px-4 py-2 rounded-md cursor-pointer"
          onClick={() => {
            if (!userInfo?.token) {
              setIsOpenUserAuthModal({
                open: true,
                type: "login",
              });
              return;
            }
            navigate(`/chapter/${firstChapterId}?mid=${comicInfo?.comic?.id}`);
          }}
        >
          {t("common.readNow")}
        </button>
        {chapterStatus?.manhua?.is_favorite === 1 ? (
          <button
            className={`bg-white text-primary-dark border-1 border-primary-dark px-4 py-2 rounded-md w-full font-medium flex items-center justify-center gap-2 cursor-pointer ${
              isLoading
                ? "opacity-50 cursor-not-allowed pointer-events-none"
                : "cursor-pointer"
            }`}
            onClick={handleClickUnFavorite}
          >
            <img
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-heart-solid.png`}
              alt="heart"
              className="w-[18px]"
            />
            <p className="font-medium">{t("common.unFavorite")}</p>
          </button>
        ) : (
          <button
            className={`bg-white text-primary-dark border-1 border-primary-dark px-4 py-2 rounded-md w-full font-medium flex items-center justify-center gap-2 cursor-pointer ${
              isLoading
                ? "opacity-50 cursor-not-allowed pointer-events-none"
                : "cursor-pointer"
            }`}
            onClick={handleClickFavorite}
          >
            <img
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-heart-outline.svg`}
              alt="heart"
              className="w-[18px]"
            />
            <p className="font-medium">{t("common.favorite")}</p>
          </button>
        )}
        {/* {chapterManhuaStatus?.is_subscribe === 1 ? (
          <button
            className={`w-full text-white px-4 py-2 rounded-md  bg-greyscale-500 ${
              isLoading
                ? "opacity-50 cursor-not-allowed pointer-events-none"
                : "cursor-pointer"
            }`}
            onClick={handleClickCancelSubscribe}
          >
            {t("common.cancelSubscribe")}
          </button>
        ) : (
          <button
            className={`w-full bg-[#FFC02D] text-white px-4 py-2 rounded-md  ${
              isLoading
                ? "opacity-50 cursor-not-allowed pointer-events-none"
                : "cursor-pointer"
            }`}
            onClick={handleClickSubscribe}
          >
            {t("common.subscribePlus")}
          </button>
        )} */}
        {/* {chapterManhuaStatus?.is_favorite === 1 ? (
          <img
            src="/assets/images/icon-heart-solid.png"
            alt="heart-solid"
            className={`w-10 h-9 cursor-pointer max-xs:hidden ${
              isLoadingFavorite
                ? "opacity-50 cursor-not-allowed pointer-events-none"
                : "opacity-100"
            }`}
            onClick={handleClickUnFavorite}
          />
        ) : (
          <img
            src="/assets/images/icon-heart-outline.svg"
            alt="heart-outline"
            className={`cursor-pointer max-xs:hidden ${
              isLoadingFavorite
                ? "opacity-20 cursor-not-allowed pointer-events-none"
                : "opacity-100"
            }`}
            onClick={handleClickFavorite}
          />
        )} */}
      </div>
    </div>
  );
};

export default ContentWorksIntro;
