export type BannersType = {
  id: number;
  image: string;
  mid: number;
  link: string;
  position: number;
  title: string;
};

export type TagsType = {
  id: number;
  name: string;
};

export type CategoriesType = {
  id: number;
  name: string;
  is_top: number;
};

export type ComicDetailType = {
  age18: number;
  auther: string;
  cover: string;
  cover_horizontal: string;
  desc: string;
  id: number;
  image: string;
  isjingpin: number;
  issole: number;
  keyword: string;
  last_chapter_title: string;
  manhua_highlights: any[];
  manhua_actors: any[];
  mark: number;
  max_score: number;
  mhstatus: number;
  new_release: number;
  subscribe: number;
  ticai_id: number;
  ticai_name: string;
  title: string;
  update_time: number;
  view: number;
  vipcanread: number;
  xianmian: number;
};

export type ComicRankListsType = {
  bestSeller: any[];
  popular: any[];
  finished: any[];
  subscribe: any[];
};

export interface Pagination<T> {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  data: T
}

export type ComicHomepageRankingType = {
  hot: Pagination<ComicDetailType[]>,
  popular: Pagination<ComicDetailType[]>,
  finished: Pagination<ComicDetailType[]>,
  subscribe: Pagination<ComicDetailType[]>,
}

export type ComicHomepageType = {
  total?: number;
  per_page?: number;
  current_page?: number;
  last_page?: number;
  data: ComicDetailType[];
}

export type ComicHomepageListsType = {
  id: number;
  module: string;
  name: string;
  is_highlight: 0 | 1;
  params: {
    [key: string]: string;
  };
  data: ComicHomepageType | ComicHomepageRankingType
}

export type ComicHomepageLists = Pagination<ComicHomepageListsType[]>;
