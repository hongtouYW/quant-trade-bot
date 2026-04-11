export interface GlobalImageThumb {
  en?: string;
  zh?: string;
  ru?: string;
  ms?: string;
  th?: string;
  es?: string;
}

export interface GlobalImage {
  id: number;
  key: string;
  thumb: GlobalImageThumb | Record<string, never>;
  m_thumb: GlobalImageThumb | Record<string, never>;
  status: number;
  sort_order: number;
  created_at: string;
  updated_at: string;
}

export type GlobalImageResponse = GlobalImage[];