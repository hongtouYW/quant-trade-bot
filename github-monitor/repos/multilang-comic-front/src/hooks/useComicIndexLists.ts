import { useQuery } from "@tanstack/react-query";
import { getComicIndexLists } from "../api/comic-api";

const useComicIndexLists = (params: { type: string }) => {
    const { data } = useQuery({
        queryKey: ["comicIndexLists", params.type],
        queryFn: () => getComicIndexLists(params),
    });
    return { data };
};

export default useComicIndexLists;