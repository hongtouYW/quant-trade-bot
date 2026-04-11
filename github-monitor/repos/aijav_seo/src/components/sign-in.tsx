import { useId, useState } from "react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import signInBg from "@/assets/sign-in-bg.png";
import { useGlobalImageWithFallback } from "@/hooks/useGlobalImageWithFallback";
import { EyeIcon, EyeOffIcon, LockIcon, UserRound } from "lucide-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { loginUser } from "@/services/user.service.ts";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import FormErrorMessage from "./form-error-alert";
import type { LoginUserPayload } from "@/types/user.types.ts";
import ForgotPasswordDialog from "./ForgotPasswordDialog";
import { setAuthToken } from "@/utils/auth.ts";
import { Base64Image } from "@/components/Base64Image.tsx";

interface LoginFormData {
  username: string;
  password: string;
  remember?: boolean;
}

type LoginForm = LoginFormData;

const loginSchema = (t: ReturnType<typeof useTranslation>["t"]) =>
  z.object({
    username: z.string().min(1, t("auth.login_required")),
    password: z.string().min(1, t("auth.login_required")),
    remember: z.boolean().optional(),
  });

interface SignInProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSwitchToSignUp: () => void;
  showTrigger?: boolean;
}

export default function SignIn({
  open,
  onOpenChange,
  onSwitchToSignUp,
  showTrigger = true,
}: SignInProps) {
  const { t } = useTranslation();
  const id = useId();
  const queryClient = useQueryClient();
  const backgroundImage = useGlobalImageWithFallback({
    imageKey: "loginBg",
    fallbackImage: signInBg,
  });

  const [isVisible, setIsVisible] = useState<boolean>(false);
  const [loginError, setLoginError] = useState<string | null>(null);
  const [forgotOpen, setForgotOpen] = useState(false);

  const { mutate, isPending } = useMutation({
    mutationFn: (payload: LoginUserPayload) => {
      delete payload.remember;
      return loginUser(payload);
    },
    onSuccess: async (data, variables) => {
      if (data.code && data.data === undefined) {
        setLoginError(data.msg || t("auth.login_required"));
        return;
      }

      // Use utility function to set token and trigger events with remember preference
      setAuthToken(data.data.token, variables.remember || false);

      // Clear popup banner storage keys to show popup again on login
      const keys = Object.keys(localStorage);
      keys.forEach((key) => {
        if (key.startsWith("popupBanner_") && key.endsWith("_lastShown")) {
          localStorage.removeItem(key);
        }
      });

      // Dispatch custom event to notify popup banner components
      window.dispatchEvent(new CustomEvent("popupBannerReset"));

      try {
        // Invalidate all active queries to refresh page data after login
        queryClient.invalidateQueries({ type: "active" });

        onOpenChange(false);
        setLoginError(null);
        reset();
      } catch (error) {
        console.error("Failed to fetch profile:", error);
      }
    },
    onError: () => {
      setLoginError(t("common.error_loading"));
    },
  });

  const {
    register,
    handleSubmit,
    formState: { errors, isValid },
    reset,
    control,
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema(t)),
    mode: "onChange",
    defaultValues: {
      username: "",
      password: "",
      remember: false,
    },
  });

  const onSubmit = (data: LoginForm) => {
    mutate({
      username: data.username,
      password: data.password,
      remember: data.remember,
    });
  };

  const toggleVisibility = () => setIsVisible((prevState) => !prevState);

  function handleForgotPassword() {
    setForgotOpen(true);
    onOpenChange(false);
  }

  const dialogContent = (
    <DialogContent className="p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
      <Base64Image
        originalUrl={backgroundImage}
        alt={`Sign In Background`}
        className="w-full object-cover max-h-[220px]"
      />

      <div className="flex flex-col gap-4 w-full px-8 pb-8 pt-4">
        {/* Error message UI */}
        <FormErrorMessage message={loginError || ""} />

        <DialogHeader className="text-left">
          <DialogTitle className="text-3xl">{t("auth.login_now")}</DialogTitle>
          <DialogDescription className="mt-2 text-base text-[#969696]">
            {t("auth.login_to_access_content")}
          </DialogDescription>
        </DialogHeader>

        <form className="space-y-5 mt-3" onSubmit={handleSubmit(onSubmit)}>
          <div className="space-y-4">
            <div className="*:not-first:mt-2">
              <Label className="text-[#757575]" htmlFor={`${id}-username`}>
                {t("auth.username_label")}
              </Label>
              <div className="relative">
                <Input
                  className="placeholder:text-[#757575] peer ps-9"
                  id={`${id}-username`}
                  placeholder={t("auth.username_label")}
                  type="text"
                  required
                  aria-invalid={!!errors.username}
                  {...register("username")}
                />
                <div className="text-muted-foreground/80 pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center ps-3 peer-disabled:opacity-50">
                  <UserRound size={16} aria-hidden="true" />
                </div>
              </div>
              {/*{errors.username && (*/}
              {/*  <p className="text-red-500 text-xs mt-1">*/}
              {/*    {errors.username.message}*/}
              {/*  </p>*/}
              {/*)}*/}
            </div>
            <div className="*:not-first:mt-2">
              <Label className="text-[#757575]" htmlFor={`${id}-password`}>
                {t("auth.password_label")}
              </Label>
              <div className="relative">
                <Input
                  id={id}
                  className="peer ps-9 pe-9 placeholder:text-[#757575]"
                  placeholder={t("auth.password_label")}
                  type={isVisible ? "text" : "password"}
                  {...register("password")}
                  aria-invalid={!!errors.password}
                />
                <div className="text-muted-foreground/80 pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center ps-3 peer-disabled:opacity-50">
                  <LockIcon size={16} aria-hidden="true" />
                </div>
                <button
                  className="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 absolute inset-y-0 end-0 flex h-full w-9 items-center justify-center rounded-e-md transition-[color,box-shadow] outline-none focus:z-10 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                  type="button"
                  onClick={toggleVisibility}
                  aria-label={isVisible ? "Hide password" : "Show password"}
                  aria-pressed={isVisible}
                  aria-controls="password"
                >
                  {isVisible ? (
                    <EyeOffIcon size={16} aria-hidden="true" />
                  ) : (
                    <EyeIcon size={16} aria-hidden="true" />
                  )}
                </button>
              </div>
              {/*{errors.password && (*/}
              {/*  <p className="text-red-500 text-xs mt-1">*/}
              {/*    {errors.password.message}*/}
              {/*  </p>*/}
              {/*)}*/}
            </div>
          </div>
          <div className="flex justify-between gap-2">
            <div className="flex items-center gap-2">
              <Controller
                name="remember"
                control={control}
                render={({ field }) => (
                  <Checkbox
                    id={`${id}-remember`}
                    checked={field.value}
                    onCheckedChange={field.onChange}
                  />
                )}
              />
              <Label
                htmlFor={`${id}-remember`}
                className="text-muted-foreground font-normal"
              >
                {t("auth.remember_me")}
              </Label>
            </div>
            <a
              className="text-sm text-red-500 cursor-pointer"
              onClick={handleForgotPassword}
            >
              {t("auth.forgot_password")}
            </a>
          </div>
          <Button
            type="submit"
            className="w-full rounded-full"
            disabled={!isValid || isPending}
          >
            {isPending ? t("auth.logging_in") : t("auth.login_now")}
          </Button>
        </form>

        <DialogFooter className="justify-center sm:justify-center flex-col sm:flex-row text-sm sm:text-base text-[#6C6C6C] gap-1 sm:gap-2">
          {t("auth.dont_have_account")}
          <a
            className="text-[#367AFF] hover:underline cursor-pointer"
            onClick={onSwitchToSignUp}
          >
            {t("auth.sign_up")}
          </a>
        </DialogFooter>
      </div>
    </DialogContent>
  );

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        {showTrigger && (
          <DialogTrigger asChild>
            <Button
              className="bg-[#EA1E61] rounded-2xl py-1.5 px-10 size-8 hover:bg-[#EA1E61] w-full"
              size="sm"
            >
              登入
            </Button>
          </DialogTrigger>
        )}
        {dialogContent}
      </Dialog>
      <ForgotPasswordDialog open={forgotOpen} onOpenChange={setForgotOpen} />
    </>
  );
}
