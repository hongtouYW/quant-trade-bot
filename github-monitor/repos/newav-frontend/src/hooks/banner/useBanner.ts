import { useQuery } from "@tanstack/react-query";
import { fetchBannerList } from "@/services/banner.service.ts";

export const useBanner = (position?: 1 | 2 | 3) =>
  useQuery({
    queryKey: ["banner"],
    queryFn: ({ signal }) => fetchBannerList(signal),
    select: (response) => {
      if (!response.data) {
        return response;
      }

      let filtered = response.data.filter((banner) => banner.type !== 4);

      if (position) {
        filtered = filtered.filter((banner) => banner.position === position);
      }

      return {
        ...response,
        data: filtered,
      };
    },
  });
