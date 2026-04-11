import { NavLink, useLocation, useNavigate, useParams } from "react-router";
import { useUser } from "../contexts/user.context";
import { useEffect, useState } from "react";
import { isMobile, toFmt } from "../utils/utils";
import { useMenu } from "../contexts/menu.context";
import i18n from "../utils/i18n";
import { useTranslation } from "react-i18next";
import { languageList, navLinks, newSideMenus } from "../utils/enum";
// import { useConfig } from "../contexts/config.context";
import type { APIResponseType } from "../api/type";
import { http } from "../api";
import { API_ENDPOINTS } from "../api/api-endpoint";
import { toast } from "react-toastify";
import useComicDetail from "../hooks/useComicDetail";

const Navbar = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const { comicId: id } = useParams();
  const { setPrevRoute, prevRoute } = useMenu();
  const defaultLanguage = import.meta.env.VITE_DEFAULT_LANGUAGE;

  // 获取漫画详情
  const { data: comicInfo } = useComicDetail({ mid: id || "" });

  const isContentPage = location.pathname.includes("/content/");
  // const { config } = useConfig();

  const { userInfo, refreshUserInfo, setIsOpenUserAuthModal } = useUser();
  const { setIsShowChildrenList } = useMenu();

  const [isAutoRenewal, setIsAutoRenewal] = useState(false);
  const [showMobileUserMenu, setShowMobileUserMenu] = useState(false);

  const [showLanguageMenu, setShowLanguageMenu] = useState({
    open: false,
    item: languageList[0],
  });
  const [keyword, setKeyword] = useState("");

  const handleCheckActivePath = (item: any) => {
    return (
      location.pathname === item.to ||
      location.pathname === item.path
    );
    // return location.pathname === item.to;
  };

  const handleOpenChildrenList = (id: number) => {
    setIsShowChildrenList((prev) => {
      if (prev.includes(id)) {
        return prev.filter((item) => item !== id);
      }
      return [...prev, id];
    });
  };

  const handleCheckNavigate = () => {
    setShowLanguageMenu((prev) => ({
      ...prev,
      open: false,
    }));
    if (!userInfo.token && !isMobile()) {
      setIsOpenUserAuthModal({ type: "login", open: true });
      return;
    }

    if (isMobile()) {
      setShowMobileUserMenu((prev) => !prev);
    } else {
      navigate("/user/account");
    }
  };

  const handleSearch = (e: React.SyntheticEvent) => {
    e.preventDefault();

    navigate(`/search?keyword=${keyword}`);
    setKeyword("");
  };

  const handleChangeLanguage = (item: any) => {
    i18n.changeLanguage(item.value);
    localStorage.setItem("language", item.value);
    setShowLanguageMenu({ open: false, item: item });
    window.location.reload();
  };

  const handleAutoRenewal = async () => {
    const params = {
      token: userInfo?.token,
      auto_buy: isAutoRenewal ? 0 : 1,
    };
    const res = await http.post<APIResponseType>(
      API_ENDPOINTS.userAutoBuy,
      params
    );

    if (res?.data?.code === 1) {
      toast.success(res?.data?.msg);
      setIsAutoRenewal((prev) => !prev);
      refreshUserInfo();
    } else {
      toast.error(res?.data?.msg);
    }
  };

  const handleNavigate = (menu: any) => {
    if (menu.name === "contactUs") {
      navigate(menu.path);
      setShowMobileUserMenu(false);
      return;
    }
    if (menu.isLogin && !userInfo?.token) {
      return setIsOpenUserAuthModal({ type: "login", open: true });
    }
    if (menu.name === "autoPurchase") {
      return;
    }
    if (menu.children) {
      return handleOpenChildrenList(menu.id);
    }

    if (menu.isLogout) {
      setIsOpenUserAuthModal({ type: "logout", open: true });
      setShowMobileUserMenu(false);
    } else {
      setShowMobileUserMenu(false);
    }

    navigate(menu.path);
  };

  useEffect(() => {
    const language = localStorage.getItem("language");
    setShowLanguageMenu({
      open: false,
      item:
        languageList.find(
          (item) => item.value === (language || defaultLanguage)
        ) || languageList[0],
    });
  }, []);

  useEffect(() => {
    if (userInfo?.auto_buy) {
      setIsAutoRenewal(userInfo?.auto_buy === 1);
    }
  }, [userInfo]);

  // Close language menu when navigating to other pages
  useEffect(() => {
    setShowLanguageMenu((prev) => ({
      ...prev,
      open: false,
    }));
  }, [location.pathname]);

  useEffect(() => {
    setPrevRoute(location.pathname);
    if (!location.pathname?.includes("/content/")) {
      setPrevRoute(location.pathname);
    } else {
      setPrevRoute(prevRoute);
    }
  }, [location.pathname]);

  // condition if should show navbar for a page
  if (
    isMobile() &&
    [
      '/user/edit-my-profile',
      '/user/edit-password',
      '/user/account',
      '/user/redeem',
      '/user/topup',
      '/user/favourite',
      '/user/history',
      '/user/cs',
      '/search',
      '/user/feedback',
    ].includes(location.pathname)
  ) {
    return null;
  }

  return (
    <div className="z-40 bg-white w-full fixed top-0 left-0 right-0 lg:border-b lg:border-greyscale-200">
      <div className="py-2 px-4 lg:py-6 lg:h-[100px] xl:px-[108px]">
        <div className="max-w-screen-xl mx-auto flex justify-between items-center gap-4">
          {/* Logo */}
          <div className="flex items-center lg:gap-6">
            {isContentPage && isMobile() ? (
              <div className="flex items-center gap-4 py-2 w-full">
                <div
                  className="cursor-pointer p-1.5 shrink-0"
                  onClick={() => navigate(prevRoute || "/")}
                >
                  <img
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-arrow-left-black.svg`}
                    alt="back"
                    className="w-6 h-6"
                  />
                </div>
                <p className="font-semibold" title={comicInfo?.comic?.title}>
                  <span className="line-clamp-1">
                    {comicInfo?.comic?.title}
                  </span>
                </p>
              </div>
            ) : (
              <div className="flex items-center gap-3 shrink-0 xl:gap-6">
                <NavLink to="/">
                  <img
                    src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/${import.meta.env.VITE_LOGO_URL || "logo-2.png"
                      }`}
                    alt="logo"
                    className="w-max max-w-[70px] h-[50px] object-contain"
                  />
                </NavLink>
                {/* <a href="https://comictoon.vip/" target="_self">
                  <img
                    src="/assets/images/18-toggle.png"
                    alt="Badge"
                    className="h-6 object-contain cursor-pointer"
                  />
                </a> */}
              </div>
            )}

            {/* Desktop nav links */}
            <div className="hidden lg:block">
              <ul className="flex items-center">
                {navLinks.map((link: any) => (
                  <li key={link.id} className={`py-1.5 px-5 border-b-3 ${handleCheckActivePath(link) ? "text-primary border-primary" : "border-transparent"}`}>
                    <NavLink to={link.to}>
                      {t(link.locale)}
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          </div>

          {/* Search and Login */}
          <div className="flex items-center gap-2 lg:gap-6 shrink-0">
            {/* 搜索 */}
            <div className="cursor-pointer">
              <img
                src={`${import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/search-normal.svg`}
                alt="search"
                onClick={handleSearch}
              />
            </div>

            {/* 登录注册 */}
            {!userInfo.token && (
              <div className="hidden lg:block">
                <button
                  type="button"
                  className="text-greyscale-50 px-4 py-3 rounded-xl text-sm font-semibold flex items-center gap-1 bg-primary cursor-pointer"
                  onClick={() => {
                    setIsOpenUserAuthModal({ type: "login", open: true });
                  }}
                >
                  {t("user.login")}/{t("user.register")}
                </button>
              </div>
            )}

            {/* 储值 */}
            {userInfo.token && (
              <div
                className="hidden lg:flex items-center gap-1 bg-secondary-gold-100 rounded-xl py-3 px-6 cursor-pointer"
                onClick={() => {
                  if (!userInfo.token) {
                    setIsOpenUserAuthModal({ type: "login", open: true });
                    return;
                  }
                  navigate("/user/topup");
                }}
              >
                <img
                  className="w-5 h-5"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/top-up.png`}
                  alt="Top Up"
                />
                <p className="font-medium text-secondary-gold-600">{t("user.topup")}</p>
              </div>
            )}

            {/* 书架 */}
            {/* <div
              className="flex items-center gap-1 bg-[#CDE9F5] rounded-full py-2 px-3 cursor-pointer max-xs:flex-col max-xs:bg-transparent max-xs:px-0 max-xs:hidden"
              onClick={() => {
                // console.log("book");
                if (!userInfo.token) {
                  setIsOpenUserAuthModal({ type: "login", open: true });
                  return;
                }
                navigate("/user/subscription");
              }}
            >
              <img
                className="w-5 h-5 max-xs:w-6 max-xs:h-6"
                src={book}
                alt="book"
              />
              <p className="font-medium max-xs:hidden">{t("user.bookShelf")}</p>
            </div> */}

            {/* 头像 */}
            {userInfo.token && (
              <div className="hidden lg:block">
                <img
                  className="w-8 h-8 sm:w-9 sm:h-9 lg:w-10 lg:h-10 cursor-pointer"
                  src={`${import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/default-avatar.png`}
                  alt="avatar"
                  onClick={handleCheckNavigate}
                />
              </div>
            )}

            {/* mobile menu 菜单 */}
            <div className="block cursor-pointer lg:hidden">
              <img
                className="max-xs:w-8 max-xs:h-8"
                src={`${import.meta.env.VITE_INDEX_DOMAIN
                  }/assets/images/menu-alt.svg`}
                alt="menu"
                onClick={handleCheckNavigate}
              />
            </div>
            {/* 语言  */}
            <div className="hidden lg:block lg:relative">
              <img
                className="w-8 h-8 max-xs:w-7 max-xs:h-7 cursor-pointer object-cover rounded-full"
                src={showLanguageMenu.item.image}
                alt="language"
                onClick={() =>
                  setShowLanguageMenu((prev) => ({
                    open: !prev.open,
                    item: prev.item,
                  }))
                }
              />
              {showLanguageMenu.open && (
                <div className="absolute top-10 right-0 bg-white rounded-lg shadow-lg p-2 flex flex-col gap-2 z-[999]">
                  {languageList.map((item: any) => (
                    <div
                      key={item.id}
                      className="flex items-center gap-2 cursor-pointer"
                      onClick={() => handleChangeLanguage(item)}
                    >
                      <div className="w-8 h-8 rounded-full">
                        <img
                          className="w-full h-full object-cover rounded-full"
                          src={item.image}
                          alt={item.name}
                        />
                      </div>
                      <p className="font-medium">{item.name}</p>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
      {isContentPage ? null : (
        <div className="block px-4 overflow-x-auto scrollbar-hide lg:hidden">
          <ul className="flex items-center xs:justify-around xs:gap-8">
            {navLinks.map((link) => (
              <li key={link.id}>
                <NavLink to={link.to}>
                  <p
                    className={`text-sm w-max px-4 py-2 ${handleCheckActivePath(link) ? "font-semibold text-primary border-b-3 border-primary" : ""
                      }`}
                  >
                    {t(link.locale)}
                  </p>
                </NavLink>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div
        className={`fixed top-0  min-w-[300px] transition-all duration-300 h-screen bg-white shadow-lg ${showMobileUserMenu ? "right-0 z-[2]" : "-right-[450px] z-[1]"
          } lg:hidden`}
      >
        <div className="flex items-center justify-between gap-2 bg-greyscale-900 px-4 py-3">
          <div
            className="bg-primary px-4 py-3 rounded-xl cursor-pointer"
            onClick={() => setShowMobileUserMenu(false)}
          >
            <img
              className="w-5 h-5"
              src={`${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/icon-cheveron-right-white.svg`}
              alt="arrow-right"
            />
          </div>
          {!userInfo.token && (
            <div className="flex items-center gap-2">
              {/* <p
                className="text-primary px-[14px] py-1 border border-primary rounded-full text-xs flex items-center gap-1"
                onClick={() => {
                  setIsOpenUserAuthModal({ type: "register", open: true });
                  setShowMobileUserMenu(false);
                }}
              >
                <img src="/assets/images/icon-register.svg" alt="register" />
                Register
              </p> */}

              <button
                type="button"
                className="text-greyscale-50 px-4 py-3 rounded-xl text-sm font-semibold flex items-center gap-1 bg-primary cursor-pointer"
                onClick={() => {
                  setIsOpenUserAuthModal({ type: "login", open: true });
                  setShowMobileUserMenu(false);
                }}
              >
                {t("user.login")}/{t("user.register")}
              </button>
            </div>
          )}
          {userInfo.token && (
            <div className="text-white text-sm">
              Hi, {userInfo.username || userInfo.email}
            </div>
          )}
        </div>
        {userInfo.token && (
          <div className="p-4 bg-greyscale-900">
            <div className="flex justify-between text-sm pb-2">
              <p className="text-white">{t("user.coinBalance")}:</p>
              <p className="text-[#FF9500] font-semibold">
                {userInfo.score} {t("common.coin")}
              </p>
            </div>
            <div className="flex justify-between text-sm pt-2">
              <p className="text-white">{t("common.vipPass")}:</p>
              <p className="text-greyscale-50 font-semibold">
                {userInfo.isvip_status === 1
                  ? toFmt(parseInt(userInfo.viptime || "0") * 1000 || 0)
                  : "-"}
              </p>
            </div>
          </div>
        )}
        <div className="bg-greyscale-100 overflow-y-auto h-[calc(100vh-160px)]">
          <div className="flex flex-col">
            {newSideMenus.map((menu) => {
              // if (menu.parentId && !isShowChildrenList.includes(menu.parentId)) {
              //   return null;
              // }
              if (menu.isLogin && !userInfo.token) {
                return null;
              }
              return (
                <div key={menu.id} onClick={() => handleNavigate(menu)}>
                  <div
                    className={`p-4 cursor-pointer ${handleCheckActivePath(menu)
                      ? "border-l-3 border-primary bg-white font-semibold text-primary"
                      : "border-l-3 border-transparent "
                      }`}
                  >
                    <div className="flex items-center gap-2">
                      {menu.icon && (
                        <img
                          src={`${import.meta.env.VITE_INDEX_DOMAIN}${handleCheckActivePath(menu) ? menu.activeIcon : menu.icon}`}
                          alt={menu.name}
                          className="w-6 h-6"
                        />
                      )}
                      <div
                        className={`leading-6 w-full flex items-center justify-between gap-1 text-sm ${menu.customClass || ''}`}
                      >
                        {menu?.locale ? t(menu?.locale) : menu?.name}
                        {menu.children && menu.name === "language" && (
                          <div className="relative">
                            <div
                              className="flex items-center gap-2"
                              onClick={() =>
                                setShowLanguageMenu((prev) => ({
                                  open: !prev.open,
                                  item: prev.item,
                                }))
                              }
                            >
                              <img
                                className="w-5 h-5 max-xs:w-5 max-xs:h-5 cursor-pointer object-cover rounded-full"
                                src={showLanguageMenu.item.image}
                                alt="language"
                              />
                              <img
                                className="w-4 h-4"
                                src={`${import.meta.env.VITE_INDEX_DOMAIN
                                  }/assets/images/icon-cheveron-down.svg`}
                                alt="arrow-down"
                              />
                            </div>
                            {showLanguageMenu.open && (
                              <div className="absolute top-6 right-0 bg-white rounded-lg shadow-lg p-2 flex flex-col gap-3 z-[999]">
                                {languageList.map((item: any) => (
                                  <div
                                    key={item.id}
                                    className="flex items-center gap-2 cursor-pointer"
                                    onClick={() => handleChangeLanguage(item)}
                                  >
                                    <div className="w-5 h-5 rounded-full">
                                      <img
                                        className="w-full h-full object-cover rounded-full"
                                        src={item.image}
                                        alt={item.name}
                                      />
                                    </div>
                                    <p className="font-medium text-sm">
                                      {item.name}
                                    </p>
                                  </div>
                                ))}
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                      {menu.switch && userInfo?.token && (
                        <label className="flex cursor-pointer select-none items-center">
                          <div className="relative">
                            <div
                              className={`block h-7 w-14 rounded-full bg-[#E5E7EB] ${isAutoRenewal ? "bg-primary" : ""
                                }`}
                              onClick={handleAutoRenewal}
                            >
                              <div
                                className={`dot absolute  top-1 h-5 w-5 rounded-full bg-white transition ${isAutoRenewal
                                  ? "translate-x-full left-3"
                                  : "left-1"
                                  }`}
                              ></div>
                            </div>
                          </div>
                        </label>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
      {/* Backdrop */}
      <div
        className={`hidden max-xs:block fixed top-0  w-full h-full bg-black/50 -z-[1] ${showMobileUserMenu
          ? "opacity-100 z-[1] right-0"
          : "opacity-0 -z-[2] -right-[450px]"
          }`}
        onClick={() => setShowMobileUserMenu(false)}
      ></div>
    </div>
  );
};

export default Navbar;
