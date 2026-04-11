import { ErrorState } from '@/components/ui/error-state';
import { useTranslation } from 'react-i18next';

interface InlineErrorProps {
  message?: string;
  onRetry?: () => void;
  variant?: 'default' | 'destructive' | 'warning';
  centered?: boolean;
  className?: string;
}

export const InlineError: React.FC<InlineErrorProps> = ({
  message,
  onRetry,
  variant = 'default',
  centered = false,
  className
}) => {
  const { t } = useTranslation();

  const defaultMessage = message || t('common.error_occurred');

  return (
    <ErrorState
      title={defaultMessage}
      onRetry={onRetry}
      variant={variant}
      size="sm"
      centered={centered}
      className={className}
    />
  );
};