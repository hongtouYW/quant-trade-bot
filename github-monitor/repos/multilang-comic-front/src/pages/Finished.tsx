import { useEffect, useMemo, useRef, useState } from "react";
import { useSearchParams } from "react-router";
import { useTranslation } from "react-i18next";

import { isNil, pickBy } from "lodash";

import useComicLists from "../hooks/useComicLists";
import { useResourceContext } from "../contexts/resource.context";

import Post from "../components/Post";
import Empty from "../components/Empty";
import Pagination from "../components/Pagination";
import RandomPostSection from "../components/RandomPostSection";

import { COMIC_LIST_MHSTATUS, COMIC_LIST_TYPE } from "../utils/constant";

const tabsList = [
  {
    id: 1,
    label: "common.latestFinished",
    value: "1",
    month: false,
    showTag: false,
    type: COMIC_LIST_TYPE.LASTEST,
  },
  {
    id: 2,
    label: "common.totalSalesRanking",
    value: "2",
    month: false,
    showTag: false,
    type: COMIC_LIST_TYPE.SALES,
  },
  // {
  //   id: 3,
  //   label: t("ranking.annualRanking"),
  //   value: "3",
  //   year: true,
  //   type: COMIC_LIST_TYPE.YEAR,
  // },
  {
    id: 4,
    label: "ranking.monthlyRanking",
    value: "4",
    month: true,
    showTag: false,
    type: COMIC_LIST_TYPE.MONTH,
  },
  {
    id: 5,
    label: "common.recommend",
    value: "5",
    month: false,
    showTag: true,
    type: COMIC_LIST_TYPE.CATEGORY,
  },
  {
    id: 6,
    label: "common.category",
    value: "6",
    month: false,
    showTag: true,
    type: COMIC_LIST_TYPE.BROWSE,
  },
];

