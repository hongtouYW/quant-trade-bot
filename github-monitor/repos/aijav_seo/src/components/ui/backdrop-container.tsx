import type { ReactNode } from "react";
import seriesBackdrop from "@/assets/series-backdrop.webp";

interface BackdropContainerProps {
  children: ReactNode;
  className?: string;
}

export function BackdropContainer({
  children,
  className = "relative flex flex-col items-center p-4 h-full"
}: BackdropContainerProps) {
  return (
    <div className={className}>
      {/* Background image */}
      <div
        className="absolute inset-0 bg-cover bg-center"
        style={{
          backgroundImage: `url(${seriesBackdrop})`,
        }}
        aria-hidden="true"
      />
      {/* Overlay for opacity */}
      <div
        className="absolute inset-0 bg-white/60"
        aria-hidden="true"
      />
      {/* Content */}
      <div className="relative flex flex-col items-center">
        {children}
      </div>
    </div>
  );
}
