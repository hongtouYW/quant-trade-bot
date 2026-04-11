import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { useLocation } from "react-router";

interface FloatButtonProps {
  onClick?: () => void;
}
const FloatButton = ({ onClick }: FloatButtonProps) => {
  const { t } = useTranslation();
  const location = useLocation();
  const [isScroll, setIsScroll] = useState(0);

  const handleBackToTop = () => {
    if (onClick) {
      onClick();
    }
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  };

  const onScroll = () => {
    setIsScroll(window.pageYOffset);
  };

  useEffect(() => {
    window.addEventListener("scroll", onScroll);
    return () => {
      window.removeEventListener("scroll", onScroll);
    };
  }, []);

  if (
    location.pathname?.includes("/chapter/")
  ) {
    return null;
  }

  return (
    isScroll > 500 && (
      <div
        className="w-[40px] h-[40px] fixed bg-primary-dark rounded-full flex items-center justify-center cursor-pointer flex-col z-10 bottom-3 right-3 gap-1 sm:w-[60px] sm:h-[60px] 2xl:w-[80px] 2xl:h-[80px] 2xl:gap-2"
        onClick={handleBackToTop}
      >
        <img
          src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/back-top.svg`}
          alt="top"
          className="w-2 h-2 sm:w-3 sm:h-3 md:w-4 md:h-4"
        />
        <p className="text-white text-center text-[10px] sm:text-xs md:text-base">
          {t("common.backToTop")}
        </p>
      </div>
    )
  );
};

export default FloatButton;
