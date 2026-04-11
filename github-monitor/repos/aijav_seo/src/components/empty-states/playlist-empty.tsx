import { ListVideo } from 'lucide-react';
import { ListEmpty } from './list-empty';

interface PlaylistEmptyProps {
  title?: string;
  description?: string;
  onRefresh?: () => void;
  size?: 'sm' | 'md' | 'lg';
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
}

export const PlaylistEmpty: React.FC<PlaylistEmptyProps> = (props) => {
  return (
    <ListEmpty
      icon={ListVideo}
      defaultTitle="empty.no_playlists"
      defaultDescription="empty.no_playlists_desc"
      {...props}
    />
  );
};