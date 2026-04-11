import { createContext, useContext } from "react";
import { BANNER_POSITION } from "../utils/constant";
import useBanners from "../hooks/useBanners";
import type { BannersType, CategoriesType, TagsType } from "../utils/type";
import useTags from "../hooks/useTags";
import useCategories from "../hooks/useCategories";
import useNotice from "../hooks/useNotice";

interface ReactNodeProps {
  children: React.ReactNode;
}

type ResourceContextType = {
  bannersHome: BannersType[];
  // bannersPromotion: BannersType[]; // 优惠活动 - 暂时隐藏
  bannersFree: BannersType[];
  banners456: BannersType[];
  tags: TagsType[];
  categories: CategoriesType[];
  notice: any[];
};
const ResourceContext = createContext<ResourceContextType | null>({
  bannersHome: [],
  // bannersPromotion: [],
  bannersFree: [],
  banners456: [],
  tags: [],
  categories: [],
  notice: [],
});

export const ResourceProvider: React.FC<ReactNodeProps> = ({ children }) => {
  const { data: bannersHome } = useBanners(BANNER_POSITION.HOME);
  // const { data: bannersPromotion } = useBanners(BANNER_POSITION.PROMOTION);
  const { data: bannersFree } = useBanners(BANNER_POSITION.FREE);
  const { data: banners456 } = useBanners("4,5,6");
  const { data: tags } = useTags();
  const { data: categories } = useCategories();
  const { data: notice } = useNotice();

  const value = {
    bannersHome: bannersHome || [],
    // bannersPromotion: bannersPromotion || [],
    bannersFree: bannersFree || [],
    banners456: banners456 || [],
    tags: tags || [],
    categories: categories || [],
    notice: notice || [],
  };
  return (
    <ResourceContext.Provider value={value}>
      {children}
    </ResourceContext.Provider>
  );
};

// eslint-disable-next-line react-refresh/only-export-components
export const useResourceContext = () => {
  return useContext(ResourceContext) as ResourceContextType;
};
