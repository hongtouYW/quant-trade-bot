export type TransactionType = "recharge" | "series" | "video";

export type TransactionStatus = "completed" | "pending" | "failed";

export type PaymentMethod = "VISA" | "MASTER" | "PAYPAL" | "ALIPAY" | "WECHAT";

export interface BaseTransaction {
  id: string;
  time: string;
  orderNumber: string;
  amount: number;
  status: TransactionStatus;
  paymentMethod: PaymentMethod;
  type: TransactionType;
}

export interface RechargeTransaction extends BaseTransaction {
  type: "recharge";
  package: string; // 套餐 (package name like "年卡", "季卡")
}

export interface SeriesTransaction extends BaseTransaction {
  type: "series";
  seriesName: string; // 系列名称
  seriesId: string;
}

export interface VideoTransaction extends BaseTransaction {
  type: "video";
  videoTitle: string; // 视频标题
  videoId: string;
}

export type Transaction =
  | RechargeTransaction
  | SeriesTransaction
  | VideoTransaction;

export interface TabConfig {
  key: TransactionType;
  label: string;
  secondColumnLabel: string;
}

// API Response Interfaces
export interface VipOrder {
  title: string;
  money: number;
  product_id: number;
  product_type: number;
  diamond: number;
  point: number;
  day: number;
  order_sn: string;
  add_time: string;
  status: number;
}

export interface SeriesPurchase {
  id: number;
  title: string;
  description: string;
  amount: number;
  image: string;
  is_purchase: number;
  is_collect: number;
  total_video: number;
  videos: unknown[];
}

export interface VideoPurchase {
  video_point: string;
  id: number;
  title: string;
  mash: string;
  duration: string;
  thumb: string;
  preview: string;
  private: number;
  play: number;
  publisher: {
    id: number;
    name: string;
  };
  publish_date: string;
}
