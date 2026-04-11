import { useQuery } from "@tanstack/react-query";
import { fetchNotices } from "@/services/notice.service.ts";

export const useNotices = () =>
  useQuery({
    queryKey: ["notices"],
    queryFn: fetchNotices,
    refetchOnWindowFocus: false,
  });