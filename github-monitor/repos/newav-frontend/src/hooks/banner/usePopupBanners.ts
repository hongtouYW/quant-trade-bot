import { useQuery } from "@tanstack/react-query";
import { fetchBannerList } from "@/services/banner.service.ts";
import type { Banner } from "@/types/banner.ts";

export const usePopupBanners = () =>
  useQuery({
    queryKey: ["popup-banners"],
    queryFn: ({ signal }) => fetchBannerList(signal),
    select: (response) => {
      if (!response.data) {
        return { ...response, data: [] };
      }

      return {
        ...response,
        data: response.data.filter((banner) => banner.type === 4) as Banner[],
      };
    },
  });
