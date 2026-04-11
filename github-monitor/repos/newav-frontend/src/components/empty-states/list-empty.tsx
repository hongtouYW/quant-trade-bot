import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/ui/empty-state';
import { useTranslation } from 'react-i18next';

interface ListEmptyProps {
  icon: LucideIcon;
  defaultTitle: string;
  defaultDescription: string;
  title?: string;
  description?: string;
  onRefresh?: () => void;
  size?: 'sm' | 'md' | 'lg';
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
}

export const ListEmpty: React.FC<ListEmptyProps> = ({
  icon: Icon,
  defaultTitle,
  defaultDescription,
  title,
  description,
  onRefresh,
  size = 'sm',
  sectionTitle,
  sectionIcon,
  sectionAction
}) => {
  const { t } = useTranslation();
  const finalTitle = title || t(defaultTitle);
  const finalDescription = description || t(defaultDescription);
  
  return (
    <EmptyState
      icon={Icon}
      title={finalTitle}
      description={finalDescription}
      size={size}
      sectionHeader={sectionTitle ? {
        icon: sectionIcon,
        title: sectionTitle,
        action: sectionAction
      } : undefined}
      action={onRefresh ? {
        label: t('common.refresh'),
        onClick: onRefresh,
        variant: "outline"
      } : undefined}
    />
  );
};
