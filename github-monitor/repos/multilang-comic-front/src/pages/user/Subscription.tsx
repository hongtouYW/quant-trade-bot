import { useEffect, useState } from "react";
import UserWrapper from "../../components/UserWrapper";
import Post from "../../components/Post";
import type { APIResponseType } from "../../api/type";
import { http } from "../../api";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import { useUser } from "../../contexts/user.context";
import { toast } from "react-toastify";
import { useTranslation } from "react-i18next";
import ConfirmModal from "./modules/ConfirmModal";
import Pagination from "../../components/Pagination";
import { useSearchParams } from "react-router";
import { isMobile } from "../../utils/utils";

// const bestSeller = [
//   {
//     id: 1,
//     image: "/assets/images/post1.png",
//     title: "命運:貞潔慾女",
//   },
//   {
//     id: 2,
//     image: "/assets/images/post2.png",
//     title: "命運:貞潔慾女",
//     isVip: true,
//     isSerial: true,
//     is18: true,
//     view: 1.5,
//   },
//   {
//     id: 3,
//     image: "/assets/images/post3.png",
//     title: "命運:貞潔慾女",
//     isVip: true,
//     isSerial: true,
//     is18: true,
//     view: 1.5,
//   },
//   {
//     id: 4,
//     image: "/assets/images/post1.png",
//     title: "命運:貞潔慾女",
//     isVip: true,
//     isSerial: true,
//   },
//   {
//     id: 5,
//     image: "/assets/images/post2.png",
//     title: "命運:貞潔慾女",
//     isVip: true,
//     isSerial: true,
//   },
//   {
//     id: 6,
//     image: "/assets/images/post3.png",
//     title: "命運:貞潔慾女",
//     isVip: true,
//     isSerial: true,
//   },
//   {
//     id: 7,
//     image: "/assets/images/post1.png",
//     title: "命運:貞潔慾女",
//     isVip: true,
//     isSerial: true,
//   },
//   {
//     id: 8,
//     image: "/assets/images/post2.png",
//     title: "命運:貞潔慾女",
//     isVip: true,
//     isSerial: true,
//   },
// ];

