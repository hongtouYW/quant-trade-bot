export interface APIResponseType<T = any> {
  data: {
    code: number;
    data: T;
    message: string;
    msg: string;
  };
  suffix: string;
  time: string;
}

export interface APIResponsePaginationType<T = any> {
  data: {
    code: number;
    data: {
      data: T;
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
    message: string;
    msg: string;
  };
  suffix: string;
  time: string;
}
