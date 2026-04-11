import { useEffect, useState } from "react";
import UserWrapper from "../../components/UserWrapper";
import Post from "../../components/Post";
import { http } from "../../api";
import type { APIResponseType } from "../../api/type";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import { useUser } from "../../contexts/user.context";
import { useTranslation } from "react-i18next";
import { useNavigate, useSearchParams } from "react-router";
import Pagination from "../../components/Pagination";
import { isMobile } from "../../utils/utils";

const History = () => {
  const { t } = useTranslation();
  const { userInfo } = useUser();
  const navigate = useNavigate();
  const [historyList, setHistoryList] = useState<any>({});

  const [active, setActive] = useState(1);
  const [searchParams, setSearchParams] = useSearchParams();

  const handleGetHistoryList = async (pageNum: number = 1) => {
    const params = {
      token: userInfo?.token,
      page: searchParams.get("page") || pageNum,
      limit: isMobile() ? 9 : 10,
    };

    const res = await http.post<APIResponseType>(
      API_ENDPOINTS.userHistory,
      params
    );

    if (res?.data?.code === 1) {
      // console.log("res", res);
      setHistoryList(res?.data?.data);
    }
  };

  const activeHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    searchParams.set("page", num.toString());
    setSearchParams(searchParams);
  };

  useEffect(() => {
    handleGetHistoryList();
  }, [userInfo?.token]);

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    handleGetHistoryList(page);
    setActive(page);
  }, [searchParams]);

  return (
    <div className="h-full">
      <UserWrapper>
        <div className="flex items-center p-4 w-full text-greyscale-900 fixed top-0 left-0 right-0 z-10 bg-white h-14 leading-6 lg:hidden">
          <img
            src="/assets/images/icon-cheveron-left.svg"
            alt="arrow-left"
            onClick={() => navigate("/")}
            className="w-6 h-6 cursor-pointer"
          />
          <p className="font-semibold text-center w-full ">{t("user.readingHistory")}</p>
        </div>

        <div className="p-4 lg:p-6">
          <p className="text-sm text-greyscale-500">
            {t("user.readingHistory1")}
          </p>
        </div>
        {/* 阅读列表 */}
        <div className="px-4 pb-4 lg:px-6 lg:pb-6">
          <div className="min-h-[calc(100vh-56px)] lg:min-h-auto">
            <div className="flex flex-wrap gap-1.5 lg:gap-6">
              {historyList?.data?.map((item: any) => (
                <div key={item.id} className="relative min-w-[113px] min-h-[239px] xl:min-w-[185px] xl:min-h-[342px]">
                  <Post item={item} fixedHeight={true} postDivClassName="h-full" showTag={true} />
                </div>
              ))}
            </div>
          </div>
        </div>
        {historyList?.last_page && historyList?.last_page > 1 && (
          <div>
            <Pagination
              active={active}
              size={historyList?.last_page || 1}
              step={1}
              total={historyList?.total || 0}
              onClickHandler={activeHandler}
            />
          </div>
        )}
      </UserWrapper>
    </div>
  );
};

export default History;
