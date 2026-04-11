import { useEffect, useId, useState } from "react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import signUpBg from "@/assets/sign-up-bg.png";
import { useGlobalImageWithFallback } from "@/hooks/useGlobalImageWithFallback";
import { useIdentityCard } from "@/contexts/IdentityCardContext";
import { EyeIcon, EyeOffIcon, LockIcon, UserRound } from "lucide-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import FormErrorMessage from "./form-error-alert";
import { registerUser, quickRegisterUser } from "@/services/user.service";
import { setAuthToken } from "@/utils/auth.ts";

interface RegisterFormData {
  username: string;
  password: string;
  confirmPassword: string;
  agreeToTerms: boolean;
}

type RegisterForm = RegisterFormData;

const registerSchema = (t: ReturnType<typeof useTranslation>["t"]) =>
  z
    .object({
      username: z.string().min(1, t("auth.username_label")),
      password: z.string().min(6, t("auth.password_label")),
      confirmPassword: z.string().min(1, t("auth.confirm_password")),
      agreeToTerms: z.boolean().refine((val) => val, {
        message: t("auth.agree_terms"),
      }),
    })
    .refine((data) => data.password === data.confirmPassword, {
      message: t("auth.password_label"),
      path: ["confirmPassword"],
    });

interface SignUpProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSwitchToSignIn: () => void;
}

