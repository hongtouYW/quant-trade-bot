export interface Banner {
  id: number;
  title: string;
  vid: number;
  aid: number;
  thumb: string;
  url: string;
  position: 1 | 2 | 3; // 1 = top, 2 = middle, 3 = bottom
  type: 1 | 2 | 3 | 4; // 1 = normal, 2 = actress, 3 = video(?), 4 = popup
  p_type: 1 | 2 | 3 | null; // 1 = vip, 2 = point(点卷), 3 = diamond(钻石)
  p_type_id: number | null; // resource id for p_type
}
