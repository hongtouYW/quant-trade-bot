import { AlertCircle, RefreshCw } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

interface ErrorStateAction {
  label: string;
  onClick: () => void;
  variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  icon?: LucideIcon;
}

interface ErrorStateSectionHeader {
  icon?: React.ReactNode;
  title: string;
  action?: React.ReactNode;
}

interface ErrorStateProps {
  // Core content
  title?: string;
  description?: string;
  icon?: LucideIcon;
  
  // Layout options
  size?: 'sm' | 'md' | 'lg';
  variant?: 'default' | 'destructive' | 'warning';
  centered?: boolean;
  
  // Section context (for list errors)
  sectionHeader?: ErrorStateSectionHeader;
  
  // Actions
  onRetry?: () => void;
  action?: ErrorStateAction;
  actions?: ErrorStateAction[];
  
  // Styling
  className?: string;
  children?: React.ReactNode;
}

export const ErrorState: React.FC<ErrorStateProps> = ({
  title,
  description,
  icon: Icon = AlertCircle,
  size = 'md',
  variant = 'default',
  centered = true,
  sectionHeader,
  onRetry,
  action,
  actions,
  className,
  children
}) => {
  const { t } = useTranslation();

  // Size configurations
  const sizeConfig = {
    sm: {
      container: 'py-4',
      icon: 'size-8',
      title: 'text-base font-semibold',
      description: 'text-sm',
      spacing: 'space-y-2'
    },
    md: {
      container: 'py-8',
      icon: 'size-12',
      title: 'text-lg font-semibold',
      description: 'text-sm',
      spacing: 'space-y-3'
    },
    lg: {
      container: 'py-12',
      icon: 'size-16',
      title: 'text-xl font-bold',
      description: 'text-base',
      spacing: 'space-y-4'
    }
  };

  // Variant configurations
  const variantConfig = {
    default: {
      icon: "text-red-500 dark:text-red-400",
      title: "text-gray-900 dark:text-gray-100",
      description: "text-gray-600 dark:text-gray-300",
    },
    destructive: {
      icon: "text-red-600 dark:text-red-500",
      title: "text-red-900 dark:text-red-200",
      description: "text-red-700 dark:text-red-300",
    },
    warning: {
      icon: "text-yellow-500 dark:text-yellow-400",
      title: "text-yellow-900 dark:text-yellow-100",
      description: "text-yellow-700 dark:text-yellow-200",
    },
  };

  const config = sizeConfig[size];
  const colors = variantConfig[variant];

  // Prepare actions
  const allActions = [];
  
  if (onRetry) {
    allActions.push({
      label: t('common.refresh'),
      onClick: onRetry,
      variant: 'outline' as const,
      icon: RefreshCw
    });
  }
  
  if (action) {
    allActions.push(action);
  }
  
  if (actions) {
    allActions.push(...actions);
  }

  return (
    <div className={cn("w-full", className)}>
      {/* Section Header */}
      {sectionHeader && (
        <div className="flex justify-between items-center mb-4">
          <div className="flex items-center space-x-2">
            {sectionHeader.icon}
            <span className="font-bold">{sectionHeader.title}</span>
          </div>
          {sectionHeader.action}
        </div>
      )}

      {/* Error Content */}
      <div className={cn(
        config.container,
        centered ? 'text-center' : 'text-left',
        'w-full'
      )}>
        <div className={cn(
          config.spacing,
          centered ? 'flex flex-col items-center' : ''
        )}>
          {/* Error Icon */}
          <Icon className={cn(config.icon, colors.icon)} />

          {/* Error Text */}
          <div className={cn(config.spacing, centered ? 'text-center' : '')}>
            {title && (
              <div className={cn(config.title, colors.title)}>
                {title}
              </div>
            )}
            
            {description && (
              <p className={cn(config.description, colors.description)}>
                {description}
              </p>
            )}
          </div>

          {/* Custom Content */}
          {children}

          {/* Actions */}
          {allActions.length > 0 && (
            <div className={cn(
              "flex gap-2",
              centered ? "justify-center" : "justify-start",
              allActions.length > 1 ? "flex-wrap" : ""
            )}>
              {allActions.map((actionItem, index) => {
                const ActionIcon = actionItem.icon;
                return (
                  <Button
                    key={index}
                    onClick={actionItem.onClick}
                    variant={actionItem.variant || 'outline'}
                    className="flex items-center gap-2"
                  >
                    {ActionIcon && <ActionIcon className="size-4" />}
                    {actionItem.label}
                  </Button>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
