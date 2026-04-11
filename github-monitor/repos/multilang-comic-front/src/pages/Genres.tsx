import { useEffect, useMemo, useRef, useState } from "react";
import { useResourceContext } from "../contexts/resource.context";
import { useTranslation } from "react-i18next";
import useComicLists from "../hooks/useComicLists";
import { COMIC_LIST_TYPE } from "../utils/constant";
import { useSearchParams } from "react-router";
import Pagination from "../components/Pagination";
import Empty from "../components/Empty";
import Post from "../components/Post";
import RandomPostSection from "../components/RandomPostSection";
import { isNil, pickBy } from "lodash";

const Genres = () => {
  const { t } = useTranslation();
  const { categories } = useResourceContext();
  // console.log("categories", categories);

  const allCategory = useMemo(() => ({ id: 0, name: t("common.all") }), [t]);
  const [searchParams, setSearchParams] = useSearchParams();

  const [active, setActive] = useState(1);
  const [isOpenGenresDropdown, setIsOpenGenresDropdown] = useState(false);
  const [currentCategory, setCurrentCategory] = useState<any>(allCategory);
  const [currentTab] = useState<any>(allCategory);
  const [reorderedCategories, setReorderedCategories] = useState<any[]>(categories);

  // 漫画分类 - OnChange
  const handleClickCategoryOnChange = (value: any) => {
    const currentCategoryValue = [allCategory, ...reorderedCategories]?.find((item) => {
      return item.id === value?.id;
    });

    // Swap category if it's in the dropdown (index >= 5)
    const selectedIndex = reorderedCategories.findIndex(
      (cat) => cat.id === value?.id
    );
    if (selectedIndex >= 5) {
      const newOrder = [...reorderedCategories];
      // Remove the selected category from its current position
      const [selectedCategory] = newOrder.splice(selectedIndex, 1);
      // Insert it at the last visible position (index 4, which is the 5th category)
      newOrder.splice(4, 0, selectedCategory);
      setReorderedCategories(newOrder);
    }

    setCurrentCategory(currentCategoryValue);

    setSearchParams((prev: any) => {
      return {
        ...prev,
        page: 1,
        type: COMIC_LIST_TYPE.CATEGORY,
        tag: currentTab?.id,
        ticai_id: currentCategoryValue?.id,
      };
    });

    // Close the mobile dropdown after selection
    setIsOpenGenresDropdown(false);
  };
  const [comicFinishedParams, setComicFinishedParams] = useState<any>({
    type: COMIC_LIST_TYPE.CATEGORY,
    tag: currentTab?.id,
    ticai_id: currentCategory?.id,
    page: 1,
    limit: 18,
  });
  const { data: comicListsByGenres } = useComicLists(comicFinishedParams);
  // console.log("comicFinishedParams", comicFinishedParams);
  // console.log("comicListsByGenres", comicListsByGenres);

  // 分页 - OnChange
  const paginationHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    const params: any = {
      page: num.toString(),
      type: COMIC_LIST_TYPE.CATEGORY,
    };

    if (searchParams.get("ticai_id")) {
      params.ticai_id = searchParams.get("ticai_id");
    }

    if (searchParams.get("tag")) {
      params.tag = searchParams.get("tag");
    }
    setSearchParams(params);
  };

  // Sync reordered categories when categories from context change
  useEffect(() => {
    const ticai_id = searchParams.get("ticai_id") || "";

    // Check if selected category is in dropdown (index >= 5)
    if (ticai_id) {
      const selectedIndex = categories.findIndex(
        (cat) => cat.id?.toString() === ticai_id?.toString()
      );

      if (selectedIndex >= 5) {
        // Category is in dropdown, move it to last visible position
        const newOrder = [...categories];
        const [selectedCategory] = newOrder.splice(selectedIndex, 1);
        newOrder.splice(4, 0, selectedCategory);
        setReorderedCategories(newOrder);
      } else {
        // Category is already visible or not found, use original order
        setReorderedCategories(categories);
      }
    } else {
      // No category selected, use original order
      setReorderedCategories(categories);
    }
  }, [categories, searchParams]);

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    const tag = searchParams.get("tag") || "";
    const ticai_id = searchParams.get("ticai_id") || "";
    // 漫画分类
    if (ticai_id) {
      const currentCategoryValue = [allCategory, ...reorderedCategories]?.find(
        (item) => {
          return item.id?.toString() === ticai_id?.toString();
        }
      );

      setCurrentCategory((prev: any) => ({
        ...prev,
        category: currentCategoryValue,
      }));
    }
    const formattedParams = pickBy(
      {
        tag: tag || "",
        ticai_id: ticai_id || "",
        type: COMIC_LIST_TYPE.CATEGORY,
        page: page,
        limit: 18,
      },
      (value) => !isNil(value) && value !== ""
    );
    setComicFinishedParams(formattedParams);
  }, [searchParams, reorderedCategories, allCategory]);

  // more dropdown
  const genresDropdownRef = useRef<HTMLDivElement>(null);
  const mobileDropdownRef = useRef<HTMLDivElement>(null);
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  // Auto-scroll to selected category in mobile view
  useEffect(() => {
    const ticai_id = searchParams.get("ticai_id") || "0";
    if (scrollContainerRef.current) {
      const selectedElement = scrollContainerRef.current.querySelector(
        `[data-category-id="${ticai_id}"]`
      ) as HTMLElement;

      if (selectedElement) {
        selectedElement.scrollIntoView({
          behavior: 'smooth',
          inline: 'center',
          block: 'nearest'
        });
      }
    }
  }, [searchParams, reorderedCategories]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      // Check if click is outside both desktop and mobile dropdowns
      const isOutsideDesktop = genresDropdownRef.current && !genresDropdownRef.current.contains(event.target as Node);
      const isOutsideMobile = mobileDropdownRef.current && !mobileDropdownRef.current.contains(event.target as Node);

      if (isOutsideDesktop && isOutsideMobile) {
        setIsOpenGenresDropdown(false);
      }
    }

    if (isOpenGenresDropdown) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpenGenresDropdown]);

  return (
    <div className="w-full h-full p-4 relative max-w-screen-xl mt-[49px] mx-auto lg:mt-0">
      {/* mobile filter */}
      <div className="w-full lg:hidden">
        <div className="-ml-2 flex items-center gap-2">
          <div className="cursor-pointer" onClick={() => setIsOpenGenresDropdown(true)}>
            <img
              className="w-6 h-6 min-w-6 min-h-6"
              src={`${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/icon-bars-arrow-down-black.svg`}
              alt="arrow-down"
            />
          </div>
          <div ref={scrollContainerRef} className="flex items-center gap-2 overflow-x-auto scrollbar-hide">
            {[allCategory, ...reorderedCategories].map((category) => (
              <p
                key={category?.id}
                data-category-id={category?.id}
                className={`text-sm rounded-lg px-2 py-1 min-w-max leading-4 ${(parseInt(searchParams.get("ticai_id") || "0") === (category?.id))
                  ? "bg-primary-100 text-primary font-semibold"
                  : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                  }`}
                onClick={() => handleClickCategoryOnChange(category)}
              >
                {category.name}
              </p>
            ))}
          </div>
        </div>
        {isOpenGenresDropdown && (
          <div className="absolute top-0 left-0 w-full h-screen bg-black/70 z-1">
            <div ref={mobileDropdownRef}>
              <div className="-ml-2 flex items-center gap-2 bg-white pt-4 px-4 cursor-pointer">
                <img
                  className="w-6 h-6"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-bars-arrow-up-pink.svg`}
                  alt="arrow-up"
                />
                <p className="text-sm font-semibold">{t("common.pleaseSelectGenre")}</p>
              </div>
              <div className="flex items-center gap-2 flex-wrap bg-white py-4 px-4 max-h-[500px] overflow-y-auto scrollbar-hide">
                {[allCategory, ...reorderedCategories].map((category) => (
                  <p
                    key={category?.id}
                    className={`text-sm rounded-lg px-2 py-1 min-w-max leading-4 ${(parseInt(searchParams.get("ticai_id") || "0") === category?.id)
                      ? "bg-primary-100 text-primary font-semibold"
                      : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                      }`}
                    onClick={() => handleClickCategoryOnChange(category)}
                  >
                    {category.name}
                  </p>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* desktop filter */}
      <div ref={genresDropdownRef} className="hidden relative w-max max-w-full lg:block">
        <div className="overflow-x-auto scrollbar-hide">
          <div className="flex items-center gap-4">
            <ul className="flex items-center gap-4">
              {[allCategory, ...reorderedCategories].slice(0, 6).map((category) => (
                <li
                  key={category?.id}
                  className={`rounded-lg px-3 py-2 min-w-max leading-6 cursor-pointer ${parseInt(searchParams.get("ticai_id") || "0") === category?.id
                    ? "bg-primary-100 text-primary font-semibold"
                    : "bg-greyscale-200 text-greyscale-900 hover:bg-primary-100 hover:text-primary"
                    }`}
                  onClick={() => {
                    setIsOpenGenresDropdown(false);
                    handleClickCategoryOnChange(category)
                  }}
                >
                  {category.name}
                </li>
              ))}
            </ul>

            {reorderedCategories.length > 5 && (<button
              type="button"
              className={`rounded-lg px-3 py-2 min-w-max leading-6 flex items-center gap-1 cursor-pointer ${isOpenGenresDropdown || [...reorderedCategories].slice(5).some(item => item.id === parseInt(searchParams.get("ticai_id") || "0"))
                ? "bg-primary-100 text-primary font-semibold"
                : "bg-greyscale-200 text-greyscale-900 hover:bg-primary-100 hover:text-primary"}
              `}
              onClick={() => setIsOpenGenresDropdown(!isOpenGenresDropdown)}
            >
              <span>{t("common.filterMore")}</span>
              <img
                className="w-6 h-6"
                src={`${import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/${isOpenGenresDropdown
                    ? 'icon-cheveron-up-pink.svg'
                    : [...reorderedCategories].slice(5).some(item => item.id === parseInt(searchParams.get("ticai_id") || "0"))
                      ? 'icon-cheveron-down-pink.svg'
                      : 'icon-cheveron-down-black.svg'
                  }`}
                alt="more genre"
              />
            </button>)}
          </div>
        </div>

        {isOpenGenresDropdown && (
          <ul className="absolute top-12 right-0 bg-white p-4 rounded-lg shadow-sm z-[9] w-[290px]">
            {[...reorderedCategories].slice(5).map((category) => (
              <li
                key={category?.id}
                className={`px-3 py-2 cursor-pointer rounded-lg ${parseInt(searchParams.get("ticai_id") || "0") === category?.id
                  ? "bg-primary-100 text-primary font-semibold"
                  : "hover:bg-primary-100 hover:text-primary"
                  }`}
                onClick={() => {
                  setIsOpenGenresDropdown(false);
                  handleClickCategoryOnChange(category)
                }}
              >
                {category.name}
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="w-full pb-10 pt-4 lg:pt-6">
        {comicListsByGenres?.data?.length &&
          comicListsByGenres?.data?.length > 0 ? (
          <div className="grid grid-cols-3 gap-y-1.5 gap-x-1.5 xs:grid-cols-5 lg:grid-cols-5 lg:gap-x-6 lg:gap-y-6 xl:grid-cols-6">
            {comicListsByGenres?.data?.map((item: any) => (
              <Post key={item.id} item={item} fixedHeight={true} />
            ))}
          </div>
        ) : (
          <Empty />
        )}
        {comicListsByGenres?.last_page && comicListsByGenres?.last_page > 1 ? (
          <div>
            <Pagination
              active={active}
              size={comicListsByGenres?.last_page || 1}
              step={1}
              total={comicListsByGenres?.total || 0}
              onClickHandler={paginationHandler}
            />
          </div>
        ) : null}
        <div>
          <RandomPostSection />
        </div>
      </div>
    </div>
  );
};

export default Genres;
