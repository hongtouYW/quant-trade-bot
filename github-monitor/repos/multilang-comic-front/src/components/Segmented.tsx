import { useState } from "react";

interface ISegmented {
  list: any;
  callback?: (value: any) => void;
}
export const Segmented = ({ list, callback }: ISegmented) => {
  const [activeSegment, setActiveSegment] = useState<any>(list?.[0]?.value);

  const handleClickSegment = (value: any) => {
    setActiveSegment(value);

    callback?.(value);
  };

  return (
    <div className="flex items-center justify-start gap-1 bg-[#FDF1E1] p-1 rounded-lg font-medium">
      {list.map((segment: any) => (
        <button
          key={segment.id}
          onClick={() => handleClickSegment(segment.value)}
          type="button"
          aria-disabled="false"
          className={`w-full h-full py-[6px] ${
            activeSegment === segment.value
              ? "bg-[#F3BA68] text-white drop-shadow rounded-md disabled:cursor-not-allowed stroke-blue-700 disabled:stroke-slate-400 disabled:text-slate-400 hover:stroke-blue-950"
              : "bg-transparent text-[#C29553]"
          }`}
        >
          <div>{segment.label}</div>
        </button>
      ))}
    </div>
  );
};