const Subscription = () => {
  const { t } = useTranslation();
  const { userInfo } = useUser();
  const [searchParams, setSearchParams] = useSearchParams();

  const [isSelectedAll, setIsSelectedAll] = useState(false);
  const [isEdit, setIsEdit] = useState(false);
  const [isOpenConfirmModal, setIsOpenConfirmModal] = useState(false);
  const [selectedItems, setSelectedItems] = useState<any>([]);
  const [subscriptionList, setSubscriptionList] = useState<any>({});

  const [active, setActive] = useState(1);

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
      prev.length === subscriptionList?.data?.length
        ? []
        : subscriptionList?.data?.map((item: any) => item.id)
    );
  };

  // 获取订阅列表
  const handleGetSubscriptionList = async (pageNum: number = 1) => {
    // console.log("pageNum", pageNum);
    try {
      const params = {
        token: userInfo?.token,
        page: searchParams.get("page") || pageNum,
        limit: isMobile() ? 9 : 10,
      };
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userSubscribe,
        params
      );

      if (res?.data?.code === 1) {
        // console.log("res", res?.data?.data);
        setSubscriptionList(res?.data?.data);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  const handleCheckSelectedCancelItems = () => {
    if (selectedItems?.length === subscriptionList?.data?.length) {
      setIsOpenConfirmModal(true);
    } else {
      handleCancelSubscription();
    }
  };

  const handleCancelSubscription = async () => {
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

      // console.log("params", params);
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.comicUnsubscribe,
        params
      );

      if (res?.data?.code === 1) {
        toast.success(t("common.cancelSubscribeSuccess"));
        handleGetSubscriptionList();
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (error) {
      console.error("error", error);
    } finally {
      setIsOpenConfirmModal(false);
    }
  };

  const activeHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    searchParams.set("page", num.toString());
    setSearchParams(searchParams);
  };

  useEffect(() => {
    if (selectedItems.length === subscriptionList?.data?.length) {
      setIsSelectedAll(true);
    } else {
      setIsSelectedAll(false);
    }
  }, [selectedItems]);

  useEffect(() => {
    handleGetSubscriptionList();
  }, [userInfo?.token]);

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    handleGetSubscriptionList(page);
    setActive(page);
  }, [searchParams]);

  return (
    <>
      <UserWrapper>
        {/* Title */}
        <div className="flex justify-between items-center border-b-2 border-greyscale-200 pb-4 max-xs:pb-2">
          <h4 className="font-medium text-lg max-xs:text-base">
            {t("user.subscriptionList")}
          </h4>
          {subscriptionList?.data?.length > 0 && (
            <div className="flex items-center gap-1">
              {isEdit && (
                <div className="flex items-center gap-1">
                  <div className="flex items-center gap-4 max-xs:gap-1 max-xs:flex-col max-xs:items-end">
                    <div
                      className="flex items-center gap-2 max-xs:gap-1 max-xs:mr-2"
                      onClick={handleClickSelectedAll}
                    >
                      <img
                        className="w-6 h-6 max-xs:w-4 max-xs:h-4"
                        src={
                          isSelectedAll
                            ? `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-selected.svg`
                            : `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-unselected.svg`
                        }
                        alt="check"
                      />
                      <p className="font-medium text-sm">
                        {t("user.selectAll")}
                      </p>
                    </div>
                    {selectedItems?.length > 0 && (
                      <button
                        className="text-sm text-[#424242] font-medium rounded-full px-6 py-[5px] border-[1.5px] border-greyscale-500 max-xs:px-4 max-xs:text-xs"
                        onClick={handleCheckSelectedCancelItems}
                      >
                        {t("user.cancelSubscription")}
                      </button>
                    )}
                  </div>
                  <div className="w-[2px] h-6 bg-greyscale-300 content-[''] mx-4 max-xs:mx-1"></div>
                </div>
              )}
              <button
                className="text-sm bg-greyscale-500 text-white rounded-full px-6 py-[6px] border-[1.5px] border-transparent max-xs:px-4 max-xs:py-1 max-xs:text-xs"
                onClick={() => setIsEdit((prev: any) => !prev)}
              >
                {isEdit ? t("user.cancel") : t("user.edit")}
              </button>
            </div>
          )}
        </div>
        {/* 订阅列表 */}
        <div>
          <div className="min-h-[645px] max-xs:min-h-[590px]">
            <div className="grid grid-cols-5 gap-x-2 gap-y-6 my-5 max-xs:grid-cols-3 max-xs:gap-y-2">
              {subscriptionList?.data?.map((item: any) => (
                <div key={item.id} className="relative">
                  <Post item={item} fixedHeight={true} />
                  {isEdit && (
                    <div
                      className={`absolute top-0 left-0 bg-black/50 w-full h-full rounded-xl max-xs:rounded-lg ${
                        selectedItems.includes(item.id)
                          ? "border-2 border-primary-dark"
                          : ""
                      }`}
                      onClick={() => handleClickSelectedItem(item.id)}
                    >
                      <img
                        className="absolute bottom-2 right-2 cursor-pointer w-8 h-8 max-xs:w-5 max-xs:h-5"
                        src={
                          selectedItems.includes(item.id)
                            ? `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-selected.svg`
                            : `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-unselected.svg`
                        }
                        alt="check"
                      />
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
          {subscriptionList?.last_page && subscriptionList?.last_page > 1 && (
            <div className="pb-6">
              <Pagination
                active={active}
                size={subscriptionList?.last_page || 1}
                step={1}
                total={subscriptionList?.total || 0}
                onClickHandler={activeHandler}
              />
            </div>
          )}
        </div>
      </UserWrapper>
      <ConfirmModal
        isOpenConfirmModal={isOpenConfirmModal}
        onClose={() => setIsOpenConfirmModal(false)}
        onConfirm={handleCancelSubscription}
        content={t("user.confirmCancelAllSubscription")}
        cancelText={t("common.cancel")}
        confirmText={t("common.confirm")}
        title={t("common.message")}
      />
    </>
  );
};

export default Subscription;
