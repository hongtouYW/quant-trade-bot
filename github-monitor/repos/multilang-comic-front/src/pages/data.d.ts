export interface ComicContentType {
  comic: ComicInfoType;
  chapters: {
    data: ChapterInfoDataType[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
export interface ComicInfoType {
  age: number;
  auther: string;
  cover: string;
  cover_horizontal: string;
  desc: string;
  id: number;
  image: string;
  issole: number;
  last_chapter_title: string;
  mark: number;
  mhstatus: number;
  subscribe: number;
  tags: Array<ChapterInfoDataType>;
  ticai_id: number;
  ticai_name: string;
  title: string;
  update_time: number;
  view: number;
  vipcanread: number;
  xianmian: number;
  manhua_actors: Array<any>;
  manhua_highlights: Array<any>;
  keyword: string;
}

export interface ChapterInfoType {
  current_page: number;
  data: Array;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ChapterInfoDataType {
  id: number;
  image: string;
  isvip: number;
  score: number;
  title: string;
  update_time: string;
}
