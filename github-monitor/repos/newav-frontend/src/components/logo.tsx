import appLogo from "../assets/logo.svg";
import appLogoWhite from "../assets/logo-white.svg";
import { cn } from "@/lib/utils.ts";
import { useTheme } from "@/components/theme-provider";
import { useEffect, useState } from "react";

interface LogoProps {
  className?: string;
}

export default function Logo({ className }: LogoProps) {
  const { theme } = useTheme();
  const [isDarkMode, setIsDarkMode] = useState(false);

  useEffect(() => {
    const updateTheme = () => {
      if (theme === "system") {
        const systemTheme = window.matchMedia("(prefers-color-scheme: dark)").matches;
        setIsDarkMode(systemTheme);
      } else {
        setIsDarkMode(theme === "dark");
      }
    };

    updateTheme();

    // Listen for system theme changes when theme is "system"
    if (theme === "system") {
      const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
      const handleChange = () => updateTheme();
      mediaQuery.addEventListener("change", handleChange);
      return () => mediaQuery.removeEventListener("change", handleChange);
    }
  }, [theme]);

  return (
    <img 
      loading="lazy" 
      className={cn(className)} 
      src={isDarkMode ? appLogoWhite : appLogo} 
      alt="Logo" 
    />
  );
}
