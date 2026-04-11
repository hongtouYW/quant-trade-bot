export interface VipPlan {
  id: number;
  title: string;
  des: string;
  day: number;
  cost: number;
  money: number;
  is_sale: number;
  diamonds: number;
  points: number;
}

export interface CoinPlan {
  id: number;
  title: string;
  des: string | null;
  diamonds: number;
  points: number;
  cost: number;
  money: number;
  is_sale: number;
  hot: number;
  recommend: number;
}

export interface PointPlan {
  id: number;
  title: string;
  des: string | null;
  diamonds: number;
  points: number;
  cost: number;
  money: number;
  is_sale: number;
  hot: number;
  recommend: number;
}

export interface GlobalVipData {
  vip: VipPlan[];
  coin: CoinPlan[];
  point: PointPlan[];
}

export interface GlobalVipResponse {
  code: number;
  msg: string;
  timestamp: number;
  data: GlobalVipData;
}

export interface PaymentPlatform {
  id: number;
  name: string;
  type: number;
}

export interface PaymentPlatformsRequest {
  vid: number;
  p_type: number;
}

export interface PurchasePackageRequest {
  vid: number; // package id
  p_type: number; // 1=vip, 2=diamond, 3=point
  pid: number; // platform id
}

export interface PurchasePackageResponse {
  pay_url: string;
}
