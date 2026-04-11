import type { SubscribePayload } from "@/types/actor.types.ts";

export interface PublisherInfo {
  id: number;
  name: string;
  image: string;
  subscribe_count: number;
  total_video: number;
  is_subscribe: number;
}

export interface PublisherSubscribePayload extends SubscribePayload {
  pid: string;
}
