import { ChevronDown } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useLocation, useNavigate } from "react-router";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils.ts";
import { useLanguage } from "@/contexts/LanguageContext";

export default function LanguageSwitcher({
  showLabel = false,
  className,
}: {
  showLabel?: boolean;
  className?: string;
}) {
  const { t } = useTranslation();
  const { currentLanguage, changeLanguage, supportedLanguages } = useLanguage();
  const location = useLocation();
  const navigate = useNavigate();
  
  const currentLangInfo = supportedLanguages.find(lang => lang.code === currentLanguage) || supportedLanguages[1]; // Default to Chinese (index 1)

  const handleSelectLanguage = (nextLang: string) => {
    changeLanguage(nextLang);

    const pathname = location.pathname;
    if (pathname.startsWith("/en/") || pathname.startsWith("/zh/")) {
      const nextPath = pathname.replace(/^\/(en|zh)\//, `/${nextLang}/`);
      if (nextPath !== pathname) {
        navigate(nextPath, { replace: true, state: location.state });
      }
      return;
    }

    if (pathname.startsWith("/watch/")) {
      const nextPath = pathname.replace(/^\/watch\//, `/${nextLang}/watch/`);
      navigate(nextPath, { replace: true, state: location.state });
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          size="sm"
          variant="ghost"
          className={cn(className, "p-0 hover:bg-transparent")}
        >
          <img
            src={currentLangInfo.icon}
            className="size-6 flex items-center justify-center rounded-full"
            alt={currentLangInfo.name}
          />
          {showLabel && <p>{currentLangInfo.name}</p>}
          {!showLabel && (
            <ChevronDown size={14} className="text-muted-foreground/80 max-md:hidden" />
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="w-56" align="end">
        <DropdownMenuLabel className="font-semibold ">{t('sidebar.language')}</DropdownMenuLabel>
        <DropdownMenuSeparator className="mx-2" />
        {supportedLanguages.map((language) => (
          <DropdownMenuItem
            className={cn(
              currentLanguage === language.code
                ? "bg-primary focus:bg-primary"
                : null,
              "cursor-pointer",
            )}
            key={language.code}
            onSelect={() => handleSelectLanguage(language.code)}
          >
            <img loading="lazy" className="size-6" src={language.icon} alt={language.name} />{" "}
            {language.name}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
