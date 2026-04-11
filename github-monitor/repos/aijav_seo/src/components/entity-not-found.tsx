import { Button } from "@/components/ui/button.tsx";
import { AlertCircle, Home } from "lucide-react";
import { Link } from "react-router";
import { useTranslation } from "react-i18next";

interface EntityNotFoundProps {
  entityType: "actress" | "publisher";
  message?: string;
  errorCode?: number;
}

export const EntityNotFound = ({
  entityType,
  message,
  errorCode,
}: EntityNotFoundProps) => {
  const { t } = useTranslation();

  const getDefaultMessage = () => {
    return entityType === "actress"
      ? t("actress.not_found")
      : t("publisher.not_found");
  };

  // const getEntityDisplayName = () => {
  //   return entityType === "actress"
  //     ? t("common.actress")
  //     : t("common.publisher");
  // };

  const getDescription = () => {
    return entityType === "actress"
      ? t("actress.not_found_description")
      : t("publisher.not_found_description");
  };

  // const getSearchPath = () => {
  //   return entityType === "actress"
  //     ? "/search?type=actress"
  //     : "/search?type=publisher";
  // };

  return (
    <div className="flex flex-col items-center justify-center min-h-[400px] text-center space-y-6 p-8 text-foreground">
      <div className="flex items-center justify-center w-20 h-20 bg-red-100 dark:bg-red-500/20 rounded-full">
        <AlertCircle className="w-10 h-10 text-red-500 dark:text-red-400" />
      </div>

      <div className="space-y-2">
        <h2 className="text-2xl font-semibold text-foreground">
          {message || getDefaultMessage()}
        </h2>
        {errorCode && (
          <p className="text-sm text-muted-foreground">
            {t("common.error_code")}: {errorCode}
          </p>
        )}
        <p className="text-muted-foreground max-w-md">{getDescription()}</p>
      </div>

      <div className="flex flex-col sm:flex-row gap-4">
        <Button asChild className="bg-[#EC67FF] hover:bg-[#EC67FF]/90">
          <Link to="/">
            <Home className="w-4 h-4 mr-2" />
            {t("common.go_home")}
          </Link>
        </Button>
      </div>
    </div>
  );
};
