import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from "react";
import useAxios from "../hooks/useAxios";
import Cookies from "universal-cookie";
import { TOKEN_NAME } from "../utils/constant";
import u from "../utils/utils";

const cookies = new Cookies();

interface ReactNodeProps {
  children: React.ReactNode;
}

type UserVipStatusType = {
  vip: number;
  vipTime: number;
};

type CurrentUserType = {
  id: number;
  username: string;
  // vip_end_time?: string;
  // wm_end_time?: string;
  // dm_end_time?: string;
  // k4_end_time?: string;
  // is_vip1?: number;
  // is_vip2?: number;
  // is_vip3?: number;
  // is_vip4?: number;
  signature?: string;
  ori_password?: string;
  token?: string;
  code?: string;
};

export type UserContextType = {
  userVip: UserVipStatusType;
  currentUser: CurrentUserType;
  fetchCurrentUser: () => void;
  fetchUserVipStatus: () => void;
  clearCurrentUser: () => void;
  setCurrentUser: any;
};

const INITIAL_CURRENT_USER_VALUE = {
  id: 0,
  username: "",
  // vip_end_time: "",
  // wm_end_time: "",
  // dm_end_time: "",
  // k4_end_time: "",
  // is_vip1: 0,
  // is_vip2: 0,
  // is_vip3: 0,
  // is_vip4: 0,
  signature: "",
  ori_password: "",
  token: "",
  code: "",
};

const INITIAL_USER_VIP_STATUS = {
  vip: 0,
  vipTime: 0,
};

export const UserContext = createContext<UserContextType | null>({
  userVip: INITIAL_USER_VIP_STATUS,
  currentUser: INITIAL_CURRENT_USER_VALUE,
  fetchCurrentUser: () => {},
  fetchUserVipStatus: () => {},
  clearCurrentUser: () => {},
  setCurrentUser: () => {},
});

export const UserProvider: React.FC<ReactNodeProps> = ({ children }) => {
  const { req } = useAxios("user/info", "post");
  const { req: requestVipStatus } = useAxios("user/getVipStatus", "post");
  const [currentUser, setCurrentUser] = useState<CurrentUserType>(
    INITIAL_CURRENT_USER_VALUE
  );
  const [userVip, setUserVip] = useState(INITIAL_USER_VIP_STATUS);

  const fetchCurrentUser = useCallback(async () => {
    const token = cookies.get(TOKEN_NAME);
    try {
      if (token) {
        const res = await req({ token });

        if (res?.data?.code === 1) {
          setCurrentUser(res?.data?.data);
        }
      } else {
        // TODO: SET LOGOUT
        u.removeTokens();
      }
    } catch (err) {
      console.log(err);
    }
  }, []);

  const handleGetUserVipStatus = useCallback(async () => {
    const token = cookies.get(TOKEN_NAME);
    try {
      if (token) {
        const res = await requestVipStatus({ token });

        if (res?.data?.code === 1) {
          const data = res?.data?.data;

          setUserVip({
            vip: data?.vip_status,
            vipTime: data?.vip_time,
          });
        }
      } else {
        // TODO: SET LOGOUT
        u.removeTokens();
      }
    } catch (err) {
      console.log(err);
    }
  }, []);

  const clearCurrentUser = () => {
    setCurrentUser(INITIAL_CURRENT_USER_VALUE);
  };

  useEffect(() => {
    fetchCurrentUser();
  }, [fetchCurrentUser]);

  useEffect(() => {
    handleGetUserVipStatus();
  }, [handleGetUserVipStatus]);

  const value = {
    userVip,
    currentUser,
    fetchCurrentUser,
    fetchUserVipStatus: handleGetUserVipStatus,
    clearCurrentUser,
    setCurrentUser,
  };

  return <UserContext.Provider value={value}>{children}</UserContext.Provider>;
};

export const useUser = () => {
  return useContext(UserContext) as UserContextType;
};
