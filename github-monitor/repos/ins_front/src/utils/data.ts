export const othersInsLink = [
  {
    text: "有码区",
    locale: "censored",
    theme: "green",
    name: "www",
  },
  {
    text: "无码区",
    locale: "uncensored",
    theme: "purple",
    name: "wuma",
  },
  {
    text: "动漫区",
    locale: "anime",
    theme: "yellow",
    name: "dm",
  },
  {
    text: "4k区",
    locale: "4k",
    theme: "blue",
    name: "4k",
  },
];

export const yellowProductInfoLink = [
  {
    text: "免费放送",
    locale: "freeVideo",
    path: "/video/list?type=3",
  },
  {
    text: "作者",
    locale: "author",
    path: "/author/index",
  },
  {
    text: "类型",
    locale: "type",
    path: "/tag/index",
  },
  {
    text: "热度",
    locale: "hot",
    path: "/video/list?list=hot&order=1",
  },
  {
    text: "新片",
    locale: "latestMovie",
    path: "/video/list?type=new",
  },
];
export const productInfoLink = [
  {
    text: "免费放送",
    locale: "freeVideo",
    path: "/video/list?type=3",
  },
  {
    text: "女优",
    locale: "pornstars",
    path: "/actor/index?order=1",
  },
  {
    text: "片商",
    locale: "film",
    path: "/publisher/index",
  },
  {
    text: "主题",
    locale: "theme",
    path: "/tag/index",
  },
  {
    text: "热度",
    locale: "hot",
    path: "/video/list?list=hot&order=1",
  },
  {
    text: "新片",
    locale: "latestMovie",
    path: "/video/list?type=new",
  },
  {
    text: "中字",
    locale: "cnSub",
    path: "/video/list?type=4",
  },
];

export const yellowProductInfoMobileLink = [
  {
    text: "首页",
    locale: "homePage",
    path: "/",
    active: [
      "/",
      "/video/list?type=1",
      "/video/list?type=2",
      "/video/list?type=3",
    ],
  },
  {
    text: "女优",
    locale: "author",
    path: "/author/index",
    active: ["/author/index"],
  },
  {
    text: "类型",
    locale: "type",
    path: "/tag/index",
    active: ["/tag/index"],
  },
  {
    text: "热度",
    locale: "hot",
    path: "/video/list?list=hot&order=1",
    active: ["/video/list?list=hot&order=1"],
  },
  {
    text: "新片",
    locale: "latestMovie",
    path: "/video/list?type=new",
    active: ["/video/list?type=new"],
  },
];

export const productInfoMobileLink = [
  {
    text: "首页",
    locale: "homePage",
    path: "/",
    active: [
      "/",
      "/video/list?type=1",
      "/video/list?type=2",
      "/video/list?type=3",
    ],
  },
  {
    text: "女优",
    locale: "pornstars",
    path: "/actor/index?order=1",
    active: ["/actor/index?order=1"],
  },
  {
    text: "片商",
    locale: "film",
    path: "/publisher/index",
    active: ["/publisher/index"],
  },
  {
    text: "主题",
    locale: "theme",
    path: "/tag/index",
    active: ["/tag/index"],
  },
  {
    text: "热度",
    locale: "hot",
    path: "/video/list?list=hot&order=1",
    active: ["/video/list?list=hot&order=1"],
  },
  {
    text: "新片",
    locale: "latestMovie",
    path: "/video/list?type=new",
    active: ["/video/list?type=new"],
  },
  {
    text: "中字",
    locale: "cnSub",
    path: "/video/list?type=4",
    active: ["/video/list?type=4"],
  },
  {
    text: "AV影评",
    locale: "movieReview",
    path: "/review/index",
    active: ["/review/index"],
    show: "green",
  },
];

