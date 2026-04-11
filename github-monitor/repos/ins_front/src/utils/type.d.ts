export type VideoListType = {
  current_page: number;
  data: Array<VideoType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type VideoType = {
  id: number;
  title: string;
  description?: string;
  actor?: ActorType;
  play?: string;
  collect_count?: string;
  thumb?: string;
  preview?: string;
  subtitle?: number;
  private?: number;
  is_collect?: number;
  is_subscribe?: number;
  mash?: string;
  panorama?: string;
  publisher?: PublisherType;
  publish_date?: string;
  tags?: Array<TagType>;
  zimu?: string;
  url?: string;
};

export type AuthorListType = {
  current_page: number;
  data: Array<AuthorType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type AuthorType = {
  id: number;
  name?: string;
  image?: string;
};

export type PublisherListType = {
  current_page: number;
  data: Array<PublisherType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type PublisherType = {
  id: number;
  name?: string;
};

export type TagListType = {
  current_page: number;
  data: Array<TagType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type TagType = {
  id: number;
  image?: string;
  name?: string;
};

export type ActorListType = {
  current_page: number;
  data: Array<ActorType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type ActorType = {
  id: number;
  image?: string;
  name?: string;
};

export type ActorInfoType = {
  id: number;
  image?: string;
  name?: string;
  tid?: number;
  video_count?: number;
  is_subscribe?: number;
};

export type BannerType = {
  id: number;
  aid?: number;
  vid?: number;
  thumb?: string;
  title?: string;
  url?: string;
};

export type LinkType = {
  id: number;
  title?: string;
  image?: string;
  url?: string;
};

export type VipPackageType = {
  id: number;
  cost?: number;
  day?: number;
  title?: string;
  des?: string;
  is_sale?: number;
  money?: number;
};

export type SubscribeListType = {
  current_page: number;
  data: Array<SubscribeType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type SubscribeType = {
  id: number;
  add_time?: string;
  image?: string;
  name?: string;
  video_count?: number;
};

export type CollectListType = {
  current_page: number;
  data: Array<VideoType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type OrderType = {
  title?: string;
  add_time?: string;
  money?: number;
  order_sn?: string;
  status?: number;
};

export type AsType = {
  title?: string;
  thumb?: string;
  url?: string;
};

export type ReviewListType = {
  current_page: number;
  data: Array<ReviewType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type ReviewType = {
  id: number;
  vid?: number;
  aid?: number;
  actor?: string;
  title?: string;
  content?: string;
  mash?: string;
  thumb?: string;
};

export type ReviewInfoType = {
  id: number;
  aid?: number;
  vid?: number;
  actor?: string;
  title?: string;
  content?: string;
  mash?: string;
  thumb?: string;
};

export type NoticeType = {
  title?: string;
  content?: string;
};

export type ActorTrendListType = {
  current_page: number;
  data: Array<ActorTrendType>;
  last_page: number;
  per_page: number;
  total: number;
};

export type ActorTrendType = {
  text?: string;
  create_at?: string;
  media: Array<string>;
};

export type PlatformType = {
  id: number;
  name?: string;
  type?: number;
};
