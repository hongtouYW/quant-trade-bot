import { NavLink, useNavigate } from "react-router";
import { useMenu } from "../contexts/menu.context";
import { useTranslation } from "react-i18next";
import { useUser } from "../contexts/user.context";
import { newSideMenus } from "../utils/enum";
import { useEffect, useState } from "react";
// import LogoutModal from "./modules/LogoutModal";
import { API_ENDPOINTS } from "../api/api-endpoint";
import type { APIResponseType } from "../api/type";
import { toast } from "react-toastify";
import { http } from "../api";
// import { useConfig } from "../contexts/config.context";

const UserWrapper = ({ children }: { children: React.ReactNode }) => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  // const { config } = useConfig();
  const { userInfo, refreshUserInfo, setIsOpenUserAuthModal } = useUser();

  const [isAutoRenewal, setIsAutoRenewal] = useState(false);
  // const [isOpenLogoutModal, setIsOpenLogoutModal] = useState(false);
  const { isShowChildrenList, setIsShowChildrenList } = useMenu();

  const handleCheckActivePath = (item: any) => {
    if (['/user/edit-password', '/user/account'].includes(location.pathname) && item.path === '/user/account') {
      return true
    }

    return location.pathname === item.path;
  };

  const handleOpenChildrenList = (id: number) => {
    setIsShowChildrenList((prev) => {
      if (prev.includes(id)) {
        return prev.filter((item) => item !== id);
      }
      return [...prev, id];
    });
  };

  const handleAutoRenewal = async () => {
    const params = {
      token: userInfo?.token,
      auto_buy: isAutoRenewal ? 0 : 1,
    };
    // console.log("params", params);
    const res = await http.post<APIResponseType>(
      API_ENDPOINTS.userAutoBuy,
      params
    );

    // console.log("res", res);
    if (res?.data?.code === 1) {
      toast.success(res?.data?.msg);
      setIsAutoRenewal((prev) => !prev);
      refreshUserInfo();
    } else {
      toast.error(res?.data?.msg);
    }
  };

  const handleNavigate = (menu: any) => {
    if (menu.isLogin && !userInfo?.token) {
      return setIsOpenUserAuthModal({ type: "login", open: true });
    }

    if (menu.children) {
      return handleOpenChildrenList(menu.id);
    }

    navigate(menu.path);
  };

  useEffect(() => {
    if (userInfo?.auto_buy) {
      setIsAutoRenewal(userInfo?.auto_buy === 1);
    }
  }, [userInfo]);

  return (
    <>
      <div className="bg-greyscale-50 h-full lg:py-6 lg:px-4">
        <div className="max-w-screen-xl mx-auto h-full">
          <div className="flex shadow-none overflow-hidden h-full lg:shadow-md lg:rounded-2xl">
            <div className="hidden flex-1 bg-greyscale-100 lg:block lg:min-h-[576px] lg:min-w-[288px]">
              {newSideMenus
                .filter((menu: any) => !["language", "home"].includes(menu.name))
                .map((menu: any) => {
                  if (
                    menu.parentId &&
                    !isShowChildrenList.includes(menu.parentId)
                  ) {
                    return null;
                  }
                  if (menu.isLogout && !userInfo?.token) {
                    return null;
                  }
                  if (menu.isLogin && !userInfo?.token) {
                    return null;
                  }

                  return (
                    <NavLink
                      key={menu.id}
                      to={menu.path || "#"}
                      // to={menu.path || "#"}
                      // target={menu.configUrl ? "_blank" : "_self"}
                      // target="_self"
                      className={`block ${handleCheckActivePath(menu)
                        ? "bg-white"
                        : "bg-greyscale-100"
                        }`}
                      onClick={() => handleNavigate(menu)}
                    >
                      <div
                        className={`py-6 px-4 ${handleCheckActivePath(menu)
                          ? "border-l-3 border-primary text-primary font-semibold"
                          : "border-l-3 border-transparent "
                          }`}
                      >
                        <div className="flex items-center gap-3">
                          {menu.icon && (
                            <img
                              src={`${import.meta.env.VITE_INDEX_DOMAIN}${handleCheckActivePath(menu) ? menu.activeIcon : menu.icon}`}
                              alt={menu.name}
                              className="w-6 h-6"
                            />
                          )}
                          <p
                            className={`w-full flex items-center justify-between gap-1 ${menu.isLogout ? 'text-[#F5483B]' : ''}`}
                            onClick={() => {
                              if (menu.isLogout) {
                                setIsOpenUserAuthModal({
                                  type: "logout",
                                  open: true,
                                });
                              }
                            }}
                          >
                            {menu?.locale ? t(menu?.locale) : menu?.name}
                            {menu.children && (
                              <img
                                className="w-4 h-4"
                                src={`${import.meta.env.VITE_INDEX_DOMAIN
                                  }/assets/images/icon-cheveron-down.svg`}
                                alt="arrow-down"
                              />
                            )}
                          </p>
                          {menu.switch && userInfo?.token && (
                            <label className="flex cursor-pointer select-none items-center">
                              <div className="relative">
                                {/* <input
                              type='checkbox'
                              checked={isAutoRenewal}
                              onClick={() => {
                                // console.log("isAutoRenewal", isAutoRenewal);
                                setIsAutoRenewal(!isAutoRenewal);

                              }}
                              className='sr-only'
                            /> */}
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
                    </NavLink>
                  );
                })}
            </div>
            <div className="flex-5 bg-white w-full">
              {children}
            </div>
          </div>
        </div>
      </div>
      {/* <LogoutModal
        isOpenLogoutModal={isOpenLogoutModal}
        onClose={() => setIsOpenLogoutModal(false)}
        onConfirm={() => {
          setIsOpenLogoutModal(false);
          handleLogout();
          navigate("/");
        }}
      /> */}
    </>
  );
};

export default UserWrapper;
