import { useEffect, useState } from "react";
import { API_ENDPOINTS } from "../../../api/api-endpoint";
import { useUser } from "../../../contexts/user.context";
import type { APIResponseType } from "../../../api/type";
import { http } from "../../../api";
import { useTranslation } from "react-i18next";
import { orderStatus } from "../../../utils/enum";
import { toFmt } from "../../../utils/utils";

const RechargeRecord = () => {
  const { t } = useTranslation();
  const { userInfo } = useUser();

  const [type, setType] = useState<string>("1");
  const [list, setList] = useState<any>({
    data: [],
  });
  const [chapterList, setChapterList] = useState<any>({
    data: [],
  });
  const currency = import.meta.env.VITE_DEFAULT_CURRENCY;

  const handleGetUserOrderList = async () => {
    try {
      const params = {
        token: userInfo?.token,
      };
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userOrderList,
        params
      );

      if (res?.data?.code === 1) {
        // console.log("res-rechargeRecord", res?.data?.data);
        setList(res?.data?.data);
      }
    } catch (error) {
      console.error("error", error);
    }
  };
  const handleGetUserChapterList = async () => {
    try {
      const params = {
        token: userInfo?.token,
      };
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userChapterList,
        params
      );

      if (res?.data?.code === 1) {
        // console.log("res-rechargeRecord", res?.data?.data);
        setChapterList(res?.data?.data);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  useEffect(() => {
    handleGetUserOrderList();
    handleGetUserChapterList();
  }, []);

  return (
    <div className="mt-4 mb-10 lg:mt-6">
      <div>
        <div className="flex items-center gap-[6px] pb-4 lg:gap-4">
          <p
            className={`text-sm leading-[14px] px-1 py-2 rounded-lg ${type === "1"
              ? "text-primary-600 bg-primary-100 font-semibold cursor-pointer"
              : "text-greyscale-900 bg-greyscale-200 cursor-pointer"
              } lg:text-base lg:leading-6 lg:py-2 lg:px-[12px]`}
            onClick={() => setType("1")}
          >
            {t("user.episodePurchaseHistory")}
          </p>
          <p
            className={`text-sm leading-[14px] px-1 py-2 rounded-lg ${type === "2"
              ? "text-primary-600 bg-primary-100 font-semibold cursor-pointer"
              : "text-greyscale-900 bg-greyscale-200 cursor-pointer"
              } lg:text-base lg:leading-6 lg:py-2 lg:px-[12px]`}
            onClick={() => setType("2")}
          >
            {t("user.transactionHistory")}
          </p>
        </div>
        <div>
          {/* mobile transaction */}
          <div className="lg:hidden">
            {type === "2" &&
              list?.data?.length > 0 &&
              list?.data?.map((item: any) => (
                <div key={item.id} className="flex flex-col gap-[4px] py-4 border-b border-greyscale-200">
                  <div className="flex items-center justify-between">
                    <p className="text-sm leading-[20px] text-greyscale-900 line-clamp-1">
                      {item?.pro_info?.intro}
                    </p>
                    <p className="text-sm leading-[20px] text-greyscale-900 font-semibold">
                      {currency} {item?.money}
                    </p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-xs leading-[18px] text-greyscale-500">
                      {toFmt(item?.addtime || 0, "YYYY/MM/DD HH:mm:ss")}
                    </p>
                    <p className="text-xs leading-[18px] text-greyscale-500">
                      {t(
                        orderStatus[item?.orderswitch as keyof typeof orderStatus]
                          ?.locale
                      )}
                    </p>
                  </div>
                </div>
              ))}
          </div>
          {type === "2" && list?.data?.length === 0 && (
            <div className="mt-6 flex flex-col items-center justify-center gap-2 w-[300px] mx-auto lg:hidden">
              <div className="rounded-full bg-greyscale-100 p-4">
                <img
                  src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-file.svg`}
                  alt="no result"
                  className="w-6 h-6"
                />
              </div>
              <p className="text-sm text-greyscale-500 text-center">{t("common.noRecordDisplay")}</p>
            </div>
          )}
          {/* desktop transaction */}
          {type === "2" && (
            <div className="hidden lg:block p-1 max-h-[1000px] overflow-y-auto">
              <table className="w-full outline outline-greyscale-200 rounded-2xl overflow-hidden">
                <thead className="bg-greyscale-100 ">
                  <tr className="text-left text-greyscale-900">
                    <th className="py-4 px-3 font-normal">{t("user.rechargeDate")}</th>
                    <th className="py-4 px-3 font-normal w-2/5">{t("user.rechargeInfo")}</th>
                    <th className="py-4 px-3 font-normal">{t("user.rechargeAmount")}</th>
                    <th className="py-4 px-3 font-normal">{t("user.status")}</th>
                  </tr>
                </thead>
                <tbody>
                  {
                    list?.data?.length > 0 && list?.data?.map((item: any, i: number) => (
                      <tr key={item.id} className={`${i + 1 !== list?.data?.length ? 'border-b border-greyscale-200' : ''}`}>
                        <td className="py-4 px-3">
                          {toFmt(item?.addtime || 0, "YYYY/MM/DD HH:mm:ss")}
                        </td>
                        <td className="py-4 px-3">
                          <span className="line-clamp-1">{item?.pro_info?.intro}</span>
                        </td>
                        <td className="py-4 px-3 font-semibold">
                          {currency} {item?.money}
                        </td>
                        <td className="py-4 px-3 text-orange-500">
                          {t(
                            orderStatus[item?.orderswitch as keyof typeof orderStatus]
                              ?.locale
                          )}
                        </td>
                      </tr>
                    ))
                  }
                  {
                    list?.data?.length === 0 && (
                      <tr>
                        <td colSpan={4} className="text-center py-10">
                          <div className="flex flex-col items-center justify-center gap-2 mx-auto">
                            <div className="rounded-full bg-greyscale-100 p-4">
                              <img
                                src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-file.svg`}
                                alt="no result"
                                className="w-6 h-6"
                              />
                            </div>
                            <p className="text-sm text-greyscale-500 text-center">{t("common.noRecordDisplay")}</p>
                          </div>
                        </td>
                      </tr>
                    )
                  }
                </tbody>
              </table>
            </div>
          )}

          {/* mobile episode purchase */}
          <div className="lg:hidden">
            {type === "1" &&
              chapterList?.data?.length > 0 &&
              chapterList?.data?.map((item: any) => (
                <div key={item.id} className="flex flex-col gap-[4px] py-4 border-b border-greyscale-200">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm leading-[20px] text-greyscale-900 line-clamp-1">
                      {item?.title?.chapter}
                    </p>
                    <p className="text-sm leading-[20px] text-greyscale-900 font-semibold">
                      {`${item?.score} ${t("common.coin")}`}
                    </p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-xs leading-[18px] text-greyscale-500">
                      {toFmt(parseInt(item?.buytime || "0") * 1000 || 0, "YYYY/MM/DD HH:mm:ss")}
                    </p>
                    <p className="text-xs leading-[18px] text-greyscale-500">
                      {t(
                        orderStatus[item?.orderswitch as keyof typeof orderStatus]
                          ?.locale
                      )}
                    </p>
                  </div>
                </div>
              ))}
          </div>
          {type === "1" && chapterList?.data?.length === 0 && (
            <div className="mt-6 flex flex-col items-center justify-center gap-2 w-[300px] mx-auto lg:hidden">
              <div className="rounded-full bg-greyscale-100 p-4">
                <img
                  src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-file.svg`}
                  alt="no result"
                  className="w-6 h-6"
                />
              </div>
              <p className="text-sm text-greyscale-500 text-center">{t("common.noRecordDisplay")}</p>
            </div>
          )}
          {/* desktop episode purchase */}
          {type === "1" && (
            <div className="hidden lg:block p-1 max-h-[1000px] overflow-y-auto">
              <table className="w-full outline outline-greyscale-200 rounded-2xl overflow-hidden">
                <thead className="bg-greyscale-100 ">
                  <tr className="text-left text-greyscale-900">
                    <th className="py-4 px-3 font-normal">{t("user.rechargeDate")}</th>
                    <th className="py-4 px-3 font-normal w-3/5">{t("user.name")}</th>
                    <th className="py-4 px-3 font-normal">{t("user.coinSpent")}</th>
                  </tr>
                </thead>
                <tbody>
                  {
                    chapterList?.data?.length > 0 && chapterList?.data?.map((item: any, i: number) => (
                      <tr key={item.id} className={`${i + 1 !== list?.data?.length ? 'border-b border-greyscale-200' : ''}`}>
                        <td className="py-4 px-3">
                          {toFmt(parseInt(item?.buytime || "0") * 1000 || 0, "YYYY/MM/DD HH:mm:ss")}
                        </td>
                        <td className="py-4 px-3">
                          <span className="line-clamp-1">{item?.title?.chapter}</span>
                        </td>
                        <td className="py-4 px-3 font-semibold">
                          {`${item?.score} ${t("common.coin")}`}
                        </td>
                      </tr>
                    ))
                  }
                  {
                    chapterList?.data?.length === 0 && (
                      <tr>
                        <td colSpan={3} className="text-center py-10">
                          <div className="flex flex-col items-center justify-center gap-2 mx-auto">
                            <div className="rounded-full bg-greyscale-100 p-4">
                              <img
                                src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-file.svg`}
                                alt="no result"
                                className="w-6 h-6"
                              />
                            </div>
                            <p className="text-sm text-greyscale-500 text-center">{t("common.noRecordDisplay")}</p>
                          </div>
                        </td>
                      </tr>
                    )
                  }
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default RechargeRecord;
