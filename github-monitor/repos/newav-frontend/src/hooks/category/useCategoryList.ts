import { useQuery } from "@tanstack/react-query";
import { fetchCategoryList } from "@/services/category.service.ts";

export const useCategoryList = () =>
  useQuery({
    queryKey: ["categoryList"],
    queryFn: ({ signal }) => fetchCategoryList(signal),
    select: (data) => data.data,
    // staleTime: 1000 * 60 * 5,
  });
