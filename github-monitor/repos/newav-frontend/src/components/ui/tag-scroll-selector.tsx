import { useRef } from "react";
import { useNavigate } from "react-router";
import { useTagList } from "@/hooks/tag/useTagList";

interface TagScrollSelectorProps {
  currentTagId?: string | null;
}

export function TagScrollSelector({ currentTagId }: TagScrollSelectorProps) {
  const navigate = useNavigate();
  const scrollContainerRef = useRef<HTMLDivElement>(null);

  // Fetch tags from cache (page 1 with default limit)
  const { data: tagsData } = useTagList({ page: 1 });

  // Get random 5 tags from the cached data, excluding the current tag
  const getRandomTags = () => {
    if (!tagsData?.data) return [];

    const allTags = tagsData.data;
    // Filter out the current tag
    const filteredTags = allTags.filter(
      (tag) => String(tag.id) !== currentTagId,
    );

    if (filteredTags.length <= 5) return filteredTags;

    // Get 5 random tags from filtered list
    const shuffled = [...filteredTags].sort(() => Math.random() - 0.5);
    return shuffled.slice(0, 5);
  };

  const randomTags = getRandomTags();

  const handleTagClick = (tagId: number, tagName: string) => {
    // Navigate to the same page but with different tag params
    navigate(`/video/list?tag=${tagId}&tagName=${tagName}&page=1`);
  };

  // Don't render if no tags or current tag
  if (!currentTagId || randomTags.length === 0) {
    return null;
  }

  return (
    <div
      ref={scrollContainerRef}
      className="flex gap-2.5 overflow-x-auto scroll-smooth flex-1 hide-scrollbar"
    >
      {randomTags.map((tag) => (
        <button
          key={tag.id}
          onClick={() => handleTagClick(tag.id, tag.name)}
          className="flex-shrink-0 flex items-center gap-2.5 py-1.5 px-3.5 bg-gray-100 text-gray-600 rounded-full hover:bg-gray-200 transition-colors"
        >
          <span className="text-xs font-medium">
            #{tag.name}
          </span>
        </button>
      ))}
    </div>
  );
}
