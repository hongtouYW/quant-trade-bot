import type { ReactNode } from "react";
import seriesBackdrop from "@/assets/series-backdrop.webp";
import { Base64Image } from "@/components/Base64Image.tsx";

interface SeriesImageCardProps {
  imageSrc?: string;
  imageAlt?: string;
  title: string;
  subtitle?: string;
  titleActions?: ReactNode;
  actions?: ReactNode;
}

export function SeriesImageCard({
  imageSrc,
  imageAlt = "Series",
  title,
  subtitle,
  titleActions,
  actions,
}: SeriesImageCardProps) {
  return (
    <>
      <Base64Image
        originalUrl={imageSrc || seriesBackdrop}
        alt={imageAlt}
        className="w-[320px] h-[320px] object-cover rounded-[10px] border"
      />
      <div className="mt-4 w-full">
        <div className="flex items-start justify-between gap-2">
          <div className="flex-1 min-w-0">
            <div className="text-xl font-semibold tracking-tight text-gray-900">
              {title}
            </div>
            {subtitle && (
              <div className="text-sm text-gray-600 mt-1">{subtitle}</div>
            )}
          </div>
          {titleActions && (
            <div className="flex items-center gap-2 flex-shrink-0">
              {titleActions}
            </div>
          )}
        </div>
      </div>
      {actions && <div className="w-full mt-6">{actions}</div>}
    </>
  );
}
