import { useQuery } from "@tanstack/react-query";
import { getNotice } from "../api/comic-api";

const useNotice = () => {
  const { data } = useQuery({
    queryKey: ["notice"],
    queryFn: () => getNotice(),
  });
  return { data };
};

export default useNotice;