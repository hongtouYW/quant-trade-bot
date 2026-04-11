import { useQuery } from "@tanstack/react-query";
import { getTags } from "../api/comic-api";

const useTags = () => {
  const { data } = useQuery({
    queryKey: ["tags"],
    queryFn: () => getTags(),
  });
  return { data };
};

export default useTags;