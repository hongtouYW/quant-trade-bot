import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";

type TabsItem = {
  label?: string;
  value: string;
  locale?: string;
};

interface ITabs {
  list: Array<TabsItem>;
  activeId?: number;
  callback?: (value: any) => void;
  className?: string;
  isFullWidth?: boolean;
  fontSize?: string;
}

const Tabs = ({
  list,
  callback,
  activeId,
  className,
  isFullWidth,
  fontSize,
}: ITabs) => {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState<any>(list?.[0]?.value);

  const handleClickTab = (value: any) => {
    setActiveTab(value);
    callback?.(value);
  };

  useEffect(() => {
    if (activeId) {
      setActiveTab(activeId);
    } else {
      setActiveTab(list?.[0]?.value);
    }
  }, [activeId, list]);

  return (
    <div className={className}>
      <ul
        className={`flex font-medium text-center xs:flex-nowrap xs:overflow-x-auto ${
          isFullWidth ? "w-full" : "flex-wrap"
        }`}
      >
        {list?.map((item) => {
          return (
            <li
              key={item.value}
              className={`xs:min-w-max flex items-center justify-center ${
                activeTab === item.value
                  ? "border-b-2 border-primary-dark"
                  : "border-b-2 border-transparent"
              } ${isFullWidth ? "w-full" : ""}`}
            >
              <p
                className={`block px-4 max-xs:px-2  ${
                  fontSize || "text-base max-xs:text-sm"
                } ${isFullWidth ? "py-3 max-xs:py-2" : "py-1 max-xs:py-1"}   ${
                  activeTab === item.value
                    ? "text-primary-dark"
                    : "text-greyscale-500"
                }`}
                onClick={() => handleClickTab(item.value)}
              >
                {item?.locale ? t(item?.locale) : item?.label}
              </p>
            </li>
          );
        })}
      </ul>
    </div>
  );
};

export default Tabs;
