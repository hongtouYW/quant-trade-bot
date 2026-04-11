import { Search } from "lucide-react";
import { EmptyState } from "@/components/ui/empty-state";
import { useTranslation } from "react-i18next";

interface SearchResultsEmptyProps {
  keyword: string;
  className?: string;
}

export const SearchResultsEmpty = ({ keyword, className }: SearchResultsEmptyProps) => {
  const { t } = useTranslation();

  return (
    <EmptyState
      icon={Search}
      title={t("search_result.no_results_title", { keyword }) || `No results found for "${keyword}"`}
      description={t("search_result.no_results_description") || "Try adjusting your search terms or check for typos."}
      size="md"
      className={className}
    />
  );
};