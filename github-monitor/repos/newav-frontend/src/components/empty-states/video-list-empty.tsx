import { Play } from 'lucide-react';
import { ListEmpty } from './list-empty';

interface VideoListEmptyProps {
  title?: string;
  description?: string;
  onRefresh?: () => void;
  size?: 'sm' | 'md' | 'lg';
  sectionTitle?: string;
  sectionIcon?: React.ReactNode;
  sectionAction?: React.ReactNode;
}

export const VideoListEmpty: React.FC<VideoListEmptyProps> = (props) => {
  return (
    <ListEmpty
      icon={Play}
      defaultTitle="empty.no_videos"
      defaultDescription="empty.no_videos_desc"
      {...props}
    />
  );
};