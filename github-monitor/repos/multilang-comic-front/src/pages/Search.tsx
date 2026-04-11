import { useEffect, useState } from "react";
// import ButtonGroups from "../components/ButtonGroups";
// import search from "/assets/images/icon-search.svg";
import { useSearchParams } from "react-router";
import { API_ENDPOINTS } from "../api/api-endpoint";
import type { APIResponseType } from "../api/type";
import { http } from "../api";
import Post from "../components/Post";
import { useTranslation } from "react-i18next";
import Pagination from "../components/Pagination";
import RandomPostSection from "../components/RandomPostSection";
// import { useResourceContext } from "../contexts/resource.context";
import { useNavigate } from "react-router";

const Search = () => {
  const { t } = useTranslation();

  // const { tags, categories } = useResourceContext();
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  const allCategory = { id: "0", name: t("common.all") };

  const [keyword, setKeyword] = useState("");
  const [active, setActive] = useState(1);
  const [comicSearchLists, setComicSearchLists] = useState<any>([]);

  // const [currentCategory, setCurrentCategory] = useState<any>({
  const [currentCategory] = useState<any>({
    category: allCategory,
    tag: allCategory,
  });

  // 分类
  // const handleClickCategoryOnChange = (value: any) => {
  //   const currentCategory = [allCategory, ...categories]?.find((item) => {
  //     return item.id === value?.id;
  //   });
  //   // console.log("currentCategory", currentCategory);
  //   setCurrentCategory((prev: any) => ({
  //     ...prev,
  //     category: currentCategory,
  //   }));
  // };

  // 标签
  // const handleClickTagOnChange = (value: any) => {
  //   const currentTag = [allCategory, ...tags]?.find((item) => {
  //     return item.id === value?.id;
  //   });
  //   // console.log("currentTag", currentTag);
  //   setCurrentCategory((prev: any) => ({
  //     ...prev,
  //     tag: currentTag,
  //   }));
  // };

  // 搜索
  const handleGetComicLists = async (pageNum: number = 1) => {
    try {
      const params: any = {
        // keyword,
        ticai_id: currentCategory?.category?.id,
        tag: currentCategory?.tag?.id,
        page: pageNum || searchParams.get("page"),
        page_size: 20,
      };
      if (keyword) {
        params["keyword"] = keyword;
      }
      // console.log("params-keyword", params);
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.comicSearch,
        params
      );
      if (res?.data?.code === 1) {
        // console.log("res-search", res);
        setComicSearchLists(res?.data?.data);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  const activeHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);
    handleGetComicLists(num);

    setSearchParams({
      page: num.toString(),
      keyword,
    });
  };

  useEffect(() => {
    handleGetComicLists();
  }, [keyword, currentCategory]);

  useEffect(() => {
    const keyword = searchParams.get("keyword");
    setKeyword(keyword || "");
  }, []);

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    handleGetComicLists(page);
    setActive(page);
  }, [searchParams]);

  // useEffect(() => {
  //   const formattedCategories = [allCategory, ...categories];
  //   if (formattedCategories?.length > 0) {
  //     setCurrentCategory((prev: any) => ({
  //       ...prev,
  //       category: formattedCategories?.[0],
  //     }));
  //   }
  // }, [categories]);

  // useEffect(() => {
  //   const formattedTags = [allCategory, ...tags];
  //   if (tags?.length > 0) {
  //     setCurrentCategory((prev: any) => ({
  //       ...prev,
  //       tag: formattedTags?.[0],
  //     }));
  //   }
  // }, [tags]);

  return (
    <div className="min-h-screen lg:min-h-[calc(100vh-162px)] lg:mt-0">
      <div className="w-full px-4 lg:w-[800px] lg:mx-auto">
        <div className="flex items-center p-4 w-full text-greyscale-900 fixed top-0 left-0 right-0 z-10 bg-white h-12 lg:hidden">
          <p className="font-semibold text-center w-full ">{t("common.search")}</p>
          <img
            src="/assets/images/icon-close-black.svg"
            alt="arrow-left"
            className="cursor-pointer"
            onClick={() => navigate("/")}
          />
        </div>

        {/* 标题 */}
        {/* <h2 className="text-[32px] font-medium text-center my-4 max-xs:text-xl max-xs:pr-3">
          {t("search.title")}
        </h2> */}
        {/* 搜索框 */}
        <div className="bg-black/5 rounded-full py-3 px-4 flex items-center gap-2 mt-4 lg:mt-20 lg:py-6">
          <input
            className="bg-transparent outline-none w-full px-2 placeholder:text-greyscale-400"
            type="text"
            placeholder={t("search.placeholder")}
            value={keyword}
            onChange={(e) => setKeyword(e.target.value)}
            autoFocus
          />
          {keyword.length === 0 ? (
            <img
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-search.svg`}
              alt="search"
              className="cursor-pointer"
              onClick={() => handleGetComicLists()}
            />
          ) : (
            <img
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-close-black.svg`}
              alt="clear"
              className="cursor-pointer"
              onClick={() => {
                setKeyword('');
                setSearchParams({ keyword: '' })
              }}
            />
          )}
        </div>
        {/* 分类 */}
        {/* <div className="my-8">
          <div>
            <p className="text-greyscale-900 font-semibold text-xl max-xs:text-base">
              {t("search.category")}
            </p>
            <ButtonGroups
              addonBefore={allCategory}
              list={categories}
              callback={handleClickCategoryOnChange}
            />
          </div>
          <div>
            <p className="text-greyscale-900 font-semibold text-xl max-xs:text-base">
              {t("search.tag")}
            </p>
            <ButtonGroups
              addonBefore={allCategory}
              list={tags}
              callback={handleClickTagOnChange}
            />
          </div>
        </div> */}
      </div>
      <div className="max-w-screen-xl mx-auto pb-10 px-4">
        {/* 漫画列表 */}
        {/* <div className="flex items-center justify-between my-2">
          <div className="flex items-center gap-2">
            <p className="text-primary-dark font-medium text-xl">
              #{currentCategory?.category?.name}
            </p>
          </div>
          <div className="font-light text-greyscale-600">
            <p>
              {t("common.total")}
              <span className="text-primary-dark font-medium mx-1">
                {comicSearchLists?.total || 0}
              </span>
              {t("common.results")}
            </p>
          </div>
        </div> */}
        {/* total result count */}
        {keyword && comicSearchLists?.total >= 0 && (<div>
          <p className="mt-7 text-sm">
            {comicSearchLists?.total || 0}{' '}
            <span className="text-greyscale-500">{t("common.resultsFound")}</span>
          </p>
        </div>)}

        {/* no result */}
        {keyword && comicSearchLists?.total === 0 && (<div className="mt-6 flex flex-col items-center justify-center gap-2 w-80 mx-auto">
          <div className="rounded-full bg-greyscale-100 p-4">
            <img
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-search-result.svg`}
              alt="no result"
              className="w-6 h-6"
            />
          </div>
          <p className="text-sm text-greyscale-500 text-center">{t("common.noSearchResultFound")}</p>
        </div>)}

        {/* result list */}
        {keyword && comicSearchLists?.data?.length > 0 && (
          <div className="my-4 grid grid-cols-3 gap-y-1.5 gap-x-1.5 xs:grid-cols-5 lg:gap-x-6 lg:gap-y-6 xl:grid-cols-6">
            {keyword && comicSearchLists?.data?.map((item: any) => (
              <Post key={item.id} item={item} fixedHeight={true} />
            ))}
          </div>
        )}

        {keyword && comicSearchLists?.last_page > 1 && (
          <div className="py-1">
            <Pagination
              active={active}
              size={comicSearchLists?.last_page || 1}
              step={1}
              total={comicSearchLists?.total || 0}
              onClickHandler={activeHandler}
            />
          </div>
        )}

        {keyword && comicSearchLists?.total === 0 && (<div className="mt-10">
          <RandomPostSection />
        </div>)}
      </div>
    </div>
  );
};

export default Search;
