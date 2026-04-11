import { ErrorState } from '@/components/ui/error-state';
import { useTranslation } from 'react-i18next';

interface ListErrorProps {
  title?: string;
  description?: string;
  onRetry?: () => void;
  size?: 'sm' | 'md' | 'lg';
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
  className?: string;
}

export const ListError: React.FC<ListErrorProps> = ({
  title,
  description,
  onRetry,
  size = 'sm',
  sectionTitle,
  sectionIcon,
  sectionAction,
  className
}) => {
  const { t } = useTranslation();

  const defaultTitle = title || t('common.error_loading');
  const defaultDescription = description || t('common.error_loading_desc');

  return (
    <ErrorState
      title={defaultTitle}
      description={defaultDescription}
      onRetry={onRetry}
      size={size}
      sectionHeader={sectionTitle ? {
        icon: sectionIcon,
        title: sectionTitle,
        action: sectionAction
      } : undefined}
      className={className}
    />
  );
};