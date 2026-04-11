import type { ReactNode } from "react";
import type { LucideIcon } from "lucide-react";

interface EmptyStateCardProps {
  icon: LucideIcon;
  iconSize?: string;
  iconColor?: string;
  title: string;
  subtitle?: string;
  actions?: ReactNode;
  iconContainerSize?: string;
  iconContainerClass?: string;
  iconFill?: boolean;
}

export function EmptyStateCard({
  icon: Icon,
  iconSize = "size-50",
  iconColor = "text-white",
  title,
  subtitle,
  actions,
  iconContainerSize = "w-[320px] h-[320px]",
  iconContainerClass = "bg-[#E126FC] rounded-[10px] border flex items-center justify-center",
  iconFill = false,
}: EmptyStateCardProps) {
  return (
    <>
      <div className={`${iconContainerSize} ${iconContainerClass}`}>
        <Icon
          className={`${iconSize} ${iconColor}`}
          {...(iconFill && { fill: "currentColor" })}
        />
      </div>
      <div className="mt-2.5 text-xl font-semibold tracking-tight text-gray-900 w-full">
        {title}
      </div>
      {subtitle && (
        <div className="text-sm w-full text-gray-600 mb-4">{subtitle}</div>
      )}
      {actions && <div className="w-full mt-4">{actions}</div>}
    </>
  );
}
