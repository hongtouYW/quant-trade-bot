import { useState } from "react";

const Select = ({
  options,
  //   value,
  onChange,
}: {
  options: any[];
  value: string;
  onChange: (value: string) => void;
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [selected, setSelected] = useState(options[0]);

  const handleSelect = (item: any) => {
    setSelected(item);
    onChange(item);
    setIsOpen(false);
  };

  return (
    <div className="bg-greyscale-300 relative">
      <div
        className="flex items-center gap-1 cursor-pointer p-2"
        onClick={() => setIsOpen(!isOpen)}
      >
        <img className="w-6" src={selected?.flag} alt="select" />
        <img
          className={`w-2 ${isOpen ? "rotate-180" : ""}`}
          src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-cheveron-down.svg`}
          alt="select"
        />
      </div>
      {isOpen && (
        <div className="absolute top-[34px] left-0 w-max bg-white shadow-md overflow-auto min-h-[200px] h-[200px]">
          {options.map((option) => (
            <div
              key={option.value}
              onClick={() => handleSelect(option)}
              className="flex items-center gap-1 p-2 cursor-pointer"
            >
              {option?.flag && (
                <img className="w-6" src={option.flag} alt="select" />
              )}
              <p className="text-xs"> {option?.name}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default Select;
