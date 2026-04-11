export interface Category {
  id: number;
  name: string;
  name_en: string;
  name_ru: string;
  tags: CategoryTag[];
}

export interface CategoryTag {
  id: number;
  name: string;
  image: string;
  video_count: number;
}
