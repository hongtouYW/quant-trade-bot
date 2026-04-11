import { useTranslation } from "react-i18next";

const NotFound = () => {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col items-center justify-center h-screen text-center">
      <h1 className="text-6xl font-bold text-red-500 mb-4">{t("notFound.title") || "404"}</h1>
      <p className="text-xl mb-6">{t("notFound.description") || "您访问的页面不存在。"}</p>
      <a href="/" className="text-blue-500 hover:text-blue-700 underline">
        {t("notFound.button") || "返回首页"}
      </a>
    </div>
  );
};

export default NotFound;
