import { useEffect, useState } from "react";
import Banner from "../components/Banner";

import { useSearchParams } from "react-router";
import Pagination from "../components/Pagination";
import RandomPostSection from "../components/RandomPostSection";
import Post from "../components/Post";
import { useTranslation } from "react-i18next";
import { useResourceContext } from "../contexts/resource.context";
import useComicLists from "../hooks/useComicLists";

const Free = () => {
  const { t } = useTranslation();
  const [searchParams, setSearchParams] = useSearchParams();
  const { bannersFree } = useResourceContext();

  const [active, setActive] = useState(1);
  const [comicFreeParams, setComicFreeParams] = useState<any>({
    xianmian: "1",
    page: 1,
    limit: 18,
  });
  const { data: comicListsByFree } = useComicLists(comicFreeParams);

  // Pagination - OnChange
  const paginationHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    setSearchParams({
      page: num.toString(),
    });
  };

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");

    setComicFreeParams({
      xianmian: "1",
      page: page,
      limit: 18,
    });

    setActive(page);
  }, [searchParams]);

  // console.log("bannersFree", bannersFree);
  return (
    <div>
      <div className="max-w-screen-xl mx-auto">
        {bannersFree?.length > 0 && (
          <div className="mt-[61px] lg:mt-6">
            <div className="flex items-center justify-between my-2 max-xs:hidden">
              <div className="flex items-center gap-2 max-xs:hidden">
                <img
                  className="w-7"
                  src={`${
                    import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/icon-free.png`}
                  alt="free"
                />
                <p className="text-greyscale-900 font-semibold text-xl">
                  {t("common.freeRecommend")}
                </p>
              </div>
            </div>
            <Banner pagination={true} banners={bannersFree} />
          </div>
        )}
      </div>

      <div className="max-w-screen-xl mx-auto px-4">
        <div className="mt-[61px] lg:mt-6">
          {/* 限免 标题 */}
          {/* <div className="flex items-center justify-between my-2">
            <div className="flex items-center gap-2">
              <img
                className="w-7 max-xs:w-5"
                src={`${
                  import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/icon-free.png`}
                alt="free"
              />
              <p className="text-greyscale-900 font-semibold text-xl max-xs:text-base">
                {t("common.free")}
              </p>
            </div>
            <div className="font-light text-greyscale-600">
              <p>
                {t("common.total")}
                <span className="text-primary-dark font-medium mx-1">
                  {comicListsByFree?.total || 0}
                </span>
                {t("common.results")}
              </p>
            </div>
          </div> */}
          {/* 新书上架 内容 */}
          <div className="w-full pb-10">
            <div className="grid grid-cols-3 gap-y-1.5 gap-x-1.5 xs:grid-cols-5 lg:grid-cols-5 lg:gap-x-6 lg:gap-y-6 xl:grid-cols-6">
              {comicListsByFree?.data?.map((item: any) => (
                <Post
                  key={item.id}
                  item={item}
                  fixedHeight={true}
                  showTag={true}
                />
              ))}
            </div>
            {comicListsByFree && comicListsByFree?.last_page > 1 && (
              <div>
                <Pagination
                  active={active}
                  size={comicListsByFree?.last_page || 1}
                  step={1}
                  total={comicListsByFree?.total || 0}
                  onClickHandler={paginationHandler}
                />
              </div>
            )}
            <div>
              <RandomPostSection />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Free;
