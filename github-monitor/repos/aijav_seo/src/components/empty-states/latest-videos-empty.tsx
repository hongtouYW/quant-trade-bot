import { Calendar } from "lucide-react";
import { ListEmpty } from "./list-empty";

interface LatestVideosEmptyProps {
  selectedMonth: string;
  title?: string;
  description?: string;
  onRefresh?: () => void;
  size?: "sm" | "md" | "lg";
}

export const LatestVideosEmpty: React.FC<LatestVideosEmptyProps> = ({
  selectedMonth: _selectedMonth,
  title,
  description,
  onRefresh,
  size = "md",
}) => {
  return (
    <div className="py-8 w-full">
      <ListEmpty
        icon={Calendar}
        defaultTitle="latest.no_videos"
        defaultDescription="latest.no_videos"
        title={title}
        description={description}
        onRefresh={onRefresh}
        size={size}
        sectionTitle={undefined}
      />
    </div>
  );
};
