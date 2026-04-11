import { Building2 } from 'lucide-react';
import { ListEmpty } from './list-empty';

interface PublisherListEmptyProps {
  title?: string;
  description?: string;
  onRefresh?: () => void;
  size?: 'sm' | 'md' | 'lg';
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
}

export const PublisherListEmpty: React.FC<PublisherListEmptyProps> = (props) => {
  return (
    <ListEmpty
      icon={Building2}
      defaultTitle="empty.no_publishers"
      defaultDescription="empty.no_publishers_desc"
      {...props}
    />
  );
};