import { useQueries } from "@tanstack/react-query";
import { getComicLists } from "../api/comic-api";

export function useComicListsByCategories(categories: any[], count: number = 3) {
  const firstThreeCategories = categories?.slice(0, count) ?? [];

  const results = useQueries({
    queries: firstThreeCategories.map((category) => ({
      queryKey: ["comicLists", category.id],
      queryFn: () =>
        getComicLists({
          ticai_id: category.id,
          page: 1,
          limit: 18,
        }),
      enabled: !!category?.id,
    })),
  });

  // Build object directly from results
  const data = firstThreeCategories.reduce((acc, category, index) => {
    acc[category.name] = results[index]?.data || [];
    return acc;
  }, {} as Record<string, any[]>);

  const isLoading = results.some((r) => r.isLoading);
  const isError = results.some((r) => r.isError);

  return { data, isLoading, isError };
}
