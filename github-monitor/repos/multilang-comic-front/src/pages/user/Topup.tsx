import { useEffect, useState } from "react";
import UserWrapper from "../../components/UserWrapper";
// import VipBanner from "../../components/VipBanner";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import type { APIResponseType } from "../../api/type";
import { http } from "../../api";
import type { RechargeItemType } from "./data";
import { useTranslation } from "react-i18next";
import { useUser } from "../../contexts/user.context";
// import { toast } from "react-toastify";
// import Input from "../../components/Input";
import PaymentForm from "./modules/PaymentForm";
import { toast } from "react-toastify";
import { useNavigate } from "react-router";
// import { toFmt } from "../../utils/utils";
import TopupConfirmationModal from "./TopupConfirmationModal";
import UserVipPassHeader from "../../components/UserVipPassHeader";
import { isMobile, systemLanguage } from "../../utils/utils";
// import { comicStatistics } from "../../utils/plugin/comicStatistics";

const Topup = () => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { userInfo } = useUser();
  const currency = import.meta.env.VITE_DEFAULT_CURRENCY;
  const currencySymbol = import.meta.env.VITE_DEFAULT_CURRENCY_SYMBOL;
  const lang = systemLanguage();

  const [isLoading, setIsLoading] = useState(false);
  const [isOpenTopupConfirmationModal, setIsOpenTopupConfirmationModal] =
    useState({
      open: false,
      payUrl: "",
    });
  const [paymentPlatformList, setPaymentPlatformList] = useState<
    RechargeItemType[]
  >([]);
  const [selectedPackage, setSelectedPackage] = useState<any>({});
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState({
    id: 0,
    qudaodes: "",
    qudaoname: "",
    qudaopaydata: 0,
  });

  const [rechargeList, setRechargeList] = useState<any>({
    vip: [],
    bookCoin: [],
  });

  const [isShowPaymentMethod, setIsShowPaymentMethod] = useState(false);
  const [paymentForm, setPaymentForm] = useState({
    card_ccno: "",
    card_month_year: "",
    card_ccvv: "",
    bill_fullname: "",
    bill_address: "",
    bill_country: "",
    bill_state: "",
    bill_city: "",
    bill_zip: "",
    bill_email: "",
    bill_phone: "",
    card_type: "dc",
  });

  const [isCreditCardFormError, setIsCreditCardFormError] = useState({
    card_ccno: false,
    card_month_year: false,
    card_ccvv: false,
    bill_fullname: false,
    bill_address: false,
    bill_country: false,
    bill_state: false,
    bill_city: false,
    bill_zip: false,
    bill_email: false,
    bill_phone: false,
    card_type: false,
  });

  // 提交充值
  const handleSubmit = async (e: React.SyntheticEvent) => {
    e.preventDefault();
    setIsLoading(true);

    const params: any = {
      token: userInfo?.token,
      pro_id: selectedPackage?.id,
      pay_id: selectedPaymentMethod?.id,
    };
    // console.log("paymentForm", paymentForm);
    if (selectedPaymentMethod.qudaopaydata === 3) {
      if (
        !paymentForm.card_ccno ||
        !paymentForm.card_month_year ||
        !paymentForm.card_ccvv ||
        !paymentForm.bill_fullname ||
        !paymentForm.bill_address ||
        !paymentForm.bill_country ||
        !paymentForm.bill_state ||
        !paymentForm.bill_city ||
        !paymentForm.bill_zip ||
        !paymentForm.bill_email ||
        !paymentForm.bill_phone ||
        !paymentForm.card_type
      ) {
        // console.log("paymentForm-error", paymentForm);
        setIsCreditCardFormError({
          ...isCreditCardFormError,
          card_ccno: !paymentForm.card_ccno,
          card_month_year: !paymentForm.card_month_year,
          card_ccvv: !paymentForm.card_ccvv,
          bill_fullname: !paymentForm.bill_fullname,
          bill_address: !paymentForm.bill_address,
          bill_country: !paymentForm.bill_country,
          bill_state: !paymentForm.bill_state,
          bill_city: !paymentForm.bill_city,
          bill_zip: !paymentForm.bill_zip,
          bill_email: !paymentForm.bill_email,
          bill_phone: !paymentForm.bill_phone,
          card_type: !paymentForm.card_type,
        });
        return;
      }

      if (paymentForm.card_month_year) {
        const monthYear = paymentForm.card_month_year.split("/");
        const month = monthYear[0];
        const year = monthYear[1];

        params.card_month = month;
        params.card_year = year;
      }

      if (paymentForm?.card_ccno) {
        params.card_ccno = paymentForm.card_ccno?.replace(/\s/g, "");
      }

      params.card_ccvv = paymentForm.card_ccvv;
      params.bill_fullname = paymentForm.bill_fullname;
      params.bill_address = paymentForm.bill_address;
      params.bill_country = paymentForm.bill_country;
      params.bill_state = paymentForm.bill_state;
      params.bill_city = paymentForm.bill_city;
      params.bill_zip = paymentForm.bill_zip;
      params.bill_email = paymentForm.bill_email;
      params.bill_phone = paymentForm.bill_phone;
      params.card_type = paymentForm.card_type;
    }
    // console.log("params", params);
    // const newTab = window.open("", "_blank");
    try {
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.rechargePay,
        params
      );
      if (res?.data?.code === 1) {
        // console.log("res", res);

        const payUrl = res?.data?.data?.pay_url;
        if (payUrl) {
          setIsOpenTopupConfirmationModal({
            open: true,
            payUrl: payUrl,
          });
        }

        // const payUrl = res?.data?.data?.pay_url;
        // if (newTab && payUrl) {
        //   newTab.location.href = payUrl;
        // }
        // setIsLoading(false);
        // setIsShowPaymentMethod(false);
        // toast.success(res?.data?.msg);
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (err) {
      console.error("err", err);
    } finally {
      setIsLoading(false);
    }
  };

  const handleChangePaymentFormValue = (form: any) => {
    setPaymentForm(form);
  };

  // 获取充值 和 书币列表
  const handleGetRechargeList = async () => {
    try {
      const res = await http.post<APIResponseType>(API_ENDPOINTS.rechargeLists);

      if (res?.data?.code === 1) {
        // console.log("res-rechargeList", res);
        setRechargeList({
          vip: res?.data?.data?.filter((item: any) => item.type_status === 2),
          bookCoin: res?.data?.data?.filter(
            (item: any) => item.type_status === 1
          ),
        });
      }
    } catch (err) {
      console.error("err", err);
    }
  };

  // 获取支付平台列表
  const handleGetPaymentPlatformList = async () => {
    try {
      const params: any = {
        pro_id: selectedPackage?.id,
      };
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.rechargePlatforms,
        params
      );
      if (res?.data?.code === 1) {
        setPaymentPlatformList(res?.data?.data);
      }
    } catch (err) {
      console.error("err", err);
    }
  };

  useEffect(() => {
    handleGetRechargeList();
    // handleGetPaymentPlatformList();
  }, []);

  useEffect(() => {
    setSelectedPackage(rechargeList?.vip?.[0]);
  }, [rechargeList]);

  useEffect(() => {
    if (paymentPlatformList?.length > 0) {
      setSelectedPaymentMethod(paymentPlatformList?.[0]);
    }
  }, [paymentPlatformList]);

  return (
    <>
      <div id="topup-page" className="h-full">
        <UserWrapper>
          <div className="flex items-center gap-4 p-4 w-full text-greyscale-900 fixed top-0 left-0 right-0 z-20 bg-white h-14 leading-6 lg:hidden">
            <img
              src="/assets/images/icon-cheveron-left.svg"
              alt="arrow-left"
              className="w-6 h-6 cursor-pointer"
              onClick={() => navigate("/")}
            />
            <p className="font-semibold text-center w-full">{t("user.plan")}</p>
          </div>
          {isLoading && (
            <div className="fixed inset-0 flex items-center justify-center bg-white/80 z-50 mt-12">
              <div className="w-12 h-12 border-4 border-primary-dark border-t-transparent rounded-full animate-spin" />
            </div>
          )}

          <div className="m-4 lg:m-6">
            {userInfo.isvip_status === 0 && (
              <div>
                <img
                  className="w-full mb-4"
                  src={`/assets/images/vip-not-activated-${isMobile() ? "mobile" : "desktop"
                    }-2-${lang}.png`}
                  alt="vip-not-activated"
                />
              </div>
            )}

            <UserVipPassHeader />
          </div>

          {!isShowPaymentMethod ? (
            <>
              {/* 开通会员 */}
              {/* <VipBanner
                vipPrice={rechargeList?.vip?.[rechargeList?.vip?.length - 1]?.price}
              /> */}
              {/* {userInfo?.isvip_status === 1 && (
                <div className="hidden max-xs:block mt-4 relative px-2">
                  <div className="flex items-center justify-between py-[10px] px-4 bg-[linear-gradient(90deg,#E9DEF6_0%,#E5E7FC_100%)] rounded-md">
                    <div className="flex items-center gap-[6px]">
                      <img
                        className="w-6"
                        src={`${
                          import.meta.env.VITE_INDEX_DOMAIN
                        }/assets/images/icon-vip-badge.svg`}
                        alt="vip"
                      />
                      <p className="text-greyscale-900 font-medium">
                        {t("user.vipActived")}
                      </p>
                    </div>
                    <p className="text-sm font-medium text-greyscale-700">
                      {t("user.vipExpiredTime")}:
                      {toFmt(parseInt(userInfo.viptime || "0") * 1000 || 0)}
                    </p>
                  </div>
                  <img
                    className="w-[30px] absolute bottom-0 right-0"
                    src={`${
                      import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/vip-badge-bg.png`}
                    alt="vip"
                  />
                </div>
              )} */}

              {/* VIP充值 */}
              <div className="m-4 lg:m-6">
                {/* Title */}
                <div className="flex gap-1 mb-4 flex-col items-start lg:mb-7">
                  <p className="font-semibold text-base leading-6 lg:text-xl">
                    {t("user.vipSubscription")}
                  </p>
                  <p className="text-greyscale-500 leading-4 text-xs lg:text-sm">
                    ({t("user.vipUserCanReadAllWorks")})
                  </p>
                </div>
                <div
                  className={`grid gap-4 grid-cols-2 ${isLoading
                    ? "pointer-events-none cursor-not-allowed opacity-50"
                    : ""
                    } lg:grid-cols-3 lg:gap-6 xl:grid-cols-4`}
                >
                  {rechargeList?.vip?.map((item: any) => (
                    <div
                      key={item.id}
                      className="relative cursor-pointer"
                      onClick={() => setSelectedPackage(item)}
                    >
                      <div className={`relative border-[1.5px] rounded-xl p-3 flex items-start gap-2 z-10 h-full ${selectedPackage?.id === item.id
                        ? "bg-primary-100 border-primary"
                        : "bg-white border-greyscale-300"
                        } lg:p-4`}>
                        <div className={`rounded-full shrink-0 ${selectedPackage?.id === item.id
                          ? "w-5 h-5 border-7 border-primary bg-white"
                          : "w-5 h-5 border border-greyscale-300"
                          }`} />

                        <div className="text-greyscale-900">
                          <div className="flex gap-1 items-center mb-0 lg:items-end lg:mb-2">
                            <p className="font-semibold text-sm leading-[20px] lg:text-base">
                              {currencySymbol} {item.price.toFixed(2)}
                            </p>

                            {/* <p className="text-greyscale-500 line-through text-sm hidden lg:text-base lg:block">
                              {currencySymbol} {item.ori_price}
                            </p> */}
                          </div>

                          <p className="mt-1 text-sm leading-[16px] lg:text-base">
                            {item.intro}
                          </p>

                          {/* gift */}
                          <div className="flex items-start justify-between mt-1">
                            {/* <p className="text-[#FF751A] max-xs:text-xs max-xs:hidden">
                              {t("user.deducted")} USD {item.ori_price - item.price}
                            </p> */}
                            {item?.gift && (
                              <div className="flex items-center gap-1 bg-[#FBE27B] px-3 py-1 rounded-full">
                                <img
                                  className="w-3 h-3"
                                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                                    }/assets/images/gift.svg`}
                                  alt="gift"
                                />
                                <p className="leading-[14px] text-xs text-[#303030] line-clamp-1 lg:text-sm">
                                  {item?.gift?.name}
                                </p>
                              </div>
                            )}
                          </div>
                        </div>
                      </div>

                      {/* recommended label */}
                      {item.recommend && (
                        <p className="absolute -top-4 right-0 bg-[#FF247C] text-greyscale-50 font-semibold rounded-t-sm text-[8px] leading-2 pt-1 px-2 pb-3 z-0 lg:text-xs lg:leading-4 lg:-top-6 lg:pb-4">
                          {t("user.highlyRecommend")}
                        </p>
                      )}
                    </div>
                  ))}
                </div>
              </div>
              {/* 书币充值 */}
              <div className="m-4 mb-10 lg:m-6">
                {/* Title */}
                <div className="flex items-center gap-1 mb-4 lg:mb-7">
                  <p className="font-semibold text-base leading-6 lg:text-xl">
                    {t("user.bookCoinRecharge")}
                  </p>
                </div>
                <div
                  className={`grid gap-4 grid-cols-2 ${isLoading
                    ? "pointer-events-none cursor-not-allowed opacity-50"
                    : ""
                    } lg:grid-cols-3 lg:gap-6 xl:grid-cols-4`}
                >
                  {rechargeList?.bookCoin?.map((item: any) => (
                    <div
                      key={item.id}
                      className="relative cursor-pointer"
                      onClick={() => setSelectedPackage(item)}
                    >
                      <div className={`relative border-[1.5px] rounded-xl p-3 flex items-start gap-2 z-10 h-full ${selectedPackage?.id === item.id
                        ? "bg-primary-100 border-primary"
                        : "bg-white border-greyscale-300"
                        } lg:p-4`}>
                        <div className={`rounded-full shrink-0 ${selectedPackage?.id === item.id
                          ? "w-5 h-5 border-7 border-primary bg-white"
                          : "w-5 h-5 border border-greyscale-300"
                          }`} />

                        <div className="text-greyscale-900">
                          <p className="font-semibold text-sm leading-[20px] lg:text-base lg:leading-6">
                            {currencySymbol} {item.price.toFixed(2)}
                          </p>

                          <div className="flex items-center gap-1">
                            <p className="text-[#FE9901] text-sm text-center lg:text-base">
                              {item.intro.replace("Coins", "")}
                            </p>

                            <img
                              className="w-4 h-4"
                              src={`${import.meta.env.VITE_INDEX_DOMAIN
                                }/assets/images/icon-coin.svg`}
                              alt="coin"
                            />
                          </div>
                        </div>
                      </div>

                      {/* limited offer */}
                      {item.ishotswitch === 1 && (
                        <p className="absolute -top-4 right-0 bg-[#FF247C] text-greyscale-50 text-[8px] font-semibold rounded-t-sm leading-[8px] pt-1 px-2 pb-3 z-0 lg:text-xs lg:leading-4 lg:-top-6 lg:pb-4">
                          {t("user.limitedOffer")}
                        </p>
                      )}
                    </div>
                  ))}
                </div>
              </div>

              <div className="fixed bottom-0 left-0 right-0 z-30 px-2 pt-4 pb-6 bg-white lg:bg-none lg:static lg:p-0 lg:m-6">
                <button
                  className="bg-primary text-white font-semibold px-10 py-2 rounded-xl w-full cursor-pointer lg:w-auto lg:px-6 lg:py-3"
                  onClick={() => {
                    setIsShowPaymentMethod(true);
                    handleGetPaymentPlatformList();
                  }}
                >
                  {t("user.continuePay")}
                  {/* {isLoading ? "正在支付..." : t("user.immediatelyPay")} */}
                </button>
              </div>
            </>
          ) : (
            <div className="m-4 lg:m-6">
              <div className="flex items-center gap-1">
                <img
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/arrow-left2x.png`}
                  alt="left"
                  className="w-4 h-4"
                  onClick={() => setIsShowPaymentMethod(false)}
                />
                <p
                  className="text-greyscale-500 cursor-pointer"
                  onClick={() => setIsShowPaymentMethod(false)}
                >
                  {t("user.topUpPackage")}
                </p>
                <img
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-cheveron-right-grey.svg`}
                  alt="right"
                />
                <p
                  className="text-primary-dark underline cursor-pointer"
                  onClick={() => setIsShowPaymentMethod(true)}
                >
                  {t("user.paymentMethod")}
                </p>
              </div>

              <div className="flex items-start gap-6 flex-col w-full lg:flex-row">
                <div className="mt-4 flex-2 border-greyscale-200 border-r-0 pr-0 w-full">
                  <div className="">
                    <div className="flex items-center gap-1">
                      <p className="font-medium text-lg">
                        {t("user.paymentMethod")}
                      </p>
                    </div>
                    <div
                      className={`mt-4 w-full flex gap-2 flex-nowrap flex-col ${isLoading
                        ? "pointer-events-none cursor-not-allowed opacity-50"
                        : ""
                        } lg:flex-row lg:flex-wrap`}
                    >
                      {paymentPlatformList.map((item: any) => (
                        <div
                          key={item.id}
                          className={`flex items-center justify-between p-3 border-[1.5px] rounded-xl cursor-pointer w-full ${selectedPaymentMethod.id === item.id
                            ? "bg-[#FFF3F7] border-primary-dark"
                            : "border-greyscale-200"
                            } lg:p-4`}
                          onClick={() => setSelectedPaymentMethod(item)}
                        >
                          <div className="flex items-center gap-2 w-full">
                            <img
                              className="w-8 h-8"
                              src={`${import.meta.env.VITE_INDEX_DOMAIN
                                }/assets/images/payment-${item.qudaopaydata}.png`}
                              alt="alipay"
                            />
                            <p className="font-medium">{item.qudaoname}</p>
                          </div>
                          {selectedPaymentMethod.id === item.id ? (
                            <img
                              className="w-5 h-5"
                              src={`${import.meta.env.VITE_INDEX_DOMAIN
                                }/assets/images/icon-selected.svg`}
                              alt="check"
                            />
                          ) : (
                            <div className="w-[18px] h-[18px] border-[1.5px] border-[#E0E0E0] rounded-full"></div>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                  {selectedPaymentMethod.qudaopaydata === 3 && (
                    <div className="w-full lg:mb-20">
                      <PaymentForm
                        onChangePaymentFormValue={handleChangePaymentFormValue}
                        isCreditCardFormError={isCreditCardFormError}
                        setIsCreditCardFormError={setIsCreditCardFormError}
                      />
                    </div>
                  )}
                </div>

                <div className="flex-1">
                  <div>
                    <p className="font-medium mb-2 max-xs:mb-0">
                      {t("user.orderSummary")}
                    </p>
                    <div className="border-b-2 border-greyscale-200 pb-4 max-xs:border-b-0 max-xs:pb-2 max-xs:text-greyscale-600">
                      <div className="flex items-center justify-between">
                        <p className="font-medium max-xs:font-normal">
                          {selectedPackage?.intro}
                        </p>
                        <p className="font-medium text-primary-dark max-xs:hidden">
                          {currency}{" "}
                          {Number(selectedPackage?.ori_price)?.toFixed(2)}
                        </p>
                        <p className="hidden max-xs:block">
                          {currency} {Number(selectedPackage?.price)?.toFixed(2)}
                        </p>
                      </div>
                      <div className="flex items-center justify-between text-sm py-[2px] max-xs:hidden">
                        <p className="text-greyscale-600">
                          {t("user.quantity")}: x1
                        </p>
                        <p className="text-greyscale-500">
                          {t("user.originalPrice")}: {currency}
                          {Number(selectedPackage?.ori_price)?.toFixed(2)}
                        </p>
                      </div>
                    </div>
                    <div className="border-b-2 border-greyscale-200 py-4 max-xs:py-0">
                      <div className="flex items-center justify-between my-2 text-greyscale-600 max-xs:hidden">
                        <p>{t("user.subtotal")}</p>
                        <p>
                          {currency}{" "}
                          {Number(selectedPackage?.ori_price)?.toFixed(2)}
                        </p>
                      </div>
                      <div className="flex items-center justify-between my-2 text-greyscale-600 max-xs:hidden">
                        <p>{t("user.discount1")}</p>
                        <p>
                          {currency}{" "}
                          {(
                            Number(selectedPackage?.ori_price) -
                            Number(selectedPackage?.price)
                          )?.toFixed(2)}
                        </p>
                      </div>
                      <div className="flex items-center justify-between my-2 font-medium max-xs:my-0">
                        <p>{t("user.total")}</p>
                        <p className="max-xs:text-primary">
                          {currency} {Number(selectedPackage?.price)?.toFixed(2)}
                        </p>
                      </div>
                    </div>
                    <div className="mt-8">
                      <button
                        className={`bg-primary text-white px-10 py-3 rounded-lg w-full cursor-pointer ${isLoading ? "" : ""
                          }`}
                        onClick={handleSubmit}
                      >
                        {isLoading
                          ? t("user.paying")
                          : `${t("user.pay")}  ${currency} ${Number(
                            selectedPackage?.price
                          )?.toFixed(2)}`}
                      </button>
                      <button
                        className="bg-white text-primary border-1 mt-2 border-primary px-10 py-3 rounded-lg w-full cursor-pointer"
                        onClick={() => setIsShowPaymentMethod(false)}
                      >
                        {t("user.back")}
                      </button>
                      <p className="text-sm text-greyscale-500 text-center mt-4">
                        {t("user.paymentNote")}
                        <span
                          className="text-blue-500 cursor-pointer underline"
                          onClick={() => navigate("/user/cs")}
                        >
                          {t("user.contact")}
                        </span>
                        {t("user.paymentNote1")}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
          {/* 支付方式 */}
          {/* <div className="my-4">
        <div className="flex items-center gap-1 mb-4">
          <p className="font-medium text-lg">{t("user.paymentMethod")}</p>
        </div>
        <div
          className={`flex flex-col gap-2 ${
            isLoading ? "pointer-events-none cursor-not-allowed opacity-50" : ""
          }`}
        >
          {paymentPlatformList.map((item: any) => (
            <div
              key={item.id}
              className={`flex items-center justify-between px-5 py-3 border-[1.5px] rounded-xl cursor-pointer ${
                selectedPackage.payment === item.id
                  ? "bg-[#FFF3F7] border-primary-dark"
                  : "border-greyscale-200"
              }`}
              onClick={() =>
                setSelectedPackage({ ...selectedPackage, payment: item.id })
              }
            >
              <div className="flex items-center gap-2 w-full">
                <img
                  className="w-8 h-8"
                  src="/assets/images/alipay.png"
                  alt="alipay"
                />
                <p className="font-medium">{item.qudaoname}</p>
              </div>
              {selectedPackage.payment === item.id ? (
                <img
                  className="w-5 h-5"
                  src="/assets/images/icon-selected.svg"
                  alt="check"
                />
              ) : (
                <div className="w-[18px] h-[18px] border-[1.5px] border-[#E0E0E0] rounded-full"></div>
              )}
            </div>
          ))}
        </div>
      </div> */}
          {/* 支付按钮 */}
          {/* <div className="w-full mt-6">
        <button
          className={`bg-primary text-white px-10 py-3 rounded-lg w-full  ${
            isLoading ? "opacity-50 cursor-not-allowed" : "cursor-pointer"
          }`}
          onClick={handleSubmit}
        >
          {isLoading ? "正在支付..." : t("user.immediatelyPay")}
        </button>
      </div> */}
          {/* 温馨提醒 */}
          {/* <div className="my-4">
        <p className="font-medium text-lg my-2">{t("user.warmReminder")}</p>
        <ul className="text-greyscale-500 text-sm">
          <li className="mb-1 text-sm">{t("user.warmReminder1")}</li>
          <li className="mb-1 text-sm">{t("user.warmReminder2")}</li>
          <li className="mb-1 text-sm">{t("user.warmReminder3")}</li>
        </ul>
      </div> */}
        </UserWrapper>
      </div>
      <TopupConfirmationModal
        open={isOpenTopupConfirmationModal?.open}
        payUrl={isOpenTopupConfirmationModal?.payUrl}
        onClose={() =>
          setIsOpenTopupConfirmationModal({ open: false, payUrl: "" })
        }
      />
    </>
  );
};

export default Topup;
