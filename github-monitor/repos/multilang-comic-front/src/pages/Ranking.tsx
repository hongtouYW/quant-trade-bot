import { useState } from "react";
import { COMIC_RANK_TYPE } from "../utils/constant";

import { useTranslation } from "react-i18next";
import useComicAllRank from "../hooks/useComicAllRank";
import Post from "../components/Post";
import RandomPostSection from "../components/RandomPostSection";

const list = [
  {
    id: COMIC_RANK_TYPE.BEST_SELLER,
    label: "ranking.bestSeller",
    value: COMIC_RANK_TYPE.BEST_SELLER,
    children: "hot",
  },
  {
    id: COMIC_RANK_TYPE.POPULAR,
    label: "ranking.popular",
    value: COMIC_RANK_TYPE.POPULAR,
    children: "popular",
  },
  {
    id: COMIC_RANK_TYPE.FINISHED,
    label: "ranking.finished",
    value: COMIC_RANK_TYPE.FINISHED,
    children: "finished",
  },
  {
    id: COMIC_RANK_TYPE.SUBSCRIBE,
    label: "ranking.subscribe",
    value: COMIC_RANK_TYPE.SUBSCRIBE,
    children: "subscribe",
  },
];

const Ranking = () => {
  const { t } = useTranslation();

  const [currentActiveRanking, setCurrentActiveRanking] = useState<any>(
    list?.[0]
  );
  const [isOpenDropdown, setIsOpenDropdown] = useState(false);

  const { data: comicRanksLists } = useComicAllRank({ range: "all" });

  return (
    <div className="w-full h-full p-4 relative max-w-screen-xl mt-[49px] mx-auto lg:mt-0 lg:pb-10">
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
            {list.map((item) => (
              <p
                key={item?.id}
                className={`text-sm rounded-lg px-2 py-1 min-w-max leading-[16px] ${currentActiveRanking?.id === item?.id
                  ? "bg-primary-100 text-primary"
                  : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                  }`}
                onClick={() => setCurrentActiveRanking(item)}
              >
                {t(item?.label)}
              </p>
            ))}
          </div>
        </div>
        {isOpenDropdown && (
          <div className="absolute top-4 left-0 w-full h-screen bg-black/70 z-10" onClick={() => setIsOpenDropdown(false)}>
            <div className="-ml-2 flex items-center gap-1 bg-white px-4 cursor-pointer">
              <img
                className="w-6 h-6"
                src={`${import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/icon-bars-arrow-up-pink.svg`}
                alt="arrow-up"
              />
              <p className="text-sm font-semibold">{t("common.pleaseSelectRanking")}</p>
            </div>
            <div className="flex items-center gap-2 flex-wrap bg-white py-4 px-4 max-h-[500px] overflow-y-auto">
              {list.map((item) => (
                <p
                  key={item?.id}
                  className={`text-sm rounded-lg px-2 py-1 min-w-max leading-[16px] ${currentActiveRanking?.id === item?.id
                    ? "bg-primary-100 text-primary"
                    : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                    }`}
                  onClick={() => setCurrentActiveRanking(item)}
                >
                  {t(item?.label)}
                </p>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* desktop filter */}
      <div className="hidden relative w-max max-w-full lg:block">
        <div className="overflow-x-auto">
          <div className="flex items-center gap-4">
            <ul className="flex items-center gap-4">
              {list.map((item) => (
                <li
                  key={item?.id}
                  className={`rounded-lg px-3 py-2 min-w-max leading-6 cursor-pointer ${currentActiveRanking?.id === item?.id
                    ? "bg-primary-100 text-primary font-semibold"
                    : "bg-greyscale-200 text-greyscale-900 hover:bg-primary-100 hover:text-primary"
                    }`}
                  onClick={() => setCurrentActiveRanking(item)}
                >
                  {t(item?.label)}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>

      <div className="w-full py-4 lg:py-6">
        <div className="grid grid-cols-3 gap-y-1.5 gap-x-1.5 xs:grid-cols-5 lg:grid-cols-5 lg:gap-x-6 lg:gap-y-6 xl:grid-cols-6">
          {(comicRanksLists as any)?.[
            currentActiveRanking?.children as keyof typeof comicRanksLists
          ]?.data?.map((item: any, index: number) => (
            <div key={item.id} className="relative">
              <Post key={item.id} item={item} index={index + 1} fixedHeight={true} showRank={true} postDivClassName="h-full" />
            </div>
          ))}
        </div>
      </div>

      <div>
        <RandomPostSection />
      </div>
    </div>
  );
};

export default Ranking;
