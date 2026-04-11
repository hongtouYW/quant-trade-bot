import { useEffect } from "react";
import { useSearchParams, useNavigate } from "react-router";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import { loginUser } from "@/services/user.service";
import { setAuthToken } from "@/utils/auth";
import type { LoginUserPayload } from "@/types/user.types";

export default function AuthLogin() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const username = searchParams.get("username");
  const password = searchParams.get("password");

  const { mutate } = useMutation({
    mutationFn: (payload: LoginUserPayload) => loginUser(payload),
    onSuccess: async (data) => {
      if (data.code === 1 && data.data) {
        // Login successful
        setAuthToken(data.data.token, false);
        queryClient.invalidateQueries({ type: "active" });
        navigate("/", { replace: true });
      } else {
        // Login failed, redirect to home with error
        navigate("/", { replace: true });
      }
    },
    onError: () => {
      // Error occurred, redirect to home
      navigate("/", { replace: true });
    },
  });

  useEffect(() => {
    if (username && password) {
      mutate({
        username,
        password,
      });
    } else {
      // Missing credentials, redirect to home
      navigate("/", { replace: true });
    }
  }, [username, password, mutate, navigate]);

  return (
    <div className="flex items-center justify-center min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <div className="text-center space-y-6">
        {/* Loading Animation */}
        <div className="flex justify-center mb-6">
          <div className="relative w-16 h-16">
            {/* Outer rotating ring */}
            <div className="absolute inset-0 border-4 border-transparent border-t-[#EC67FF] rounded-full animate-spin" />
            {/* Inner rotating ring */}
            <div className="absolute inset-2 border-4 border-transparent border-b-[#EC67FF] rounded-full animate-spin" style={{ animationDirection: "reverse" }} />
            {/* Center dot */}
            <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-[#EC67FF] rounded-full" />
          </div>
        </div>

        {/* Text */}
        <div className="space-y-2">
          <h2 className="text-2xl font-bold text-gray-800">
            {t("auth.login_now")}
          </h2>
          <p className="text-gray-600 text-lg">
            {t("auth_login.logging_in")}
          </p>
        </div>

        {/* Animated dots */}
        <div className="flex justify-center gap-1">
          <span className="w-2 h-2 bg-[#EC67FF] rounded-full animate-bounce" style={{ animationDelay: "0s" }} />
          <span className="w-2 h-2 bg-[#EC67FF] rounded-full animate-bounce" style={{ animationDelay: "0.2s" }} />
          <span className="w-2 h-2 bg-[#EC67FF] rounded-full animate-bounce" style={{ animationDelay: "0.4s" }} />
        </div>
      </div>
    </div>
  );
}
