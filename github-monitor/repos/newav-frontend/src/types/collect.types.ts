export interface CollectVideoResponse {
  collect: boolean;
  msg: string;
}

export interface CollectVideoPayload {
  vid: number;
  token?: string;
}
