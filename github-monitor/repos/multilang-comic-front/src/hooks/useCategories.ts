import { useQuery } from "@tanstack/react-query";
import { getCategories } from "../api/comic-api";

const useCategories = () => {
  const { data } = useQuery({
    queryKey: ["categories"],
    queryFn: () => getCategories(),
  });
  return { data };
};

export default useCategories;
