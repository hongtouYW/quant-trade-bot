import { useEffect, useState } from "react";
import UserWrapper from "../../components/UserWrapper";
import { useUser } from "../../contexts/user.context";
import { http } from "../../api";
import type { APIResponseType } from "../../api/type";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import { toast } from "react-toastify";
import Post from "../../components/Post";
import { useTranslation } from "react-i18next";
import ConfirmModal from "./modules/ConfirmModal";
import { useNavigate, useSearchParams } from "react-router";
import Pagination from "../../components/Pagination";
import { isMobile } from "../../utils/utils";
import useUserFavorite from "../../hooks/useUserFavorite";

const Favourite = () => {
  const { t } = useTranslation();
  const { userInfo } = useUser();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [active, setActive] = useState(1);

  const [isSelectedAll, setIsSelectedAll] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [isOpenConfirmModal, setIsOpenConfirmModal] = useState(false);

  const [selectedItems, setSelectedItems] = useState<any>([]);
  // 用户收藏参数
  const [userFavoriteParams, setUserFavoriteParams] = useState<any>({
    token: userInfo?.token,
    page: searchParams.get("page") || 1,
    limit: isMobile() ? 9 : 10,
  });
  // 用户收藏列表
  const { data: favouriteList, refetch: refetchFavouriteList } =
    useUserFavorite(userFavoriteParams);

  const handleClickSelectedItem = (id: any) => {
    setSelectedItems((prev: any) =>
      prev.includes(id)
        ? prev.filter((item: any) => item !== id)
        : [...prev, id]
    );
  };

  const handleClickSelectedAll = () => {
    setIsSelectedAll((prev: any) => !prev);
    setSelectedItems((prev: any) =>
      prev.length === favouriteList?.data?.length
        ? []
        : favouriteList?.data?.map((item: any) => item.id)
    );
  };

  // // 获取订阅列表
  // const handleGetFavouriteList = async (pageNum: number = 1) => {
  //   try {
  //     const params = {
  //       token: userInfo?.token,
  //       page: searchParams.get("page") || pageNum,
  //       limit: isMobile() ? 9 : 10,
  //     };
  //     const res = await http.post<APIResponseType>(
  //       API_ENDPOINTS.userFavorite,
  //       params
  //     );

  //     if (res?.data?.code === 1) {
  //       console.log("res", res);
  //       setFavouriteList(res?.data?.data);
  //     }
  //   } catch (error) {
  //     console.log("error", error);
  //   }
  // };

  const handleCheckSelectedCancelItems = () => {
    if (selectedItems?.length === favouriteList?.data?.length) {
      setIsOpenConfirmModal(true);
    } else {
      handleCancelFavourite();
    }
  };

  const handleCancelFavourite = async () => {
    const ids =
      selectedItems?.length > 0
        ? selectedItems?.length === 1
          ? selectedItems[0]
          : JSON.stringify(selectedItems)
        : "";

    try {
      const params = {
        token: userInfo?.token,
        mid: ids,
      };

      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.comicUnfavorite,
        params
      );

      if (res?.data?.code === 1) {
        toast.success(t("user.cancelFavouriteSuccess"));
        refetchFavouriteList();
        setIsEdit(false);
        // handleGetFavouriteList();
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (error) {
      console.log("error", error);
    } finally {
      setIsOpenConfirmModal(false);
    }
  };

  const paginationHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    searchParams.set("page", num.toString());
    setSearchParams(searchParams);
  };

  useEffect(() => {
    if (selectedItems.length === favouriteList?.data?.length) {
      setIsSelectedAll(true);
    } else {
      setIsSelectedAll(false);
    }
  }, [selectedItems]);

  // useEffect(() => {
  //   handleGetFavouriteList();
  // }, [userInfo?.token]);

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    // handleGetFavouriteList(page);
    setUserFavoriteParams({
      token: userInfo?.token,
      page: page.toString(),
      limit: isMobile() ? 9 : 10,
    });
    setActive(page);
  }, [searchParams]);

  return (
    <>
      <div id="favorite-page" className="h-full">
        <UserWrapper>
          <div className="flex items-center p-4 w-full text-greyscale-900 isolate fixed top-0 left-0 right-0 z-10 bg-white h-14 lg:hidden">
            <img
              src="/assets/images/icon-cheveron-left.svg"
              alt="arrow-left"
              className="w-6 h-6 cursor-pointer"
              onClick={() => navigate("/")}
            />
            <p className="font-semibold text-center w-full">{t("user.myFavourite")}</p>
            {favouriteList?.data?.length > 0 && (
              <div className="flex justify-end items-center gap-1 w-12 shrink-0 text-right">
                <button
                  className="cursor-pointer"
                  onClick={() => setIsEdit((prev: any) => !prev)}
                >
                  {isEdit ? (<span className="text-primary">{t("user.done")}</span>) : (
                    <img
                      className="w-6 h-6"
                      src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-edit-black.svg`}
                      alt="edit"
                    />
                  )}
                </button>
              </div>
            )}
          </div>

          {/* Desktop edit */}
          <div className="hidden pt-6 px-6 lg:flex justify-end">
            {favouriteList?.data?.length > 0 && (
              <div className="flex justify-end items-center gap-1 shrink-0 text-right">
                <button
                  className="flex gap-1 items-center cursor-pointer text-primary text-xl font-semibold"
                  onClick={() => setIsEdit((prev: any) => !prev)}
                >
                  {isEdit ? (<span>{t("user.done")}</span>) : (
                    <>
                      <span>{t("user.edit")}</span>
                      <img
                        className="w-6 h-6"
                        src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-edit-pink.svg`}
                        alt="edit"
                      />
                    </>
                  )}
                </button>
              </div>
            )}
          </div>

          {/* 订阅列表 */}
          <div className="p-4 lg:p-6">
            <div className="min-h-[calc(100vh-56px)] lg:min-h-auto">
              {favouriteList?.data?.length === 0 && (
                <div className="flex flex-col items-center justify-center gap-2 w-80 mx-auto">
                  <div className="rounded-full bg-greyscale-100 p-4">
                    <img
                      src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-search-result.svg`}
                      alt="no result"
                      className="w-6 h-6"
                    />
                  </div>
                  <p className="text-sm text-greyscale-500 text-center">{t("common.noFavouriteDisplay")}</p>
                </div>
              )}

              <div className="flex flex-wrap gap-1.5 lg:gap-6">
                {favouriteList?.data?.map((item: any) => (
                  <div key={item.id} className="relative min-w-[113px] min-h-[239px] xl:min-w-[185px] xl:min-h-[342px]">
                    <Post item={item} fixedHeight={true} postDivClassName="h-full" />
                    {isEdit && (
                      <div
                        className={`absolute top-0 left-0 bg-black/50 w-full h-full rounded-xl max-xs:rounded-lg`}
                        onClick={() => handleClickSelectedItem(item.id)}
                      >
                        {/* <img
                          className="absolute top-2 right-2 cursor-pointer w-8 h-8 max-xs:w-5 max-xs:h-5"
                          src={
                            selectedItems.includes(item.id)
                              ? `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-selected.svg`
                              : `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-unselected.svg`
                          }
                          alt="check"
                        /> */}
                        <div className={`absolute top-2 right-2 rounded-full shrink-0 ${selectedItems.includes(item.id)
                          ? "w-5 h-5 border-7 border-primary bg-white"
                          : "w-5 h-5 border border-greyscale-300"
                          }`} />
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
          {favouriteList?.last_page > 1 && (
            <div className="pb-6">
              <Pagination
                active={active}
                size={favouriteList?.last_page || 1}
                step={1}
                total={favouriteList?.total || 0}
                onClickHandler={paginationHandler}
              />
            </div>
          )}
          {/* Fixed footer button */}
          {isEdit && (<div className="fixed bottom-0 left-0 right-0 p-4 bg-white z-[6]">
            <div className="flex justify-between items-center max-w-screen-xl mx-auto">
              <label htmlFor="check-all" className="flex items-center gap-2 max-xs:gap-1 max-xs:mr-2">
                <input type="checkbox" id="check-all" className="w-5 h-5 accent-primary" onClick={handleClickSelectedAll} checked={isSelectedAll} />
                <p className="text-sm">
                  {t("user.selectAll")}
                </p>
              </label>

              <button
                className={`px-4 py-3 rounded-xl leading-[20px] text-sm ${selectedItems?.length > 0 ? "border border-[#F5483B] text-[#F5483B] cursor-pointer" : "border border-greyscale-400 text-greyscale-400"}`}
                disabled={selectedItems?.length === 0}
                onClick={handleCheckSelectedCancelItems}
              >
                {t("user.cancelFavourite")}
              </button>
            </div>
          </div>)}
        </UserWrapper>
      </div>
      <ConfirmModal
        title={t("common.message")}
        cancelText={t("common.cancel")}
        confirmText={t("common.confirm")}
        isOpenConfirmModal={isOpenConfirmModal}
        onClose={() => setIsOpenConfirmModal(false)}
        onConfirm={handleCancelFavourite}
        content={t("user.confirmCancelAllFavourite")}
      />
    </>
  );
};

export default Favourite;