export default function SignUp({
  open,
  onOpenChange,
  onSwitchToSignIn,
}: SignUpProps) {
  const { t } = useTranslation();
  const id = useId();
  const queryClient = useQueryClient();
  const { openIdentityCard } = useIdentityCard();
  const backgroundImage = useGlobalImageWithFallback({
    imageKey: "registerBg",
    fallbackImage: signUpBg,
  });
  const [isVisible, setIsVisible] = useState<boolean>(false);
  const [isConfirmVisible, setIsConfirmVisible] = useState<boolean>(false);
  const [registerError, setRegisterError] = useState<string | null>(null);
  const [useQuickRegister, setUseQuickRegister] = useState<boolean>(false);

  const { mutate, isPending } = useMutation({
    mutationFn: (payload: {
      username: string;
      password: string;
      repassword: string;
    }) => {
      return registerUser(payload);
    },
    onSuccess: async (data) => {
      if (data.code !== 1 || !data.data || !data.data.token) {
        setRegisterError(data.msg || t("common.error_loading"));
        return;
      }
      // Use utility function to set token and trigger events
      setAuthToken(data.data.token);
      try {
        // Invalidate all active queries to refresh page data after registration
        queryClient.invalidateQueries({ type: "active" });

        onOpenChange(false);
        setRegisterError(null);
      } catch (error) {
        console.error("Failed to fetch profile:", error);
      }
    },
    onError: () => {
      setRegisterError(t("common.error_loading"));
    },
  });

  const { mutate: quickRegister, isPending: isQuickRegisterPending } =
    useMutation({
      mutationFn: () => quickRegisterUser(),
      onSuccess: async (data) => {
        if (data.code !== 1 || !data.data || !data.data.user || !data.data.user.token) {
          setRegisterError(data.msg || t("common.error_loading"));
          setUseQuickRegister(false);
          return;
        }
        setAuthToken(data.data.user.token);
        try {
          queryClient.invalidateQueries({ type: "active" });
          onOpenChange(false);
          setRegisterError(null);
          // Open identity card modal after successful quick register with a slight delay to avoid race condition
          setTimeout(() => {
            openIdentityCard();
          }, 300);
        } catch (error) {
          console.error("Failed to complete quick register:", error);
        }
      },
      onError: (error) => {
        console.error("Quick register error:", error);
        setRegisterError(t("common.error_loading"));
        setUseQuickRegister(false);
      },
    });

  const {
    register,
    handleSubmit,
    formState: { errors, isValid },
    reset,
    control,
  } = useForm<RegisterForm>({
    resolver: zodResolver(registerSchema(t)),
    mode: "onChange",
    defaultValues: {
      username: "",
      password: "",
      confirmPassword: "",
      agreeToTerms: false,
    },
  });

  // Reset form and error when dialog closes
  useEffect(() => {
    if (!open) {
      reset();
      setRegisterError(null);
      setUseQuickRegister(false);
    }
  }, [open, reset]);

  const onSubmit = (data: RegisterForm) => {
    mutate({
      username: data.username,
      password: data.password,
      repassword: data.confirmPassword,
    });
  };

  const toggleVisibility = () => setIsVisible((prevState) => !prevState);
  const toggleConfirmVisibility = () =>
    setIsConfirmVisible((prevState) => !prevState);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="p-0"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        <img
          className="w-full object-cover max-h-[220px]"
          src={backgroundImage}
          alt="Sign Up Background"
          width={500}
          height={252}
        />

        <div className="flex flex-col gap-4 w-full px-8 pb-8 pt-4">
          {/* Error message UI */}
          <FormErrorMessage message={registerError || ""} />

          <DialogHeader className="text-left flex flex-row items-center justify-between">
            <DialogTitle className="text-3xl">{t("auth.register")}</DialogTitle>
            <Button
              variant="outline"
              onClick={() => {
                setRegisterError(null);
                setUseQuickRegister(true);
                quickRegister();
              }}
              disabled={isQuickRegisterPending}
              className="rounded-full px-6 py-3 text-primary border-primary hover:text-primary hover:bg-primary/10 dark:hover:text-primary dark:hover:bg-primary/20"
            >
              {isQuickRegisterPending
                ? t("auth.quick_registering")
                : t("auth.quick_register")}
            </Button>
          </DialogHeader>

          {useQuickRegister && isQuickRegisterPending ? (
            <div className="flex flex-col items-center justify-center py-12 gap-4">
              <div className="w-12 h-12 border-4 border-[#367AFF]/20 border-t-[#367AFF] rounded-full animate-spin" />
              <p className="text-sm text-muted-foreground">
                {t("auth.quick_registering")}
              </p>
            </div>
          ) : (
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
                  {errors.username && (
                    <p className="text-red-500 text-xs mt-1">
                      {errors.username.message}
                    </p>
                  )}
                </div>

                <div className="*:not-first:mt-2">
                  <Label className="text-[#757575]" htmlFor={`${id}-password`}>
                    {t("auth.password_label")}
                  </Label>
                  <div className="relative">
                    <Input
                      id={`${id}-password`}
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
                  {errors.password && (
                    <p className="text-red-500 text-xs mt-1">
                      {errors.password.message}
                    </p>
                  )}
                </div>

                <div className="*:not-first:mt-2">
                  <Label
                    className="text-[#757575]"
                    htmlFor={`${id}-confirm-password`}
                  >
                    {t("auth.confirm_password")}
                  </Label>
                  <div className="relative">
                    <Input
                      id={`${id}-confirm-password`}
                      className="peer ps-9 pe-9 placeholder:text-[#757575]"
                      placeholder={t("auth.confirm_password")}
                      type={isConfirmVisible ? "text" : "password"}
                      {...register("confirmPassword")}
                      aria-invalid={!!errors.confirmPassword}
                    />
                    <div className="text-muted-foreground/80 pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center ps-3 peer-disabled:opacity-50">
                      <LockIcon size={16} aria-hidden="true" />
                    </div>
                    <button
                      className="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring/50 absolute inset-y-0 end-0 flex h-full w-9 items-center justify-center rounded-e-md transition-[color,box-shadow] outline-none focus:z-10 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                      type="button"
                      onClick={toggleConfirmVisibility}
                      aria-label={
                        isConfirmVisible ? "Hide password" : "Show password"
                      }
                      aria-pressed={isConfirmVisible}
                      aria-controls="confirm-password"
                    >
                      {isConfirmVisible ? (
                        <EyeOffIcon size={16} aria-hidden="true" />
                      ) : (
                        <EyeIcon size={16} aria-hidden="true" />
                      )}
                    </button>
                  </div>
                  {errors.confirmPassword && (
                    <p className="text-red-500 text-xs mt-1">
                      {errors.confirmPassword.message}
                    </p>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-2">
                <Controller
                  name="agreeToTerms"
                  control={control}
                  render={({ field }) => (
                    <Checkbox
                      id={`${id}-agree-terms`}
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  )}
                />
                <Label
                  htmlFor={`${id}-agree-terms`}
                  className="text-muted-foreground font-normal text-sm"
                >
                  {t("auth.agree_terms")}
                </Label>
              </div>
              {errors.agreeToTerms && (
                <p className="text-red-500 text-xs mt-1">
                  {errors.agreeToTerms.message}
                </p>
              )}

              <Button
                type="submit"
                className="w-full rounded-full"
                disabled={!isValid || isPending}
              >
                {isPending ? t("auth.registering") : t("auth.register")}
              </Button>
            </form>
          )}

          <DialogFooter className="justify-center sm:justify-center flex-col sm:flex-row text-sm sm:text-base text-[#6C6C6C] gap-1 sm:gap-2">
            {t("auth.already_have_account")}
            <a
              className="text-[#367AFF] hover:underline cursor-pointer"
              onClick={onSwitchToSignIn}
            >
              {t("auth.click_to_login")}
            </a>
          </DialogFooter>
        </div>
      </DialogContent>
    </Dialog>
  );
}
