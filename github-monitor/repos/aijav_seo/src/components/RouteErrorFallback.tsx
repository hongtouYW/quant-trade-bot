import { useRouteError, isRouteErrorResponse, useNavigate } from "react-router";
import { useTranslation } from "react-i18next";

export function RouteErrorFallback() {
  const error = useRouteError();
  const navigate = useNavigate();
  const { t } = useTranslation();

  const is404 = isRouteErrorResponse(error) && error.status === 404;

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 px-4 text-center">
      <h1 className="text-4xl font-bold text-gray-200">
        {is404 ? "404" : t("common.error_occurred")}
      </h1>
      <p className="text-gray-400">
        {is404
          ? t("common.page_not_found", "Page not found")
          : t("common.something_went_wrong", "Something went wrong")}
      </p>
      <div className="flex gap-3">
        <button
          onClick={() => navigate("/")}
          className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
        >
          {t("common.go_home", "Go Home")}
        </button>
        <button
          onClick={() => window.location.reload()}
          className="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors"
        >
          {t("common.retry", "Retry")}
        </button>
      </div>
    </div>
  );
}
