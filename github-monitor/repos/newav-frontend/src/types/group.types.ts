import type { VideoInfo } from "@/types/video.types.ts";

export interface GroupDetailResponse {
  id: number;
  title: string;
  description: string;
  amount: number;
  image: string;
  is_purchase?: number;
  is_collect?: number;
  total_video: number;
  videos: VideoInfo[];
}

export interface CollectedGroup {
  id: number;
  title: string;
  title_en: string;
  title_ru: string;
  description: string;
  amount: number;
  image: string;
  total_video: number;
  videos: VideoInfo[];
}

export interface CollectionToggleRequest {
  gid: number;
}

export interface CollectionToggleResponse {
  isCollected: boolean;
  msg: string;
}

export interface GroupPurchaseRequest {
  gid: number;
}

export interface GroupPurchaseResponse {
  purchase: boolean;
  msg: string;
  currentCoin: number;
}