import icon_file from "/icon_file-text.png";
import icon_duihuan from "/icon_duihuan.png";
import icon_dingyue from "/icon_dingyue-1.png";
import icon_soucang from "/icon_soucang.png";
import icon_edit from "/icon_edit.png";
import icon_cart from "/icon_cart.png";
import icon_notice from "/icon_notice.png";
import icon_client_service from "/icon_client_service.png";
import icon_logout from "/icon_logout.png";
import icon_order from "/icon_order.png";

export const userSideMenu = [
  {
    id: 1,
    path: "/user/center",
    text: "個人中心",
    locale: "account",
  },
  {
    id: 2,
    path: "/user/identify",
    text: "身份卡",
    locale: "identifyCard",
    img: icon_file,
  },
  {
    id: 3,
    path: "/user/redeemcode",
    text: "兑换中心",
    locale: "redeemCenter",
    img: icon_duihuan,
  },
  {
    id: 4,
    path: "/user/subscribe",
    text: "訂閱列表",
    locale: "subscriptionList",
    img: icon_dingyue,
  },
  {
    id: 5,
    path: "/user/collect",
    text: "收藏列表",
    locale: "favoritesList",
    img: icon_soucang,
  },
  {
    id: 6,
    path: "/user/password",
    text: "修改密碼",
    locale: "changePassword",
    img: icon_edit,
  },
  {
    id: 7,
    path: "/user/order",
    text: "购买记录",
    locale: "purchaseHistory",
    img: icon_cart,
  },
  {
    id: 8,
    path: "/user/feedback",
    text: "问题反馈",
    locale: "feedback",
    img: icon_order,
  },
  {
    id: 9,
    path: "/user/notice",
    text: "系统公告",
    locale: "systemNotification",
    img: icon_notice,
  },
  {
    id: 10,
    // path: "/user/system",
    targetBlankPath: "server_link",
    text: "在线客服",
    locale: "onlineService",
    img: icon_client_service,
  },
  {
    id: 11,
    path: "/",
    text: "安全退出",
    locale: "logout",
    img: icon_logout,
  },
];

export const actorMenuList = [
  {
    id: 1,
    order: "1",
    title: "INS推荐",
    locale: "insRecommedation",
    path: "/actor/index?order=1",
  },
  {
    id: 2,
    order: "2",
    title: "本月最热",
    locale: "mostPopularMonth",
    path: "/actor/index?order=2",
  },
  {
    id: 3,
    order: "3",
    title: "本周最热",
    locale: "mostPopularWeek",
    path: "/actor/index?order=3",
  },
  {
    id: 4,
    order: "4",
    title: "人气女优",
    locale: "mostPopularPornstars",
    path: "/actor/index?order=4",
  },
];

export const hotMenuList = [
  {
    id: 1,
    order: "1",
    title: "所有时间",
    locale: "allTime",
    path: "/video/list?list=hot&order=1",
  },
  {
    id: 2,
    order: "2",
    title: "本月最热",
    locale: "mostPopularMonth",
    path: "/video/list?list=hot&order=2",
  },
  {
    id: 3,
    order: "3",
    title: "本周最热",
    locale: "mostPopularWeek",
    path: "/video/list?list=hot&order=3",
  },
  {
    id: 4,
    order: "4",
    title: "今日最热",
    locale: "mostPopularToday",
    path: "/video/list?list=hot&order=4",
  },
];

import icon_user from "/icon_user.png";
import icon_video from "/icon_video.png";
import icon_calendar from "/icon_calendar.png";
import icon_clock from "/icon_clock.png";
import icon_fav from "/icon_fav.png";
import icon_collect from "/icon_collect.png";
import icon_share from "/icon_share.png";

