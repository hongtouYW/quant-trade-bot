import React from "react";
import type { LucideIcon } from "lucide-react";
// import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface EmptyStateProps {
  /** Icon to display (from Lucide React) */
  icon?: LucideIcon;
  /** Main title text */
  title: string;
  /** Optional description text */
  description?: string;
  /** Optional action button */
  action?: {
    label: string;
    onClick: () => void;
    variant?:
      | "default"
      | "outline"
      | "secondary"
      | "ghost"
      | "link"
      | "destructive";
  };
  /** Custom icon element if not using Lucide */
  customIcon?: React.ReactNode;
  /** Additional CSS classes */
  className?: string;
  /** Size variant */
  size?: "sm" | "md" | "lg";
  /** Optional section header */
  sectionHeader?: {
    icon?: React.ReactNode;
    title: string;
    action?: React.ReactNode;
  };
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  icon: Icon,
  title,
  description,
  // action,
  customIcon,
  className,
  size = "md",
  sectionHeader,
}) => {
  const sizeClasses = {
    sm: {
      container: "py-8 px-4",
      icon: "size-12",
      title: "text-lg",
      description: "text-sm",
    },
    md: {
      container: "py-4 px-6",
      icon: "size-16",
      title: "text-xl",
      description: "text-base",
    },
    lg: {
      container: "py-16 px-8",
      icon: "size-20",
      title: "text-2xl",
      description: "text-lg",
    },
  };

  const currentSize = sizeClasses[size];

  return (
    <div className={className}>
      {/* Optional Section Header */}
      {sectionHeader && (
        <div className="flex justify-between items-center mb-4">
          <div className="flex items-center space-x-2">
            {sectionHeader.icon && sectionHeader.icon}
            <span className="font-bold">{sectionHeader.title}</span>
          </div>
          {sectionHeader.action && sectionHeader.action}
        </div>
      )}

      {/* Empty State Content */}
      <div
        className={cn(
          "flex flex-col items-center justify-center text-center",
          currentSize.container,
        )}
      >
        {/* Icon */}
        <div
          className={cn(
            "flex items-center justify-center rounded-full bg-muted mb-4",
            currentSize.icon,
          )}
        >
          {customIcon ? (
            customIcon
          ) : Icon ? (
            <Icon
              className={cn(
                "text-muted-foreground",
                size === "sm" ? "size-6" : size === "md" ? "size-8" : "size-10",
              )}
            />
          ) : null}
        </div>

        {/* Title */}
        <h3
          className={cn(
            "font-semibold text-foreground mb-2",
            currentSize.title,
          )}
        >
          {title}
        </h3>

        {/* Description */}
        {description && (
          <p
            className={cn(
              "text-muted-foreground mb-6 max-w-md",
              currentSize.description,
            )}
          >
            {description}
          </p>
        )}

        {/*/!* Action Button *!/*/}
        {/*{action && (*/}
        {/*  <Button*/}
        {/*    variant={action.variant || "outline"}*/}
        {/*    onClick={action.onClick}*/}
        {/*    size={size === 'sm' ? 'sm' : 'default'}*/}
        {/*  >*/}
        {/*    {action.label}*/}
        {/*  </Button>*/}
        {/*)}*/}
      </div>
    </div>
  );
};
