import type { Video } from "@/types/video.types.ts";

export interface Tags {
  id: number;
  name: string;
  video_list: Video[];
}

// New interfaces for /tag/lists endpoint
export interface TagListItem {
  id: number;
  name: string;
  image: string;
  video_count: number;
}

export interface TagListRequest {
  keyword?: number;
  limit?: number;
  top?: 0 | 1;
  page: number;
}
