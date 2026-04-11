import { useTranslation } from "react-i18next";

export const EndOfContent = () => {
  const { t } = useTranslation();

  return (
    <div className="flex items-center gap-4 my-8 px-4">
      <div className="flex-1 h-px bg-border" />
      <span className="text-sm text-muted-foreground whitespace-nowrap">
        {t("common.end_of_content")}
      </span>
      <div className="flex-1 h-px bg-border" />
    </div>
  );
};
