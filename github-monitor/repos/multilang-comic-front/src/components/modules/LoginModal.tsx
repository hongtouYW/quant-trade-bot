import React, { useState } from "react";
import Modal from "../Modal";
import { toast } from "react-toastify";
import { useUser } from "../../contexts/user.context";
import { http } from "../../api";
import type { APIResponseType } from "../../api/type";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import Input from "../Input";
import { useConfig } from "../../contexts/config.context";
import { useTranslation } from "react-i18next";
// import { comicStatistics } from "../../utils/plugin/comicStatistics";

const LoginModal = () => {
  const { config } = useConfig();
  const { t } = useTranslation();
  const {
    isOpenUserAuthModal,
    setIsOpenUserAuthModal,
    handleSetUserInfo,
  } = useUser();

  const [showPassword, setShowPassword] = useState(false);
  const [isError, setIsError] = useState({
    username: false,
    password: false,
  });

  const handleSubmit = async (e: React.SyntheticEvent) => {
    e.preventDefault();

    try {
      const formData = new FormData(e.target as HTMLFormElement);
      const username = formData.get("username") as string;
      const password = formData.get("password") as string;
      const remember = document.querySelector(
        "input[name='remember']"
      ) as HTMLInputElement;

      if (!username || !password) {
        // 用户名不能为空
        if (!username) {
          setIsError((prev) => ({
            ...prev,
            username: true,
          }));
        }
        // 密码不能为空
        if (!password) {
          setIsError((prev) => ({
            ...prev,
            password: true,
          }));
        }
        return;
      }

      const params = {
        username,
        password,
      };

      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userLogin,
        params
      );

      if (res?.data?.code === 1) {
        // Comic Statistics
        // comicStatistics(API_ENDPOINTS.statisticsLogin, {}).then(() => {});

        toast.success(t("user.loginSuccess"));
        handleSetUserInfo(res?.data?.data, remember?.checked);

        setIsOpenUserAuthModal({ type: "login", open: false });
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.value) {
      if (e.target.name === "username") {
        setIsError((prev) => ({
          ...prev,
          username: false,
        }));
      } else if (e.target.name === "password") {
        setIsError((prev) => ({
          ...prev,
          password: false,
        }));
      }
    } else {
      if (e.target.name === "username") {
        setIsError((prev) => ({
          ...prev,
          username: true,
        }));
      } else if (e.target.name === "password") {
        setIsError((prev) => ({
          ...prev,
          password: true,
        }));
      }
    }
  };

  return (
    <>
      <Modal
        open={isOpenUserAuthModal.open && isOpenUserAuthModal.type === "login"}
        width={400}
        className="bg-white rounded-xl"
      >
        <div className="relative">
          <div className="w-6 h-6 absolute top-4 right-4 cursor-pointer rounded-full bg-black/70 flex items-center justify-center">
            <img
              className="w-[10px] h-[10px]"
              src={`${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/icon-close.svg`}
              alt="close"
              onClick={() =>
                setIsOpenUserAuthModal({ type: "login", open: false })
              }
            />
          </div>
          {/* Header Image */}
          <div>
            <img
              className="w-full rounded-t-xl"
              src={`${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/login-header.png`}
              alt="login"
            />
          </div>
          {/* 登录表单 */}
          <div className="px-4 py-6">
            {/* 登录 */}
            <div>
              <h3 className="text-[28px] font-bold my-1">{t("user.login")}</h3>
              <p className="text-sm text-[#969696]">
                {t("user.pleaseEnterYourAccountInformationToContinue")}
              </p>
            </div>
            <form onSubmit={handleSubmit} className="w-full mt-4">
              <Input
                label={t("user.username")}
                name="username"
                placeholder={t("user.usernamePlaceholder")}
                type="text"
                className={`shadow mb-2 ${isError.username ? "border-[1.5px] border-red-500" : ""
                  }`}
                icon={
                  <img
                    className="w-6 h-6"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-user-outline-black.svg`}
                    alt="left"
                  />
                }
                onChange={handleChange}
              />
              {isError.username && (
                <p className="text-red-500 text-sm">
                  {t("common.thisFieldIsRequired")}
                </p>
              )}
              <Input
                label={t("user.password")}
                name="password"
                placeholder={t("user.passwordPlaceholder")}
                type={showPassword ? "text" : "password"}
                className={`shadow mb-2 ${isError.password ? "border-[1.5px] border-red-500" : ""
                  }`}
                icon={
                  <img
                    className="w-6 h-6"
                    src={`${import.meta.env.VITE_INDEX_DOMAIN
                      }/assets/images/icon-lock-outline-black.svg`}
                    alt="lock"
                  />
                }
                addonAfterIcon={
                  <img
                    className="w-6 h-6"
                    src={
                      !showPassword
                        ? `${import.meta.env.VITE_INDEX_DOMAIN
                        }/assets/images/icon-hide-eye-black.svg`
                        : `${import.meta.env.VITE_INDEX_DOMAIN
                        }/assets/images/icon-show-eye-black.svg`
                    }
                    alt="left"
                    onClick={() => setShowPassword((prev) => !prev)}
                  />
                }
                onChange={handleChange}
              />
              {isError.password && (
                <p className="text-red-500 text-sm">
                  {t("common.thisFieldIsRequired")}
                </p>
              )}
              {/* 记住密码 */}
              <div className="flex justify-end mt-4">
                <div className="flex items-center gap-1 flex-1">
                  <label htmlFor="remember" className="flex items-center gap-1 cursor-pointer">
                    <input
                      type="checkbox"
                      id="remember"
                      className="hidden peer"
                      name="remember"
                    />
                    <div className="w-4 h-4 border border-greyscale-300 rounded flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary">
                      <img
                        className="w-3 h-3"
                        src={`${import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-check-white.svg`}
                        alt="check"
                      />
                    </div>
                    <span className="text-sm text-greyscale-600">
                      {t("user.rememberPassword")}
                    </span>
                  </label>
                </div>
                <p
                  className="text-sm text-primary underline cursor-pointer"
                  onClick={() => {
                    window.open(config?.customer_url || "", "_blank");
                  }}
                >
                  {t("user.forgetPassword")}
                </p>
              </div>
              <button
                type="submit"
                className="bg-primary text-white px-10 py-2 rounded-xl w-full cursor-pointer mt-6"
              >
                {t("user.login")}
              </button>
              <div className="flex justify-center mt-4">
                <p className="text-sm text-greyscale-900">
                  {t("user.noAccount")}
                  <span
                    className="text-primary ml-1 underline cursor-pointer"
                    onClick={() =>
                      setIsOpenUserAuthModal({ type: "register", open: true })
                    }
                  >
                    {t("user.registerNow")}
                  </span>
                </p>
              </div>
            </form>
          </div>
        </div>
      </Modal>
      {/* <ToastContainer
        position="top-center"
        theme="colored"
        autoClose={1500}
        hideProgressBar={true}
        pauseOnHover
        className="toast_notification"
        transition={Slide}
        closeButton={false}
      /> */}
    </>
  );
};

export default LoginModal;
