import { useTranslation } from "react-i18next";

const Empty = () => {
  const { t } = useTranslation();
  return (
    <div className="w-full text-greyscale-600 h-[200px] flex items-center justify-center">
      <p className="text-center">{t("common.noData")}</p>
    </div>
  );
};

export default Empty;
