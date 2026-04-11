import axios, {
  AxiosError,
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from "axios";
import Cookies from "universal-cookie";
import { base64Sign, decrypt, encrypt, systemLanguage } from "../utils/utils";
import { API_ENDPOINTS } from "./api-endpoint";
import { COOKIE_NAME } from "../utils/constant";
import { queryClient } from "../main";
import { toast } from "react-toastify";
import i18n from "../utils/i18n";
import { modalEvents, MODAL_EVENTS } from "../utils/modalEvents";
const cookies = new Cookies();
// const APP_ENV = import.meta.env.VITE_ENV;

//接口地址
// const baseURL = "/api";
const baseURL = import.meta.env.VITE_DEFAULT_BASE_URL;
const request: AxiosInstance = axios.create({
  baseURL,
  //超时时间
  timeout: 10e4,
});

// 请求拦截器
request.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // 拦截请求配置，进行个性化处理。
    const tokenName = localStorage.getItem("token_name") || "";
    const token = cookies.get(tokenName);

    if (token) {
      config.headers[tokenName] = token;
    }
    let payload = {} as any;
    const data = config?.data;
    const lang = systemLanguage();

    // payload["system"] = 1;
    payload["lang"] = lang;
    payload["timestamp"] = new Date().getTime();
    payload = { ...payload, ...data };

    payload["encode_sign"] = base64Sign(payload);
    payload = { "post-data": encrypt(JSON.stringify(payload)) };

    config.data = payload;

    // 使用本地js缓存文件
    let _domain = import.meta.env.VITE_STATIC_JSON_HOST;

    if (localStorage.getItem("_BASE")) {
      _domain = JSON.parse(localStorage.getItem("_BASE") ?? "{}")?.config
        ?.json_host;
    }

    if (config.url?.includes(API_ENDPOINTS.config)) {
      config.url = _domain + `/data/config/lists.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.bannerLists)) {
      config.url = _domain + `/data/banner/lists-${data.position}-${lang}.js`;
      // console.log("config.url-banner", config.url);
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicIndexLists)) {
      config.url =
        _domain +
        `/data/comic/indexLists-${data.type}-${data.page || "1"}-${
          data.limit || "10"
        }-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicTicai)) {
      config.url = _domain + `/data/comic/ticai-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicTags)) {
      config.url = _domain + `/data/comic/tags-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.notice)) {
      config.url = _domain + `/data/index/notice-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicLists)) {
      config.url =
        _domain +
        `/data/comic/lists-${data.type || "1"}-${data.ticai_id || "0"}-${
          data.tag || "0"
        }-${data.mhstatus || "2"}-${data.xianmian || "2"}-${data.year || "0"}-${
          data.month || "0"
        }-${data.weekday || "0"}-${data.page || "1"}-${
          data.limit || "18"
        }-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicRanks)) {
      config.url =
        _domain +
        `/data/comic/allRank-${data.range || "all"}-${data.page || "1"}-${
          data.limit || "18"
        }-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicRank)) {
      config.url =
        _domain +
        `/data/comic/rank-${data.date || "0"}-${data.type || "1"}-${
          data.page || "1"
        }-${data.limit || "6"}-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicGuess)) {
      config.url =
        _domain +
        `/data/comic/guess-${data.mid || "0"}-${data.limit || "6"}-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicInfo)) {
      // console.log('data', data);
      config.url =
        _domain +
        `/data/comic/info-${data.mid || "0"}-${data?.sort || "asc"}-${data.page || "1"}-${
          data.limit || "10"
        }-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicChapterInfo)) {
      config.url =
        _domain + `/data/comic/chapterInfo-${data.cid || "0"}-${lang}.js`;
      config.method = "get";
    }

    // if (config.url?.includes(API_ENDPOINTS.comicSearch)) {
    //   config.url = _domain + `/data/comic/search-${data.keyword || ""}-${data.ticai_id || "0"}-${data.tag || "0"}-${data.limit || "18"}-${lang}.js`;
    //   config.method = "get";
    // }

    if (config.url?.includes(API_ENDPOINTS.rechargeLists)) {
      config.url = _domain + `/data/recharge/lists-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.rechargePlatforms)) {
      config.url = _domain + `/data/recharge/platforms-${data.pro_id || "0"}-${lang}.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.adsLists)) {
      config.url = _domain + `/data/ads/lists.js`;
      config.method = "get";
    }

    if (config.url?.includes(API_ENDPOINTS.comicHomepage)) {
      config.url = _domain + `/data/comic/homepage-${data.page}-${data.limit}-${lang}.js`;
      config.method = "get";
    }

    if (
      config.baseURL &&
      (config.baseURL.indexOf("172.247.9.210:8900") >= 0 ||
        config.baseURL.indexOf(baseURL?.replace("https://", "")) >= 0)
    ) {
      config.headers["suffix"] = 123456;
      config.headers["Content-Type"] = "application/json";
    }
    // console.log("config", config);
    return config;
  },
  (error: AxiosError) => {
    //对请求错误做些什么
    // console.log(error, "request-error");
    return Promise.reject(error);
  }
);

// 响应拦截器
request.interceptors.response.use(
  (response: AxiosResponse) => {
    const { data } = response;

    if (data.code === 401) {
      return (window.location.href = "/login");
    }

    if (data.code !== 200) {
      //   message.error(data.message);
    }
    // console.log("response", response);
    let __data: any = decrypt(response.data.data, response.data.suffix);
    __data = JSON.parse(__data);
    response.data.data = __data;

    if (__data?.code === 2000) {
      cookies.remove(COOKIE_NAME, {
        path: "/",
      });
      queryClient.setQueryData(["userInfo"], null); // 🔥 clear user info
      const msg = i18n.t("common.pleaseLoginAgain", {
        defaultValue: "Please login again",
      });
      toast.error(msg);

      // Trigger login modal
      modalEvents.emit(MODAL_EVENTS.OPEN_LOGIN_MODAL);

      return;
    }
    return data;
  },
  (error: AxiosError) => {
    //对请求错误做些什么
    return Promise.reject(error);
  }
);

export const http = {
  post<T>(url: string, data?: object, config?: AxiosRequestConfig): Promise<T> {
    return request.post(url, data, config);
  },
};