export const yellowOperateList = [
  {
    id: 1,
    icon: icon_user,
    path: "/video/list",
    key: "actor",
    secondKey: "name",
  },
  {
    id: 3,
    icon: icon_calendar,
    path: "/video/list",
    key: "publish_date",
  },
  {
    id: 4,
    icon: icon_clock,
    key: "duration",
    path: "/video/list",
  },
  {
    id: 6,
    icon: icon_fav,
    icon_true: "/icon_fav_on.png",
    label: "subscribeThisPornstars",
    label_on: "subscribedThisPornstars",
    path: "/video/list",
  },
  {
    id: 7,
    icon: icon_collect,
    icon_true: "/icon_collect_on",
    label: "favoriteThisVideo",
    label_on: "favoritedThisVideo",
    path: "/video/list",
  },
  {
    id: 8,
    icon: icon_share,
    label: "shareThisVideo",
    path: "/video/list",
    freeVIP: true,
  },
];
export const operateList = [
  {
    id: 1,
    icon: icon_user,
    path: "/video/list",
    key: "actor",
    secondKey: "name",
  },
  {
    id: 2,
    icon: icon_video,
    path: "/video/list",
    key: "mash",
  },
  {
    id: 3,
    icon: icon_calendar,
    path: "/video/list",
    key: "publish_date",
  },
  {
    id: 4,
    icon: icon_clock,
    label: "120",
    path: "/video/list",
  },
  {
    id: 5,
    icon: icon_video,
    path: "/video/list",
    key: "publisher",
    secondKey: "name",
  },
  {
    id: 6,
    icon: icon_fav,
    icon_true: "/icon_fav_on.png",
    label: "subscribeThisPornstars",
    label_on: "subscribedThisPornstars",
    path: "/video/list",
  },
  {
    id: 7,
    icon: icon_collect,
    icon_true: "/icon_collect_on",
    label: "favoriteThisVideo",
    label_on: "favoritedThisVideo",
    path: "/video/list",
  },
  {
    id: 8,
    icon: icon_share,
    label: "shareThisVideo",
    path: "/video/list",
    freeVIP: true,
  },
];

export const INITIALVIDEOLIST = [
  {
    id: 1,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 2,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 3,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 4,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 5,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 6,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 7,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 8,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 9,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
  {
    id: 10,
    collect_count: 0,
    actor: { id: 1, image: "", name: "..." },
    play: 0,
    thumb: "",
    preview: "",
  },
];

export const INITIALBANNERLIST = [
  {
    id: 1,
    aid: 0,
    vid: 0,
    thumb: "",
    title: "",
    url: "",
  },
  {
    id: 2,
    aid: 0,
    vid: 0,
    thumb: "",
    title: "",
    url: "",
  },
  {
    id: 3,
    aid: 0,
    vid: 0,
    thumb: "",
    title: "",
    url: "",
  },
];

export const INITIALTAGLIST = {
  current_page: 1,
  data: [
    {
      id: 1,
      image: "",
      name: "",
    },
    {
      id: 2,
      image: "",
      name: "",
    },
    {
      id: 3,
      image: "",
      name: "",
    },
    {
      id: 4,
      image: "",
      name: "",
    },
    {
      id: 5,
      image: "",
      name: "",
    },
    {
      id: 6,
      image: "",
      name: "",
    },
    {
      id: 7,
      image: "",
      name: "",
    },
    {
      id: 8,
      image: "",
      name: "",
    },
    {
      id: 9,
      image: "",
      name: "",
    },
    {
      id: 10,
      image: "",
      name: "",
    },
    {
      id: 11,
      image: "",
      name: "",
    },
    {
      id: 12,
      image: "",
      name: "",
    },
    {
      id: 13,
      image: "",
      name: "",
    },
    {
      id: 14,
      image: "",
      name: "",
    },
    {
      id: 15,
      image: "",
      name: "",
    },
    {
      id: 16,
      image: "",
      name: "",
    },
    {
      id: 17,
      image: "",
      name: "",
    },
    {
      id: 18,
      image: "",
      name: "",
    },
    {
      id: 19,
      image: "",
      name: "",
    },
    {
      id: 20,
      image: "",
      name: "",
    },
  ],
  last_page: 1,
  per_page: 1,
  total: 1,
};
