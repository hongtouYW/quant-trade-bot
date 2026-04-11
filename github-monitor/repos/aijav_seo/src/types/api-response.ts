export interface ApiResponse<T> {
  code: number;
  msg: string;
  timestamp: number;
  data: T;
}

export interface PaginatedData<T> {
  data: T[];
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface PaginationParams {
  page?: number;
  limit?: number;
}

export interface SubscribePayload {
  token?: string;
}
