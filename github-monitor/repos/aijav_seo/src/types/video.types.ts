import type { Actor } from "@/types/actor.types.ts";

export interface Video {
  actor: Actor[];
  publisher: Publisher;
  id: number;
  title: string;
  mash_title: string;
  mash: string;
  publish_date: string;
  duration: string;
  thumb: string;
  preview: string;
  private: number;
  play: number;
  play_day: number;
  play_week: number;
  play_month: number;
  subtitle: number;
  rating_avg: number;
  rating_count: number;
  collect_count: number;
  video_point: string;
}

export interface Publisher {
  id: number;
  name: string;
}

export interface SubtitleUrls {
  en: string;
  zh: string;
  ru: string;
  ms: string;
  th: string;
  es: string;
}

export interface VideoGroup {
  id: number;
  title: string;
}

export interface VideoInfo {
  id: number;
  title: string;
  mash: string;
  duration: string;
  tags: Tag[];
  actor?: Actor[];
  director: string | null;
  thumb: string;
  thumb_series?: string;
  preview: string;
  panorama: string;
  video_url?: string;
  description: string;
  private: number;
  play: number;
  mash_title?: string;
  is_new?: number;
  is_purchase?: number;
  is_collect?: number;
  is_subscribe?: number;
  position?: "right" | "left" | "centre";
  publisher: Publisher;
  publish_date: string;
  subtitle: number;
  zimu: SubtitleUrls;
  rating_avg: number;
  rating_count: number;
  collect_count: number;
  reviews: unknown[];
  video_point: string;
  video_group?: VideoGroup[] | null;
}

export interface Tag {
  id: number;
  name: string;
  image?: string;
}

export interface PlayLogVideo extends Video {
  video_point: string;
}

export interface PurchaseVideoPayload {
  vid: number;
}

export interface PurchaseVideoResponse {
  purchase: boolean;
  msg: string;
}

export interface HotlistItem {
  id: number;
  title: string;
  title_en: string;
  title_ru: string;
  sub_title: string;
  sub_title_en: string;
  sub_title_ru: string;
  image: string;
  total_video: number;
  videos: VideoInfo[];
}

export interface VideoAccessRequest {
  vid: number;
}

export interface VideoAccessResponse {
  access: boolean;
  msg: string;
}

export type VideoAccessErrorCode = 6007 | 5007 | 1;

export const VIDEO_ACCESS_ERROR_CODES = {
  6007: "purchase_required",
  5007: "series_purchase_required",
  1: "success",
} as const;
