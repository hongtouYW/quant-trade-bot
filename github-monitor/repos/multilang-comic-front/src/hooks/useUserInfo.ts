import { useQuery } from "@tanstack/react-query";
import { getUserInfo } from "../api/user-api";
import Cookies from "universal-cookie";
import { COOKIE_NAME } from "../utils/constant";

const cookies = new Cookies();

const useUserInfo = () => {
  const { data, refetch, isSuccess, isError } = useQuery({
    queryKey: ["userInfo"],
    queryFn: () => getUserInfo({ token: cookies.get(COOKIE_NAME) }),
  });
  return { data, refetch, isSuccess, isError };
};

export default useUserInfo;
