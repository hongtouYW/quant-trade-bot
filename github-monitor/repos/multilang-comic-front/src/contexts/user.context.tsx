import { createContext, useContext, useEffect, useState } from "react";
import Cookies from "universal-cookie";
import { COOKIE_NAME } from "../utils/constant";
// import type { APIResponseType } from "../api/type";
// import { API_ENDPOINTS } from "../api/api-endpoint";
// import { http } from "../api";
import useUserInfo from "../hooks/useUserInfo";
import { modalEvents, MODAL_EVENTS } from "../utils/modalEvents";

const cookies = new Cookies();
interface ReactNodeProps {
  children: React.ReactNode;
}

type UserInfoType = {
  id: number;
  username: string;
  token: string;
  token_vip: number;
  auto_buy?: number;
  code?: string;
  discount_time?: number;
  email?: string;
  isvip_status?: number;
  nickname?: string;
  score?: number;
  viptime?: string;
};

type UserContextType = {
  userInfo: UserInfoType;
  handleSetUserInfo: (userInfo: UserInfoType, remember?: boolean) => void;
  refreshUserInfo: () => void;
  handleLogout: () => void;
  isOpenUserAuthModal: {
    open: boolean;
    type: "login" | "register" | "logout";
  };
  setIsOpenUserAuthModal: (isOpenUserAuthModal: {
    open: boolean;
    type: "login" | "register" | "logout";
  }) => void;
  isOpenNoticeModal: boolean;
  setIsOpenNoticeModal: (isOpen: boolean) => void;
  isOpenConfirm18Modal: boolean;
  setIsOpenConfirm18Modal: (isOpen: boolean) => void;
};

const UserContext = createContext<UserContextType | null>({
  userInfo: {
    id: 0,
    username: "",
    token: "",
    token_vip: 0,
    auto_buy: 0,
    code: "",
    discount_time: 0,
    email: "",
    isvip_status: 0,
    score: 0,
  },
  handleSetUserInfo: () => {},
  refreshUserInfo: () => {},
  handleLogout: () => {},
  isOpenUserAuthModal: {
    open: false,
    type: "login",
  },
  setIsOpenUserAuthModal: () => {},
  isOpenNoticeModal: false,
  setIsOpenNoticeModal: () => {},
  isOpenConfirm18Modal: false,
  setIsOpenConfirm18Modal: () => {},
});

export const UserProvider: React.FC<ReactNodeProps> = ({ children }) => {
  const {
    data: userInfoData,
    refetch: refetchUserInfo,
    isSuccess: isSuccessUserInfo,
    isError: isErrorUserInfo,
  } = useUserInfo();

  const [userInfo, setUserInfo] = useState<UserInfoType>({
    id: 0,
    username: "",
    token: cookies.get(COOKIE_NAME) || "",
    token_vip: 0,
    auto_buy: 0,
    code: "",
    discount_time: 0,
    email: "",
    isvip_status: 0,
    score: 0,
  });
  const [isOpenUserAuthModal, setIsOpenUserAuthModal] = useState<{
    open: boolean;
    type: "login" | "register" | "logout";
  }>({
    open: false,
    type: "login",
  });
  const [isOpenNoticeModal, setIsOpenNoticeModal] = useState(false);
  const [isOpenConfirm18Modal, setIsOpenConfirm18Modal] = useState(false);

  const handleSetUserInfo = (
    userInfo: UserInfoType,
    remember: boolean = false
  ) => {
    setUserInfo(userInfo);
    cookies.set(COOKIE_NAME, userInfo.token, {
      path: "/",
      maxAge: remember ? 60 * 60 * 24 * 30 : 60 * 60 * 24 * 2, // 30 days if remember is true, 2 days if remember is false
    });
  };

  const handleLogout = () => {
    cookies.remove(COOKIE_NAME, {
      path: "/",
    });
    setUserInfo({
      id: 0,
      username: "",
      token: "",
      token_vip: 0,
      auto_buy: 0,
    });
  };

  useEffect(() => {
    if (isSuccessUserInfo) {
      if (userInfoData?.data?.code === 1) {
        setUserInfo(userInfoData?.data?.data);
      } else {
        handleLogout();
      }
    }
    if (isErrorUserInfo) {
      handleLogout();
    }
  }, [userInfoData, isSuccessUserInfo, isErrorUserInfo]);

  // Listen for modal events from outside React components
  useEffect(() => {
    const handleOpenLoginModal = () => {
      setIsOpenUserAuthModal({ type: "login", open: true });
    };

    const handleOpenRegisterModal = () => {
      setIsOpenUserAuthModal({ type: "register", open: true });
    };

    const handleOpenLogoutModal = () => {
      setIsOpenUserAuthModal({ type: "logout", open: true });
    };

    modalEvents.on(MODAL_EVENTS.OPEN_LOGIN_MODAL, handleOpenLoginModal);
    modalEvents.on(MODAL_EVENTS.OPEN_REGISTER_MODAL, handleOpenRegisterModal);
    modalEvents.on(MODAL_EVENTS.OPEN_LOGOUT_MODAL, handleOpenLogoutModal);

    return () => {
      modalEvents.off(MODAL_EVENTS.OPEN_LOGIN_MODAL, handleOpenLoginModal);
      modalEvents.off(MODAL_EVENTS.OPEN_REGISTER_MODAL, handleOpenRegisterModal);
      modalEvents.off(MODAL_EVENTS.OPEN_LOGOUT_MODAL, handleOpenLogoutModal);
    };
  }, []);

  const value = {
    userInfo,
    handleSetUserInfo,
    refreshUserInfo: refetchUserInfo,
    handleLogout,
    isOpenUserAuthModal,
    setIsOpenUserAuthModal,
    isOpenNoticeModal,
    setIsOpenNoticeModal,
    isOpenConfirm18Modal,
    setIsOpenConfirm18Modal,
  };
  return <UserContext.Provider value={value}>{children}</UserContext.Provider>;
};

// eslint-disable-next-line react-refresh/only-export-components
export const useUser = () => {
  return useContext(UserContext) as UserContextType;
};
