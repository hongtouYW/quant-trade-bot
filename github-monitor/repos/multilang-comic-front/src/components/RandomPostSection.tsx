import { useState } from "react";
// import { useComic } from "../contexts/comic.context";
import Post from "./Post";
import { useTranslation } from "react-i18next";
import useRandomComic from "../hooks/useRandomComic";

const RandomPostSection = () => {
  const { t } = useTranslation();

  const { data: randomComicData, repick } = useRandomComic();

  const [isRefreshing, setIsRefreshing] = useState(false);

  const handleRefresh = () => {
    setIsRefreshing(true);
    repick();
  };

  return (
    <div className="max-w-screen-xl mx-auto">
      <div className="flex items-center justify-between pt-4">
        <div className="flex items-center gap-2">
          {/* <img
            className="w-6 h-6 max-xs:w-5 max-xs:h-5"
            src={`${
              import.meta.env.VITE_INDEX_DOMAIN
            }/assets/images/icon-love.png`}
            alt="hot"
          /> */}
          <h4 className="font-semibold lg:text-2xl lg:font-bold">
            {t("common.guessYouLike")}
          </h4>
        </div>
        <div
          className="flex items-center gap-1 cursor-pointer"
          onClick={handleRefresh}
        >
          <p className="text-primary text-sm font-semibold lg:text-xl">
            {t("common.random")}
          </p>
          <img
            src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-refresh.svg`}
            alt="refresh"
            onAnimationEnd={() => setIsRefreshing(false)} // stop spinning after 1s
            className={`w-6 h-6 ${
              isRefreshing ? "animate-spin-once" : ""
            }`}
          />
        </div>
      </div>

      <div className="flex gap-1.5 overflow-x-auto scrollbar-hide py-4 -mr-4 pr-4 lg:gap-6 xl:grid xl:grid-cols-6">
        {randomComicData?.slice(0, 6)?.map((item: any) => (
          <Post key={item.id} item={item} fixedHeight={true} />
        ))}
      </div>
    </div>
  );
};

export default RandomPostSection;
