import { useEffect, useState } from "react";

interface InputProps {
  label?: string;
  name: string;
  placeholder?: string;
  type?: string;
  initialValue?: string;
  value?: string;
  className?: string;
  labelClassName?: string;
  icon?: React.ReactNode;
  addonAfterIcon?: React.ReactNode;
  addonBeforeIcon?: React.ReactNode;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onBlur?: (e: React.FocusEvent<HTMLInputElement>) => void;
  showCount?: boolean;
  maxLength?: number;
}

const Input = ({
  label,
  name,
  placeholder,
  type = "text",
  initialValue,
  value,
  className,
  labelClassName,
  icon,
  addonAfterIcon,
  addonBeforeIcon,
  onChange,
  onBlur,
  showCount,
  maxLength,
}: InputProps) => {
  const [inputValue, setInputValue] = useState(value || initialValue);

  useEffect(() => {
    setInputValue(initialValue);
  }, [initialValue]);

  return (
    <>
      {label && (
        <label
          className={`text-sm text-greyscale-900 block mb-1 ${labelClassName || ""} lg:text-base`}
        >
          {label}
        </label>
      )}
      <div
        className={`w-full flex items-center bg-white py-3 px-4 ${
          addonBeforeIcon ? "" : "px-1 py-1"
        }  border border-greyscale-300 rounded-xl text-sm leading-[25px] text-greyscale-900 ${
          icon ? "gap-2" : ""
        } ${className}`}
      >
        {addonBeforeIcon && (
          <div className="border border-[#E0E0E0] rounded-tl-lg rounded-bl-lg pr-1">
            {addonBeforeIcon}
          </div>
        )}
        <div>{icon}</div>
        <input
          type={type}
          name={name}
          value={value}
          defaultValue={initialValue}
          placeholder={placeholder}
          className="placeholder:text-greyscale-400 placeholder:text-sm focus:outline-none flex-1"
          onChange={(e) => {
            onChange?.(e);
            if (showCount) {
              setInputValue(e.target.value);
            }
          }}
          maxLength={maxLength}
          onBlur={onBlur}
        />
        <div className="pr-1">{addonAfterIcon}</div>
        {showCount && (
          <div className="text-sm text-greyscale-600">
            {inputValue?.length}/{maxLength}
          </div>
        )}
      </div>
    </>
  );
};

export default Input;
