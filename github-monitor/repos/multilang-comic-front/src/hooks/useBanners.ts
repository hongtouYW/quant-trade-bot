import { useQuery } from "@tanstack/react-query";
import { getBannerLists } from "../api/comic-api";

const useBanners = (position: string) => {
  const { data } = useQuery({
    queryKey: ["banners", position],
    queryFn: () => getBannerLists(position),
  });
  return { data };
};

export default useBanners;
