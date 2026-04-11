import RandomPostSection from "../components/RandomPostSection";
import Post from "../components/Post";
import Pagination from "../components/Pagination";
import { useSearchParams } from "react-router";
import { useEffect, useState } from "react";
import { COMIC_LIST_TYPE } from "../utils/constant";
// import { useTranslation } from "react-i18next";
import useComicLists from "../hooks/useComicLists";

const New = () => {
  // const { t } = useTranslation();
  const [searchParams, setSearchParams] = useSearchParams();

  const [active, setActive] = useState(1);
  const [comicLatestParams, setComicLatestParams] = useState<any>({
    type: COMIC_LIST_TYPE.LASTEST,
    page: 1,
    limit: 18,
  });

  const { data: comicListsByLatest } = useComicLists(comicLatestParams);

  const paginationHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    setSearchParams({
      page: num.toString(),
    });
  };

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    if (page) {
      setComicLatestParams({
        type: COMIC_LIST_TYPE.LASTEST,
        page: page,
        limit: 18,
      });
      setActive(page);
    }
  }, [searchParams]);

  return (
    <div className="max-w-screen-xl mx-auto px-4">
      <div className="mt-[61px] lg:mt-6">
        {/* 新书上架 标题 */}
        {/* <div className="flex items-center justify-between my-2">
          <div className="flex items-center gap-[6px]">
            <img
              className="w-7 max-xs:w-5"
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/new-book.png`}
              alt="new"
            />
            <p className="text-greyscale-900 font-semibold text-xl max-xs:text-base">
              {t("common.newComic")}
            </p>
          </div>
          <div className="font-light text-greyscale-600">
            <p>
              {t("common.total")}
              <span className="text-primary-dark font-medium mx-1">
                {comicListsByLatest?.total || 0}
              </span>
              {t("common.results")}
            </p>
          </div>
        </div> */}
        {/* 新书上架 内容 */}
        <div className="w-full pb-10">
          <div className="min-h-[640px] h-auto">
            <div className="grid grid-cols-3 gap-y-1.5 gap-x-1.5 xs:grid-cols-5 lg:grid-cols-5 lg:gap-x-6 lg:gap-y-6 xl:grid-cols-6">
              {comicListsByLatest?.data?.map((item: any) => (
                <Post key={item.id} item={item} fixedHeight={true} />
              ))}
            </div>
          </div>
          {comicListsByLatest?.last_page &&
            comicListsByLatest?.last_page > 1 && (
              <div>
                <Pagination
                  active={active}
                  size={comicListsByLatest?.last_page || 1}
                  step={1}
                  total={comicListsByLatest?.total || 0}
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
  );
};

export default New;
