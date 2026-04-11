import { useQuery } from "@tanstack/react-query";
import { loginUser } from "@/services/user.service.ts";
import type { LoginUserPayload } from "@/types/user.types.ts";

export const useLoginUser = (payload: LoginUserPayload) =>
  useQuery({
    queryKey: ["loginUser"], // Include the param in query key
    queryFn: ({ signal }) => loginUser(payload, signal),
    select: (data) => {
      return data.data;
    },
    // enabled: !localStorage.getItem("tokenNew"),
  });
