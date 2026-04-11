import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router";
// import { useNavigate } from "react-router";
interface IButtonGroups {
  list: any[];
  callback: (val: any) => void;
  className?: string;
  addonAfter?: any;
  addonBefore?: any;
  active?: any;
}

const ButtonGroups = ({
  list,
  callback,
  className,
  addonAfter,
  addonBefore,
  active,
}: IButtonGroups) => {
  const navigate = useNavigate();
  const [currentActive, setCurrentActive] = useState<any>({});

  const buttonList = useMemo(() => {
    const newList = [...list];
    if (addonBefore) {
      newList.unshift(addonBefore);
    }

    if (addonAfter) {
      newList.push(addonAfter);
    }

    return newList;
  }, [addonBefore, addonAfter, list]);

  const handleClickResource = (value: any) => {
    const find = buttonList?.find((item) => {
      return item.id === value;
    });
    if (value === addonAfter?.id && addonAfter?.to) {
      navigate(addonAfter.to);
    }
    // const jump_name = find?.jump_name;

    setCurrentActive(find);
    callback(find);
    // navigate(`/resources/${jump_name}`);
  };

  useEffect(() => {
    if (buttonList?.[0]) {
      setCurrentActive(buttonList?.[0]);

      // const jump_name = list?.[0]?.jump_name;
      // navigate(`/resources/${jump_name}`);
    }
  }, [list]);

  useEffect(() => {
    if (active) {
      const currentActive = buttonList?.find((item) => {
        return item.id?.toString() === active?.toString();
      });
      setCurrentActive(currentActive);
    }
  }, [active]);

  //   useEffect(() => {
  //  console.log("currentActive", currentActive);
  //   }, [currentActive]);

  return (
    <div
      className={`flex flex-wrap items-center gap-2 text-primary-200 font-medium mt-2 mb-3 ${className} max-sm:flex-nowrap max-sm:overflow-x-auto max-xs:overflow-x-auto`}
    >
      {buttonList?.map((item) => (
        <p
          key={item.id}
          className={`rounded-full px-4 py-1 max-sm:min-w-max max-xs:min-w-max max-xs:text-sm flex items-center ${
            currentActive?.id === item.id
              ? "bg-primary-200 text-white border-2 border-primary-300"
              : "border-[1.5px] border-primary-200"
          }`}
          onClick={() => handleClickResource(item.id)}
        >
          {item.name}
          {item.id === 1000 && (
            <img
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-cheveron-right-light.svg`}
              alt="right"
              className="max-sm:w-4 max-sm:h-4"
            />
          )}
        </p>
      ))}
    </div>
  );
};

export default ButtonGroups;
