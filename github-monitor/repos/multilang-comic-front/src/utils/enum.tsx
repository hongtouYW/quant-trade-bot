// import languageCN from "/assets/images/language.png";
// import languageEN from "/assets/images/language-en.png";

export const yearList = [
  {
    id: 1,
    label: "全部年份",
    locale: "common.allYear",
    value: "0",
  },
  {
    id: 2,
    label: "2025",
    value: "2025",
  },
  {
    id: 3,
    label: "2024",
    value: "2024",
  },
  {
    id: 4,
    label: "2023",
    value: "2023",
  },
  {
    id: 5,
    label: "2022",
    value: "2022",
  },
  {
    id: 6,
    label: "2021",
    value: "2021",
  },
  {
    id: 7,
    label: "2020",
    value: "2020",
  },
  {
    id: 8,
    label: "2019",
    value: "2019",
  },
  {
    id: 9,
    label: "2018",
    value: "2018",
  },
  {
    id: 10,
    label: "2017",
    value: "2017",
  },
  {
    id: 11,
    label: "2016",
    value: "2016",
  },
  {
    id: 12,
    label: "2015",
    value: "2015",
  },
  {
    id: 13,
    label: "2014",
    value: "2014",
  },
];

export const monthList = [
  {
    id: 1,
    label: "全部月份",
    locale: "common.allMonth",
    value: "0",
  },
  {
    id: 2,
    label: "1月",
    value: "1",
  },
  {
    id: 3,
    label: "2月",
    value: "2",
  },
  {
    id: 4,
    label: "3月",
    value: "3",
  },
  {
    id: 5,
    label: "4月",
    value: "4",
  },
  {
    id: 6,
    label: "5月",
    value: "5",
  },
  {
    id: 7,
    label: "6月",
    value: "6",
  },
  {
    id: 8,
    label: "7月",
    value: "7",
  },
  {
    id: 9,
    label: "8月",
    value: "8",
  },
  {
    id: 10,
    label: "9月",
    value: "9",
  },
  {
    id: 11,
    label: "10月",
    value: "10",
  },
  {
    id: 12,
    label: "11月",
    value: "11",
  },
  {
    id: 13,
    label: "12月",
    value: "12",
  },
];

export const languageList = [
  {
    id: 1,
    name: "English",
    value: "en-US",
    image: `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/language-en.png`,
  },
  {
    id: 2,
    name: "中文",
    value: "zh-CN",
    image: `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/language.png`,
  },
];

export const sideMenus = [
  {
    id: 1,
    name: "账户信息",
    locale: "user.account",
    icon: "/assets/images/icon-user.svg",
    path: "/user/account",
    isLogin: true,
  },
  {
    id: 2,
    name: "充值",
    locale: "user.recharge",
    icon: "/assets/images/icon-topup.svg",
    path: "/user/topup",
    isLogin: true,
  },
  {
    id: 3,
    name: "我的书柜",
    locale: "user.myBookshelf",
    icon: "/assets/images/icon-bookmark.svg",
    // redirect: "/user/subscription",
    children: true,
    isLogin: true,
    // children: [
    //   {
    //     id: 4,
    //     name: "订阅",
    //     path: "/user/subscription",
    //   },
    //   {
    //     id: 5,
    //     name: "我的最爱",
    //     path: "/user/setting",
    //   },
    // ],
  },
  {
    id: 4,
    name: "订阅",
    locale: "user.subscription",
    parentId: 3,
    path: "/user/subscription",
    isLogin: true,
  },
  {
    id: 5,
    name: "我的最爱",
    locale: "user.myFavourite",
    parentId: 3,
    path: "/user/favourite",
    isLogin: true,
  },
  {
    id: 6,
    name: "阅读记录",
    locale: "user.readingHistory",
    parentId: 3,
    path: "/user/history",
    isLogin: true,
  },
  // {
  //   id: 7,
  //   name: "优惠活动",
  //   icon: "/assets/images/icon-discount.svg",
  //   to: "/user/offers",
  // },
  {
    id: 8,
    name: "自动购买",
    locale: "user.autoPurchase",
    icon: "/assets/images/icon-purchase.svg",
    switch: true,
    isLogin: true,
    // path: "/user/setting",
  },
  {
    id: 9,
    name: "客户中心",
    locale: "user.customerCenter",
    icon: "/assets/images/icon-customer-service.svg",
    path: "/user/cs",
    isLogin: false,
    // configUrl: "customer_url",
    // path: "/user/setting",
  },
  {
    id: 10,
    name: "退出登录",
    locale: "user.logout",
    icon: "/assets/images/icon-logout.svg",
    // to: "/",
    isLogout: true,
    isLogin: true,
  },
];

