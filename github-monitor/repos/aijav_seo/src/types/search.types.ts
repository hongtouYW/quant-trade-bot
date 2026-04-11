export interface SearchResultData {
  actor: Actor;
  publisher: Publisher;
  video: Video;
  video_groups: VideoGroups;
  total: number;
}

export interface Actor {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  data: ActorResult[];
}

export interface Publisher {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  data: ActorResult[];
}

export interface ActorResult {
  id: number;
  name: string;
  image: string;
  is_subscribe: number;
}

export interface PublisherResult {
  id: number;
  name: string;
  image?: string;
  video_count: number;
  video_count1: number;
  video_count2: number;
  is_subscribe: number;
}

export interface MyPublisherResponse {
  id: number;
  name: string;
  image: string;
  video_count: number;
  video_count1: number;
  video_count2: number;
  subscribe_time: number;
}

export interface Video {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  data: object[];
}

export interface VideoGroups {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  data: object[];
}

export interface SubscribeResponse {
  isSubscribed: boolean;
  msg: string; // "Publisher unsubscribed" or "Publisher subscribed"
}
