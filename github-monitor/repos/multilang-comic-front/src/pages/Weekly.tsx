import { useEffect, useState } from "react";
// import Tabs from "../components/Tabs";
import Post from "../components/Post";
import Pagination from "../components/Pagination";
import RandomPostSection from "../components/RandomPostSection";

import { useSearchParams } from "react-router";
import { useTranslation } from "react-i18next";
import useComicLists from "../hooks/useComicLists";

const tabsList = [
  {
    id: 1,
    label: "weekly.monday",
    value: "1",
  },
  {
    id: 2,
    label: "weekly.tuesday",
    value: "2",
  },
  {
    id: 3,
    label: "weekly.wednesday",
    value: "3",
  },
  {
    id: 4,
    label: "weekly.thursday",
    value: "4",
  },
  {
    id: 5,
    label: "weekly.friday",
    value: "5",
  },
  {
    id: 6,
    label: "weekly.saturday",
    value: "6",
  },
  {
    id: 7,
    label: "weekly.sunday",
    value: "7",
  },
];

const Weekly = () => {
  const { t } = useTranslation();
  const [searchParams, setSearchParams] = useSearchParams();

  const [active, setActive] = useState(1);
  const [isOpenDropdown, setIsOpenDropdown] = useState(false);
  const [currentActiveTab, setCurrentActiveTab] = useState<any>(tabsList?.[0]);
  const [comicWeeklyParams, setComicWeeklyParams] = useState<any>({
    weekday: "0",
    page: 1,
    limit: 18,
  });
  const { data: comicListsByWeekly } = useComicLists(comicWeeklyParams);

  // Weekly TabsOnChange
  const handleClickTabOnChange = (value: any) => {
    const currenTab = tabsList?.find((item) => {
      return item.id?.toString() === value;
    });

    setCurrentActiveTab(currenTab);

    setSearchParams({
      weekday: currenTab?.value || "",
      page: "1",
    });
  };

  // PaginationOnChange
  const paginationHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    setSearchParams({
      weekday: searchParams.get("weekday") || "",
      page: num.toString(),
    });
  };

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    const weekday = searchParams.get("weekday") || "1";

    const findTab = tabsList?.find((item) => {
      return item.value === weekday;
    });
    setCurrentActiveTab(findTab || tabsList?.[0]);
    setComicWeeklyParams({
      weekday: findTab?.value || "",
      page: page,
      limit: 18,
    });
    setActive(page);
  }, [searchParams]);

  return (
    <div className="w-full h-full p-4 relative max-w-screen-xl mt-[49px] mx-auto lg:mt-0">
      {/* mobile filter */}
      <div className="w-full lg:hidden">
        <div className="-ml-2 flex items-center gap-2">
          <div className="cursor-pointer" onClick={() => setIsOpenDropdown(true)}>
            <img
              className="w-6 h-6 min-w-6 min-h-6"
              src={`${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/icon-bars-arrow-down-black.svg`}
              alt="arrow-down"
            />
          </div>
          <div className="flex items-center gap-2 overflow-x-auto scrollbar-hide">
            {tabsList.map((item) => (
              <p
                key={item?.id}
                className={`text-sm rounded-lg px-2 py-1 min-w-max leading-[16px] ${currentActiveTab?.id === item?.id
                  ? "bg-primary-100 text-primary"
                  : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                  }`}
                onClick={() => handleClickTabOnChange(item.value)}
              >
                {t(item?.label)}
              </p>
            ))}
          </div>
        </div>
        {isOpenDropdown && (
          <div className="absolute top-0 left-0 w-full h-screen bg-black/70 z-1" onClick={() => setIsOpenDropdown(false)}>
            <div className="bg-white">
              <div className="-ml-2 flex items-center gap-2 bg-white pt-4 px-4 cursor-pointer">
                <img
                  className="w-6 h-6"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-bars-arrow-up-pink.svg`}
                  alt="arrow-up"
                />
                <p className="text-sm font-semibold">{t("common.pleaseSelectDay")}</p>
              </div>
              <div className="flex items-center gap-2 flex-wrap bg-white py-4 px-4 max-h-[500px] overflow-y-auto">
                {tabsList.map((item) => (
                  <p
                    key={item?.id}
                    className={`text-sm rounded-lg px-2 py-1 min-w-max leading-4 ${currentActiveTab?.id === item?.id
                      ? "bg-primary-100 text-primary font-semibold"
                      : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                      }`}
                    onClick={() => handleClickTabOnChange(item.value)}
                  >
                    {t(item?.label)}
                  </p>
                ))}
              </div>
            </div>

          </div>
        )}
      </div>

      {/* desktop filter */}
      <div className="hidden relative w-max max-w-full lg:block">
        <div className="overflow-x-auto">
          <div className="flex items-center gap-4">
            <ul className="flex items-center gap-4">
              {tabsList.map((item) => (
                <li
                  key={item?.id}
                  className={`rounded-lg px-3 py-2 min-w-max leading-6 cursor-pointer ${currentActiveTab?.id === item?.id
                    ? "bg-primary-100 text-primary font-semibold"
                    : "bg-greyscale-200 text-greyscale-900 hover:bg-primary-100 hover:text-primary"
                    }`}
                  onClick={() => handleClickTabOnChange(item.value)}
                >
                  {t(item?.label)}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>

      <div className="w-full pb-10 pt-4 lg:pt-6">
        <div className="min-h-[640px] h-auto">
          <div className="grid grid-cols-3 gap-y-1.5 gap-x-1.5 xs:grid-cols-5 lg:grid-cols-5 lg:gap-x-6 lg:gap-y-6 xl:grid-cols-6">
            {comicListsByWeekly?.data?.map((item: any) => (
              <Post key={item.id} item={item} fixedHeight={true} />
            ))}
          </div>
        </div>
        {comicListsByWeekly && comicListsByWeekly?.last_page > 1 && (
          <div>
            <Pagination
              active={active}
              size={comicListsByWeekly?.last_page || 1}
              step={1}
              total={comicListsByWeekly?.total || 0}
              onClickHandler={paginationHandler}
            />
          </div>
        )}

        <div className="mt-6">
          <RandomPostSection />
        </div>
      </div>
    </div>
  );
};

export default Weekly;