export const newSideMenus = [
  {
    id: 1,
    name: "home",
    locale: "common.home",
    icon: "/assets/images/icon-home-black.svg",
    activeIcon: "/assets/images/icon-home-pink.svg",
    path: "/",
    isLogin: false,
  },
  {
    id: 2,
    name: "account",
    locale: "user.account",
    icon: "/assets/images/icon-user-outline-black.svg",
    activeIcon: "/assets/images/icon-user-outline-pink.svg",
    path: "/user/account",
    isLogin: true,
  },
  {
    id: 3,
    name: "redeem",
    locale: "user.membershipRedemption",
    icon: "/assets/images/icon-ticket-sketch-black.svg",
    activeIcon: "/assets/images/icon-ticket-sketch-pink.svg",
    path: "/user/redeem",
    isLogin: true,
  },
  {
    id: 4,
    name: "shop",
    locale: "user.plan",
    icon: "/assets/images/icon-shopping-bag-black.svg",
    activeIcon: "/assets/images/icon-shopping-bag-pink.svg",
    path: "/user/topup",
    isLogin: true,
  },
  {
    id: 5,
    name: "favourite",
    locale: "user.myFavourite",
    icon: "/assets/images/icon-heart-sketch.svg",
    activeIcon: "/assets/images/icon-heart-sketch-pink.svg",
    path: "/user/favourite",
    isLogin: true,
  },
  {
    id: 6,
    name: "history",
    locale: "user.readingHistory",
    icon: "/assets/images/icon-history-sketch.svg",
    activeIcon: "/assets/images/icon-history-sketch-pink.svg",
    path: "/user/history",
    isLogin: true,
  },
  {
    id: 7,
    name: "contactUs",
    locale: "user.contactUs",
    icon: "/assets/images/icon-contact-us-sketch-black.svg",
    activeIcon: "/assets/images/icon-contact-us-sketch-pink.svg",
    path: "/user/cs",
    isLogin: false,
  },
  {
    id: 8,
    name: "feedback",
    locale: "user.feedbackSuggestions",
    icon: "/assets/images/icon-feedback-black.svg",
    activeIcon: "/assets/images/icon-feedback-pink.svg",
    path: "/user/feedback",
    isLogin: false,
  },
  {
    id: 9,
    name: "autoPurchase",
    locale: "user.autoPurchase",
    icon: "/assets/images/icon-purchase-sketch.svg",
    activeIcon: "/assets/images/icon-purchase-sketch.svg",
    switch: true,
    isLogin: true,
  },
  {
    id: 10,
    name: "language",
    locale: "user.language",
    icon: "/assets/images/icon-globe-alt.svg",
    activeIcon: "/assets/images/icon-globe-alt.svg",
    // redirect: "/user/subscription",
    children: true,
    isLogin: false,
    // children: [
    //   {
    //     id: 4,
    //     name: "订阅",
    //     path: "/user/subscription",
    //   },
    //   {
    //     id: 5,
    //     name: "我的最爱",
    //     path: "/user/setting",
    //   },
    // ],
  },
  {
    id: 11,
    name: "logout",
    locale: "user.logout",
    icon: "/assets/images/icon-logout-sketch-red.svg",
    activeIcon: "/assets/images/icon-logout-sketch-red.svg",
    isLogout: true,
    isLogin: true,
    customClass: "text-[#F5483B]"
  },
];

export const navLinks = [
  {
    id: 1,
    to: "/",
    locale: "common.home",
    childPath: [
      "/user/account",
      "/user/topup",
      "/user/favourite",
      "/user/history",
      "/user/cs",
      "/user/setting",
      "/user/offers",
      "/user/vip",
      "/user/feedback",
    ],
  },
  {
    id: 2,
    to: "/genres",
    locale: "common.category",
  },
  {
    id: 3,
    to: "/weekly",
    locale: "common.weekly",
  },
  {
    id: 4,
    to: "/new",
    locale: "common.new",
  },
  // {
  //   id: 5,
  //   to: "/finished",
  //   locale: "common.finished",
  // },
  {
    id: 6,
    to: "/free",
    locale: "common.free",
  },
  {
    id: 7,
    to: "/ranking",
    locale: "common.ranking",
  },
  // {
  //   id: 8,
  //   to: "/vip",
  //   title: "VIP专区",
  // },
];

export const orderStatus = {
  0: {
    name: "未付款",
    locale: "user.unpaid",
    value: "unpaid",
  },
  1: {
    name: "已付款",
    locale: "user.paid",
    value: "paid",
  },
};

export const postStatus = {
  0: {
    name: "Finished",
    locale: "common.finished",
  },
};
