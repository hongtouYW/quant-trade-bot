export interface Actor {
  id: number;
  name: string;
  image: string;
}

export interface ActorList extends Actor {
  video_count: number;
  subscribe_count: number;
  is_subscribe: number;
}

export interface Photo {
  id: number;
  image: string;
  sort: number;
}

export interface ActorInfo {
  id: number;
  name: string;
  image: string;
  tid: number;
  video_count: number;
  birthday: string;
  debut: string;
  bust: number;
  waist: number;
  hip: number;
  bra_size: string;
  height: number;
  nationality: Nationality;
  constellation: Constellation;
  blood_type: BloodType;
  subscribe_count: number;
  is_subscribe: number;
  background_image: string;
  photos?: Photo[];
  twitter?: string | null;
  instagram?: string | null;
  tiktok?: string | null;
  fantia?: string | null;
  patreon?: string | null;
  onlyfans?: string | null;
  facebook?: string | null;
  youtube?: string | null;
  telegram?: string | null;
}

export interface MyActorResponse {
  id: number;
  name: string;
  image: string;
  video_count: number;
  add_time: number;
}

export interface Nationality {
  id: number;
  name: string;
  name_en: string;
  name_ru: string;
}

export interface Constellation {
  id: number;
  name: string;
  name_en: string;
  name_ru: string;
}

export interface BloodType {
  id: number;
  name: string;
  name_en: string;
  name_ru: string;
}

export interface ActorSubscribePayload extends SubscribePayload {
  aid: string;
}

export interface SubscribePayload {
  token?: string;
}

export interface Subscribe {
  id: number;
  name: string;
  image: string;
  video_count: number;
  add_time: number;
}

export interface ActorByPublisherPayload extends SubscribePayload {
  pid: number;
}

export interface ActorRanking extends ActorList {
  ranking_score?: number;
}
