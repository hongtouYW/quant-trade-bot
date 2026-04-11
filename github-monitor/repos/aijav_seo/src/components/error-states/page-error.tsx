import { ErrorState } from '@/components/ui/error-state';
import { Home, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

interface PageErrorProps {
  title?: string;
  description?: string;
  onRetry?: () => void;
  showHomeAction?: boolean;
  variant?: 'default' | 'destructive' | 'warning';
  className?: string;
}

export const PageError: React.FC<PageErrorProps> = ({
  title,
  description,
  onRetry,
  showHomeAction = true,
  variant = 'default',
  className
}) => {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const defaultTitle = title || t('common.page_error');
  const defaultDescription = description || t('common.page_error_desc');

  const actions = [];
  
  if (onRetry) {
    actions.push({
      label: t('common.try_again'),
      onClick: onRetry,
      variant: 'outline' as const,
      icon: RefreshCw
    });
  }

  if (showHomeAction) {
    actions.push({
      label: t('common.go_home'),
      onClick: () => navigate('/'),
      variant: 'default' as const,
      icon: Home
    });
  }

  return (
    <ErrorState
      title={defaultTitle}
      description={defaultDescription}
      variant={variant}
      size="lg"
      actions={actions}
      className={className}
    />
  );
};