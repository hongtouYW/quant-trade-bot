export interface User {
  id: number;
  coin: number;
  point: number;
  username: string;
  ori_password: string;
  code: string;
  reg_time: number;
  token: string;
  token_val: number;
  vip_end_time: number;
  signature: string;
  is_vip: number;
}

export interface LoginUserPayload {
  username: string;
  password: string;
  remember?: boolean;
}

export interface RegisterUserPayload {
  username: string;
  password: string;
  repassword: string;
}

export interface RegisterUserResponse {
  code: number;
  msg: string;
  data: User;
}

export interface QuickRegisterResponse {
  code: number;
  msg: string;
  timestamp: number;
  data: {
    user: User;
    username: string;
    password: string;
  };
}

export interface RedeemCodePayload {
  code: string;
}

export interface RedeemCodeResponse {
  redeemcode: boolean;
  msg: string;
}

export interface RedeemRecord {
  id: number;
  status: number;
  uid: number;
  batch: string;
  day: number;
  create_time: number;
  exchange_time: number;
  day_value: number;
  diamonds: number;
  points: number;
}
