import { Users } from 'lucide-react';
import { ListEmpty } from './list-empty';

interface ActorListEmptyProps {
  title?: string;
  description?: string;
  onRefresh?: () => void;
  size?: 'sm' | 'md' | 'lg';
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
}

export const ActorListEmpty: React.FC<ActorListEmptyProps> = (props) => {
  return (
    <ListEmpty
      icon={Users}
      defaultTitle="empty.no_actresses"
      defaultDescription="empty.no_actresses_desc"
      {...props}
    />
  );
};