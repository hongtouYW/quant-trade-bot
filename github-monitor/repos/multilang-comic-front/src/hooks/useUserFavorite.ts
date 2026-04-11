import { useQuery } from "@tanstack/react-query";
import { getUserFavorite } from "../api/user-api";
// import { COOKIE_NAME } from "../utils/constant";
// import Cookies from "universal-cookie";

// const cookies = new Cookies();

const useUserFavorite = (params: { token: string, page: string, limit: string }) => {
  const { data, refetch } = useQuery({
    queryKey: ["userFavorite", params],
    queryFn: () => getUserFavorite(params),
  });
  return { data, refetch };
};

export default useUserFavorite;