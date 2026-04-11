import { useEffect } from "react";
import { useNavigate } from "react-router";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import { loginUser } from "@/services/user.service";
import { setAuthToken } from "@/utils/auth";
import type { LoginUserPayload } from "@/types/user.types";
import { parseAuthLoginToken } from "@/lib/auth-token-url";

export default function AuthLogin() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { mutate } = useMutation({
    mutationFn: (payload: LoginUserPayload) => loginUser(payload),
    onSuccess: async (data) => {
      if (data.code === 1 && data.data) {
        setAuthToken(data.data.token, false);
        queryClient.invalidateQueries({ type: "active" });
        navigate("/", { replace: true });
      } else {
        navigate("/", { replace: true });
      }
    },
    onError: () => {
      navigate("/", { replace: true });
    },
  });

  useEffect(() => {
    const credentials = parseAuthLoginToken();
    if (credentials) {
      // Clear the hash to remove the token from the URL
      window.history.replaceState(null, "", window.location.pathname);
      mutate({
        username: credentials.username,
        password: credentials.password,
      });
    } else {
      navigate("/", { replace: true });
    }
  }, [mutate, navigate]);

  return (
    <div className="flex items-center justify-center min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <div className="text-center space-y-6">
        <div className="flex justify-center mb-6">
          <div className="relative w-16 h-16">
            <div className="absolute inset-0 border-4 border-transparent border-t-[#EC67FF] rounded-full animate-spin" />
            <div className="absolute inset-2 border-4 border-transparent border-b-[#EC67FF] rounded-full animate-spin" style={{ animationDirection: "reverse" }} />
            <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-[#EC67FF] rounded-full" />
          </div>
        </div>
        <div className="space-y-2">
          <h2 className="text-2xl font-bold text-gray-800">
            {t("auth.login_now")}
          </h2>
          <p className="text-gray-600 text-lg">
            {t("auth_login.logging_in")}
          </p>
        </div>
        <div className="flex justify-center gap-1">
          <span className="w-2 h-2 bg-[#EC67FF] rounded-full animate-bounce" style={{ animationDelay: "0s" }} />
          <span className="w-2 h-2 bg-[#EC67FF] rounded-full animate-bounce" style={{ animationDelay: "0.2s" }} />
          <span className="w-2 h-2 bg-[#EC67FF] rounded-full animate-bounce" style={{ animationDelay: "0.4s" }} />
        </div>
      </div>
    </div>
  );
}
