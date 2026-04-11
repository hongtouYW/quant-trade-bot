import { useState } from "react";
import UserWrapper from "../../components/UserWrapper";
// import VipBanner from "../../components/VipBanner";
// import Tabs from "../../components/Tabs";
import PersonalInformation from "./modules/PersonalInformation";
import { useUser } from "../../contexts/user.context";
import ChangePassword from "./modules/ChangePassword";
import RechargeRecord from "./modules/RechargeRecord";
// import RedemptionCode from "./modules/RedemptionCode";

import { useNavigate } from "react-router";
import { useTranslation } from "react-i18next";
// import { API_ENDPOINTS } from "../../api/api-endpoint";
// import { http } from "../../api";
// import type { APIResponseType } from "../../api/type";
import { isMobile, systemLanguage } from "../../utils/utils";
import UserVipPassHeader from "../../components/UserVipPassHeader";

const tabsList = [
  {
    id: 1,
    locale: "user.personalInformation",
    value: "1",
    children: <PersonalInformation />,
  },
  {
    id: 2,
    locale: "user.securitySettings",
    value: "2",
    children: <ChangePassword />,
  },
  {
    id: 3,
    locale: "user.rechargeRecord",
    value: "3",
    children: <RechargeRecord />,
  },
  // {
  //   id: 4,
  //   locale: "user.membershipRedemption",
  //   value: "4",
  //   children: <RedemptionCode />,
  // },
];

const Account = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { userInfo } = useUser();
  const lang = systemLanguage();

  const [currentActiveTab, setCurrentActiveTab] = useState<any>(tabsList?.[0]);
  // const [rechargeVipList, setRechargeVipList] = useState<any>();

  const handleClickTabOnChange = (value: any) => {
    const currenTab = tabsList?.find((item) => {
      return item.id?.toString() === value;
    });

    setCurrentActiveTab(currenTab);
  };
  // 获取充值 和 书币列表
  // const handleGetRechargeList = async () => {
  //   try {
  //     const res = await http.post<APIResponseType>(API_ENDPOINTS.rechargeLists);

  //     if (res?.data?.code === 1) {
  //       setRechargeVipList(
  //         res?.data?.data?.filter((item: any) => item.type_status === 2)
  //       );
  //     }
  //   } catch (err) {
  //     console.log("err", err);
  //   }
  // };

  // useEffect(() => {
  //   handleGetRechargeList();
  // }, []);
  return (
    <div className="h-full">
      <UserWrapper>
        {/* mobile header */}
        <div className="flex items-center p-4 w-full text-greyscale-900 fixed top-0 left-0 right-0 bg-white h-14 leading-6 lg:hidden">
          <img
            src="/assets/images/icon-cheveron-left.svg"
            alt="arrow-left"
            onClick={() => navigate("/")}
            className="w-6 h-6 cursor-pointer"
          />
          <p className="font-semibold text-center w-full ">{t("user.account")}</p>
        </div>

        <div className="flex flex-col gap-6 p-4 flex-5 lg:p-6">
          {userInfo.isvip_status === 0 && (
            <div>
              <img
                className="w-full"
                src={`/assets/images/vip-not-activated-${isMobile() ? "mobile" : "desktop"
                  }-2-${lang}.png`}
                alt="vip-not-activated"
              />
            </div>
          )}

          <UserVipPassHeader isShowCoin={true} />
          {/* 个人资料 */}
          {/* <div className="flex items-center gap-4 max-xs:flex-col">
            <img
              className="w-[95px] h-[95px] rounded-full"
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/default-avatar-1.png`}
              alt=""
            />
            <div className="flex flex-col gap-1 max-xs:items-center max-xs:gap-2">
              <p className="font-medium text-lg max-xs:text-base max-xs:leading-[18px]">
                {userInfo.username || "-"}
              </p>
              <p className="text-sm text-greyscale-600 max-xs:leading-4">
                {userInfo.isvip_status === 0
                  ? t("common.normalUser")
                  : t("user.isvip")}
              </p>
              <p className="text-sm text-greyscale-600 max-xs:leading-4">
                UID：{userInfo.id}
              </p>
            </div>
          </div> */}
          {/* 书币余额 */}
          {/* <div className="flex items-center gap-4 max-xs:justify-center">
            <div className="flex items-center gap-1">
              <img
                className="w-6 h-6"
                alt="coin"
                src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-coin.png`}
              />
              <p className="font-medium">
                {t("user.coinBalance")}：{userInfo?.score}
              </p>
            </div>
            <button
              className="text-sm text-white bg-[#FF981F] px-4 py-[6px] rounded-md cursor-pointer max-xs:text-xs"
              onClick={() => {
                navigate("/user/topup");
              }}
            >
              {t("user.recharge")}
            </button>
          </div> */}
          {/* 开通VIP */}
          {/* <VipBanner
            vipPrice={rechargeVipList?.[rechargeVipList?.length - 1]?.price}
          /> */}
          {/* Tabs */}
          <div>
            <div className="-mx-4 sticky top-12 bg-white lg:mx-0">
              <div className="flex items-center px-4 border-b border-greyscale-200 lg:px-0">
                {tabsList.map((item) => {
                  return (
                    <div
                      key={item.id}
                      className={`cursor-pointer ${currentActiveTab.value?.toString() === item.value
                        ? "border-b-3 border-primary"
                        : "border-b-3 border-transparent"
                        }`}
                      onClick={() => {
                        handleClickTabOnChange(item.value);
                      }}
                    >
                      <p
                        className={`text-sm leading-4 px-4 py-2 ${currentActiveTab.value?.toString() === item.value
                          ? "text-primary font-semibold"
                          : "text-greyscale-900"
                          } lg:text-base lg:leading-6 lg:py-1.5 lg:px-[20px]`}
                      >
                        {t(item.locale)}
                      </p>
                    </div>
                  );
                })}
              </div>
            </div>
            <div className="min-h-[120px]">
              {currentActiveTab.children}
            </div>
          </div>
        </div>
      </UserWrapper>
    </div>
  );
};

export default Account;
