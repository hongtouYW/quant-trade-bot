import React from "react";
import { cn } from "@/lib/utils";

interface FilmstripBackgroundProps {
  bgColor?: string;
  orientation?: "horizontal" | "vertical";
  className?: string;
  children: React.ReactNode;
}

export const FilmstripBackground: React.FC<FilmstripBackgroundProps> = ({
  bgColor = "#F8E6FB",
  orientation = "horizontal",
  className,
  children,
}) => {
  const isHorizontal = orientation === "horizontal";

  // SVG pattern IDs to avoid conflicts
  const patternId = `filmstrip-${orientation}-${Math.random().toString(36).substr(2, 9)}`;

  return (
    <div
      className={cn("relative rounded-2xl overflow-hidden p-8", className)}
      style={{ backgroundColor: bgColor }}
    >
      <svg
        className="absolute inset-0 w-full h-full pointer-events-none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <pattern
            id={patternId}
            patternUnits="userSpaceOnUse"
            width={isHorizontal ? "20" : "12"}
            height={isHorizontal ? "12" : "20"}
          >
            <line
              x1={isHorizontal ? "0" : "6"}
              y1={isHorizontal ? "6" : "0"}
              x2={isHorizontal ? "12" : "6"}
              y2={isHorizontal ? "6" : "12"}
              stroke="#EBC8F0"
              strokeWidth="12"
              opacity="0.8"
            />
          </pattern>
        </defs>
        {isHorizontal ? (
          <>
            <rect
              x="0"
              y="8"
              width="100%"
              height="12"
              fill={`url(#${patternId})`}
            />
            <rect
              x="0"
              y="calc(100% - 20px)"
              width="100%"
              height="12"
              fill={`url(#${patternId})`}
            />
          </>
        ) : (
          <>
            <rect
              x="8"
              y="0"
              width="12"
              height="100%"
              fill={`url(#${patternId})`}
            />
            <rect
              x="calc(100% - 20px)"
              y="0"
              width="12"
              height="100%"
              fill={`url(#${patternId})`}
            />
          </>
        )}
      </svg>
      {children}
    </div>
  );
};