const Finished = () => {
  const { t } = useTranslation();
  const { tags, categories } = useResourceContext();

  const allCategory = useMemo(() => ({ id: 0, name: t("common.all") }), [t]);
  const [searchParams, setSearchParams] = useSearchParams();

  const [active, setActive] = useState(1);
  const [currentCategory, setCurrentCategory] = useState<any>({
    category: allCategory,
    tag: allCategory,
  });

  const [currentActiveTab, setCurrentActiveTab] = useState<any>(tabsList?.[0]);
  // const [currentMonthOption, setCurrentMonthOption] = useState<any>({
  //   isShow: false,
  //   value: monthList?.[0],
  // });
  const [comicFinishedParams, setComicFinishedParams] = useState<any>({
    mhstatus: COMIC_LIST_MHSTATUS.END,
    type: COMIC_LIST_TYPE.LASTEST,
    page: 1,
    limit: 18,
  });
  const { data: comicListsByFinished } = useComicLists(comicFinishedParams);
  const [isOpenCategoryDropdown, setIsOpenCategoryDropdown] = useState(false);
  const [isOpenTagDropdown, setIsOpenTagDropdown] = useState(false);
  const [reorderedCategories, setReorderedCategories] = useState<any[]>(categories);
  const [reorderedTags, setReorderedTags] = useState<any[]>(tags);

  // Refs for mobile scroll containers
  const mobileCategoryScrollRef = useRef<HTMLDivElement>(null);
  const mobileTagScrollRef = useRef<HTMLDivElement>(null);

  // Finished - TabsOnChange
  // const handleClickTabOnChange = (value: any) => {
  //   const currenTab = tabsList?.find((item) => {
  //     return item.id?.toString() === value;
  //   });

  //   setCurrentActiveTab(currenTab);

  //   setSearchParams((prev: any) => {
  //     return {
  //       ...prev,
  //       type: currenTab?.type,
  //     };
  //   });
  // };

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

    setCurrentCategory((prev: any) => ({
      ...prev,
      category: currentCategoryValue,
    }));

    setSearchParams((prev: any) => {
      return {
        ...prev,
        page: 1,
        type: currentActiveTab?.type,
        tag: currentCategory?.tag?.id,
        ticai_id: currentCategoryValue?.id,
      };
    });

    // Close mobile dropdown after selection
    setIsOpenCategoryDropdown(false);
  };

  // 标签 - OnChange
  const handleClickTagOnChange = (value: any) => {
    const currentTag = [allCategory, ...reorderedTags]?.find((item) => {
      return item.id === value?.id;
    });

    // Swap tag if it's in the dropdown (index >= 5)
    const selectedIndex = reorderedTags.findIndex(
      (tag) => tag.id === value?.id
    );
    if (selectedIndex >= 5) {
      const newOrder = [...reorderedTags];
      // Remove the selected tag from its current position
      const [selectedTag] = newOrder.splice(selectedIndex, 1);
      // Insert it at the last visible position (index 4, which is the 5th tag)
      newOrder.splice(4, 0, selectedTag);
      setReorderedTags(newOrder);
    }

    setCurrentCategory((prev: any) => ({
      ...prev,
      tag: currentTag,
    }));
    setSearchParams((prev: any) => {
      return {
        ...prev,
        page: 1,
        type: currentActiveTab?.type,
        tag: currentTag?.id,
        ticai_id: currentCategory?.category?.id,
      };
    });

    // Close mobile dropdown after selection
    setIsOpenTagDropdown(false);
  };

  // 分页 - OnChange
  const paginationHandler = (clickedActive: any) => {
    const num = parseInt(clickedActive);
    setActive(num);

    const params: any = {
      page: num.toString(),
      type: currentActiveTab?.type,
    };

    if (searchParams.get("type")) {
      params.type = searchParams.get("type");
    }

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

  // Sync reordered tags when tags from context change
  useEffect(() => {
    const tag = searchParams.get("tag") || "";

    // Check if selected tag is in dropdown (index >= 5)
    if (tag) {
      const selectedIndex = tags.findIndex(
        (t) => t.id?.toString() === tag?.toString()
      );

      if (selectedIndex >= 5) {
        // Tag is in dropdown, move it to last visible position
        const newOrder = [...tags];
        const [selectedTag] = newOrder.splice(selectedIndex, 1);
        newOrder.splice(4, 0, selectedTag);
        setReorderedTags(newOrder);
      } else {
        // Tag is already visible or not found, use original order
        setReorderedTags(tags);
      }
    } else {
      // No tag selected, use original order
      setReorderedTags(tags);
    }
  }, [tags, searchParams]);

  useEffect(() => {
    const page = parseInt(searchParams.get("page") || "1");
    const type = searchParams.get("type") || "";
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

    // 类型
    if (type) {
      const currentTab = tabsList?.find((item) => {
        return item.type === type;
      });
      setCurrentActiveTab(currentTab);
    }
    // 分页
    if (page) {
      setActive(page);
    }

    // 标签
    if (tag) {
      const currentTag = [allCategory, ...reorderedTags]?.find((item) => {
        return item.id?.toString() === tag?.toString();
      });

      setCurrentCategory((prev: any) => ({
        ...prev,
        tag: currentTag,
      }));
    }

    const formattedParams = pickBy(
      {
        tag: tag || "",
        ticai_id: ticai_id || "",
        type: type || "",
        page: page,
        limit: 18,
      },
      (value) => !isNil(value) && value !== ""
    );
    setComicFinishedParams(formattedParams);
  }, [searchParams, reorderedCategories, reorderedTags, allCategory]);

  // Auto-scroll to selected category when category param changes
  useEffect(() => {
    const ticai_id = searchParams.get("ticai_id");

    if (mobileCategoryScrollRef.current) {
      const categoryId = parseInt(ticai_id || "0");

      if (categoryId === 0) {
        // Scroll to the leftmost position when "All" is selected
        mobileCategoryScrollRef.current.scrollTo({
          left: 0,
          behavior: 'smooth'
        });
      } else {
        // Scroll to the selected category
        const selectedElement = mobileCategoryScrollRef.current.querySelector(
          `#mobile-category-${ticai_id}`
        ) as HTMLElement;

        if (selectedElement) {
          selectedElement.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center'
          });
        }
      }
    }
  }, [searchParams, reorderedCategories]);

  // Auto-scroll to selected tag when tag param changes
  useEffect(() => {
    const tag = searchParams.get("tag");

    if (mobileTagScrollRef.current) {
      const tagId = parseInt(tag || "0");

      if (tagId === 0) {
        // Scroll to the leftmost position when "All" is selected
        mobileTagScrollRef.current.scrollTo({
          left: 0,
          behavior: 'smooth'
        });
      } else {
        // Scroll to the selected tag
        const selectedElement = mobileTagScrollRef.current.querySelector(
          `#mobile-tag-${tag}`
        ) as HTMLElement;

        if (selectedElement) {
          selectedElement.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center'
          });
        }
      }
    }
  }, [searchParams, reorderedTags]);

  // more comic dropdown
  const categoryDropdownRef = useRef<HTMLDivElement>(null);
  const mobileCategoryDropdownRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      const isClickOutside =
        (!categoryDropdownRef.current || !categoryDropdownRef.current.contains(event.target as Node)) &&
        (!mobileCategoryDropdownRef.current || !mobileCategoryDropdownRef.current.contains(event.target as Node));

      if (isClickOutside) {
        setIsOpenCategoryDropdown(false);
      }
    }

    if (isOpenCategoryDropdown) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpenCategoryDropdown]);

  // more tag dropdown
  const tagDropdownRef = useRef<HTMLDivElement>(null);
  const mobileTagDropdownRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      const isClickOutside =
        (!tagDropdownRef.current || !tagDropdownRef.current.contains(event.target as Node)) &&
        (!mobileTagDropdownRef.current || !mobileTagDropdownRef.current.contains(event.target as Node));

      if (isClickOutside) {
        setIsOpenTagDropdown(false);
      }
    }

    if (isOpenTagDropdown) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpenTagDropdown]);

  return (
    <div className="w-full h-full relative max-w-screen-xl mt-[49px] mx-auto lg:mt-4">
      {/* category and tag */}
      {currentActiveTab?.showTag && (
        <div>
          {/* mobile filter category */}
          <div className="relative w-full px-4">
            <p className="text-sm font-semibold text-greyscale-900  lg:text-base">
              {t("search.category")}
            </p>
            <div className="w-full mt-2 lg:hidden">
              <div className="-ml-2 flex items-center gap-2">
                <div className="cursor-pointer" onClick={() => {
                  setIsOpenCategoryDropdown(true)
                  setIsOpenTagDropdown(false)
                }}>
                  <img
                    className="w-6 h-6 min-w-6 min-h-6"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-bars-arrow-down-black.svg`}
                    alt="arrow-down"
                  />
                </div>
                <div ref={mobileCategoryScrollRef} className="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                  {[allCategory, ...reorderedCategories].map((category) => (
                    <p
                      id={`mobile-category-${category?.id}`}
                      key={category?.id}
                      className={`text-sm rounded-lg px-2 py-1 min-w-max leading-4 ${parseInt(searchParams.get("ticai_id") || "0") === category?.id
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
              {isOpenCategoryDropdown && (
                <div className="absolute top-5 left-0 w-full h-screen bg-black/70 z-1">
                  <div ref={mobileCategoryDropdownRef}>
                    <div className="-ml-2 flex items-center gap-2 bg-white pt-2 px-4 cursor-pointer">
                      <img
                        className="w-6 h-6"
                        src={`${import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-bars-arrow-up-pink.svg`}
                        alt="arrow-up"
                      />
                      <p className="text-sm font-semibold">{t("common.pleaseSelectCategory")}</p>
                    </div>
                    <div className="flex items-center gap-2 flex-wrap bg-white py-4 px-4 max-h-[500px] overflow-y-auto scrollbar-hide">
                      {[allCategory, ...reorderedCategories].map((category) => (
                        <p
                          key={category?.id}
                          className={`text-sm rounded-lg px-2 py-1 min-w-max leading-4 ${parseInt(searchParams.get("ticai_id") || "0") === category?.id
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
          </div>

          {/* desktop filter category */}
          <div ref={categoryDropdownRef} className="hidden relative w-max max-w-full lg:block pt-4 px-4">
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
                        setIsOpenCategoryDropdown(false);
                        handleClickCategoryOnChange(category)
                      }}
                    >
                      {category.name}
                    </li>
                  ))}
                </ul>

                {reorderedCategories.length > 5 && (<button
                  type="button"
                  className={`rounded-lg px-3 py-2 min-w-max leading-6 flex items-center gap-1 cursor-pointer ${isOpenCategoryDropdown || [...reorderedCategories].slice(5).some(item => item.id === parseInt(searchParams.get("ticai_id") || "0"))
                    ? "bg-primary-100 text-primary font-semibold"
                    : "bg-greyscale-200 text-greyscale-900 hover:bg-primary-100 hover:text-primary"}
              `}
                  onClick={() => setIsOpenCategoryDropdown(!isOpenCategoryDropdown)}
                >
                  <span>{t("common.filterMore")}</span>
                  <img
                    className="w-6 h-6"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/${isOpenCategoryDropdown
                        ? 'icon-cheveron-up-pink.svg'
                        : [...reorderedCategories].slice(5).some(item => item.id === parseInt(searchParams.get("ticai_id") || "0"))
                          ? 'icon-cheveron-down-pink.svg'
                          : 'icon-cheveron-down-black.svg'
                      }`}
                    alt="more category"
                  />
                </button>)}
              </div>
            </div>

            {isOpenCategoryDropdown && (
              <ul className="absolute top-12 right-0 bg-white p-4 rounded-lg shadow-sm z-[9] w-[290px]">
                {[...reorderedCategories].slice(5).map((category) => (
                  <li
                    key={category?.id}
                    className={`px-3 py-2 cursor-pointer rounded-lg ${parseInt(searchParams.get("ticai_id") || "0") === category?.id
                      ? "bg-primary-100 text-primary font-semibold"
                      : "hover:bg-primary-100 hover:text-primary"
                      }`}
                    onClick={() => {
                      setIsOpenCategoryDropdown(false);
                      handleClickCategoryOnChange(category)
                    }}
                  >
                    {category.name}
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* mobile filter tag */}
          <div className="relative w-full px-4 mt-4">
            <p className="text-sm font-semibold text-greyscale-900  lg:text-base">
              {t("search.tag")}
            </p>
            <div className="w-full mt-2 lg:hidden">
              <div className="-ml-2 flex items-center gap-2">
                <div className="cursor-pointer" onClick={() => {
                  setIsOpenTagDropdown(true)
                  setIsOpenCategoryDropdown(false)
                }}>
                  <img
                    className="w-6 h-6 min-w-6 min-h-6"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-bars-arrow-down-black.svg`}
                    alt="arrow-down"
                  />
                </div>
                <div ref={mobileTagScrollRef} className="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                  {[allCategory, ...reorderedTags].map((tag) => (
                    <p
                      id={`mobile-tag-${tag?.id}`}
                      key={tag?.id}
                      className={`text-sm rounded-lg px-2 py-1 min-w-max leading-4 ${parseInt(searchParams.get("tag") || "0") === tag?.id
                        ? "bg-primary-100 text-primary font-semibold"
                        : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                        }`}
                      onClick={() => handleClickTagOnChange(tag)}
                    >
                      {tag.name}
                    </p>
                  ))}
                </div>
              </div>
              {isOpenTagDropdown && (
                <div className="absolute top-5 left-0 w-full h-screen bg-black/70 z-1">
                  <div ref={mobileTagDropdownRef}>
                    <div className="-ml-2 flex items-center gap-2 bg-white pt-2 px-4 cursor-pointer">
                      <img
                        className="w-6 h-6"
                        src={`${import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-bars-arrow-up-pink.svg`}
                        alt="arrow-up"
                      />
                      <p className="text-sm font-semibold">{t("common.pleaseSelectTag")}</p>
                    </div>
                    <div className="flex items-center gap-2 flex-wrap bg-white py-4 px-4 max-h-[500px] overflow-y-auto scrollbar-hide">
                      {[allCategory, ...reorderedTags].map((tag) => (
                        <p
                          key={tag?.id}
                          className={`text-sm rounded-lg px-2 py-1 min-w-max leading-4 ${parseInt(searchParams.get("tag") || "0") === tag?.id
                            ? "bg-primary-100 text-primary font-semibold"
                            : "bg-greyscale-200 text-greyscale-900 cursor-pointer"
                            }`}
                          onClick={() => handleClickTagOnChange(tag)}
                        >
                          {tag.name}
                        </p>
                      ))}
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* desktop filter tag */}
          <div ref={tagDropdownRef} className="hidden relative w-max max-w-full lg:block pt-4 px-4">
            <div className="overflow-x-auto scrollbar-hide">
              <div className="flex items-center gap-4">
                <ul className="flex items-center gap-4">
                  {[allCategory, ...reorderedTags].slice(0, 6).map((tag) => (
                    <li
                      key={tag?.id}
                      className={`rounded-lg px-3 py-2 min-w-max leading-6 cursor-pointer ${parseInt(searchParams.get("tag") || "0") === tag?.id
                        ? "bg-primary-100 text-primary font-semibold"
                        : "bg-greyscale-200 text-greyscale-900 hover:bg-primary-100 hover:text-primary"
                        }`}
                      onClick={() => {
                        setIsOpenTagDropdown(false);
                        handleClickTagOnChange(tag)
                      }}
                    >
                      {tag.name}
                    </li>
                  ))}
                </ul>

                {reorderedTags.length > 5 && (<button
                  type="button"
                  className={`rounded-lg px-3 py-2 min-w-max leading-6 flex items-center gap-1 cursor-pointer ${isOpenTagDropdown || [...reorderedTags].slice(5).some(item => item.id === parseInt(searchParams.get("tag") || "0"))
                    ? "bg-primary-100 text-primary font-semibold"
                    : "bg-greyscale-200 text-greyscale-900 hover:bg-primary-100 hover:text-primary"}
              `}
                  onClick={() => setIsOpenTagDropdown(!isOpenTagDropdown)}
                >
                  <span>{t("common.filterMore")}</span>
                  <img
                    className="w-6 h-6"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/${isOpenTagDropdown
                        ? 'icon-cheveron-up-pink.svg'
                        : [...reorderedTags].slice(5).some(item => item.id === parseInt(searchParams.get("tag") || "0"))
                          ? 'icon-cheveron-down-pink.svg'
                          : 'icon-cheveron-down-black.svg'
                      }`}
                    alt="more tag"
                  />
                </button>)}
              </div>
            </div>

            {isOpenTagDropdown && (
              <ul className="absolute top-12 right-0 bg-white p-4 rounded-lg shadow-sm z-[9] w-[290px]">
                {[...reorderedTags].slice(5).map((tag) => (
                  <li
                    key={tag?.id}
                    className={`px-3 py-2 cursor-pointer rounded-lg ${parseInt(searchParams.get("tag") || "0") === tag?.id
                      ? "bg-primary-100 text-primary font-semibold"
                      : "hover:bg-primary-100 hover:text-primary"
                      }`}
                    onClick={() => {
                      setIsOpenTagDropdown(false);
                      handleClickTagOnChange(tag)
                    }}
                  >
                    {tag.name}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}

      {/* 漫画列表 */}
      <div className="mt-4 px-4 w-full">
        {comicListsByFinished?.data?.length &&
          comicListsByFinished?.data?.length > 0 ? (
          <div className="grid grid-cols-3 gap-y-1.5 gap-x-1.5 lg:grid-cols-5 lg:gap-x-6 lg:gap-y-6 xl:grid-cols-6">
            {comicListsByFinished?.data?.map((item: any) => (
              <Post key={item.id} item={item} fixedHeight={true} />
            ))}
          </div>
        ) : (
          <Empty />
        )}

        {comicListsByFinished?.last_page &&
          comicListsByFinished?.last_page > 1 ? (
          <div className="py-1">
            <Pagination
              active={active}
              size={comicListsByFinished?.last_page || 1}
              step={1}
              total={comicListsByFinished?.total || 0}
              onClickHandler={paginationHandler}
            />
          </div>
        ) : null}

        <div className="mb-10">
          <RandomPostSection />
        </div>
      </div>
    </div>
  );
};

export default Finished;
