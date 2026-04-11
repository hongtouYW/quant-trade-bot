import React, { useState } from "react";
import { useUser } from "../../contexts/user.context";
import Modal from "../Modal";
import { toast } from "react-toastify";
import Input from "../Input";
import { http } from "../../api";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import type { APIResponseType } from "../../api/type";
import { useTranslation } from "react-i18next";
// import { NavLink } from "react-router";
import Cookies from "universal-cookie";
import { COOKIE_COMIC_STATISTICS } from "../../utils/constant";
// import { comicStatistics } from "../../utils/plugin/comicStatistics";
const cookies = new Cookies();

const RegisterModal = () => {
  const { t } = useTranslation();
  const { isOpenUserAuthModal, setIsOpenUserAuthModal, handleSetUserInfo } =
    useUser();
  const [showPassword, setShowPassword] = useState(false);
  const [isError, setIsError] = useState({
    username: false,
    password: false,
    confirmPassword: false,
    consent: false,
  });

  const handleSubmit = async (e: React.SyntheticEvent) => {
    e.preventDefault();

    try {
      const formData = new FormData(e.target as HTMLFormElement);
      const username = formData.get("username") as string;
      const password = formData.get("password") as string;
      const confirmPassword = formData.get("confirmPassword") as string;
      const consent = formData.get("consent");

      if (!username || !password || !consent || !confirmPassword) {
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
        // 确认密码不能为空
        if (!confirmPassword) {
          setIsError((prev) => ({
            ...prev,
            confirmPassword: true,
          }));
        }
        // 同意协议
        if (!consent) {
          setIsError((prev) => ({
            ...prev,
            consent: true,
          }));
        }
        return;
      }
      const query = new URLSearchParams(window.location.search);
      const queryChannel = query.get("channel");
      const cookiesChannel = cookies.get(COOKIE_COMIC_STATISTICS);

      const channel = queryChannel || cookiesChannel;

      const params = {
        username,
        password,
        repassword: confirmPassword,
        channel_name: channel,
      };

      if (!channel) {
        delete params.channel_name;
      }
      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userRegister,
        params
      );

      if (res?.data?.code === 1) {
        // Comic Statistics
        // comicStatistics(API_ENDPOINTS.statisticsRegister, {}).then(() => {});

        toast.success(t("user.registerSuccess"));
        handleSetUserInfo(res?.data?.data);

        setIsOpenUserAuthModal({ type: "register", open: false });
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.name === "consent") {
      return setIsError((prev) => {
        if (!e.target.checked) {
          return {
            ...prev,
            consent: true,
          }
        }

        return {
          ...prev,
          consent: false,
        }
      });
    }

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
      } else if (e.target.name === "confirmPassword") {
        setIsError((prev) => ({
          ...prev,
          confirmPassword: false,
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
      } else if (e.target.name === "confirmPassword") {
        setIsError((prev) => ({
          ...prev,
          confirmPassword: true,
        }));
      }
    }
  };

  return (
    <>
      <Modal
        open={
          isOpenUserAuthModal.open && isOpenUserAuthModal.type === "register"
        }
        width={400}
        className="bg-white rounded-xl"
      >
        <div className="relative">
          <div className="w-5 h-5 absolute top-4 right-4 cursor-pointer rounded-full bg-black/70 flex items-center justify-center">
            <img
              className="w-[10px] h-[10px]"
              src={`${
                import.meta.env.VITE_INDEX_DOMAIN
              }/assets/images/icon-close.svg`}
              alt="close"
              onClick={() =>
                setIsOpenUserAuthModal({ type: "register", open: false })
              }
            />
          </div>
          {/* Header Image */}
          <div>
            <img
              className="w-full rounded-t-xl"
              src={`${
                import.meta.env.VITE_INDEX_DOMAIN
              }/assets/images/register-header.png`}
              alt="register"
            />
          </div>
          {/* 注册表单 */}
          <div className="px-4 py-6">
            {/* 登录 */}
            <div>
              <h3 className="text-[28px] font-bold my-1">
                {t("user.register")}
              </h3>
            </div>
            <form onSubmit={handleSubmit} className="w-full mt-4">
              <Input
                label={t("user.username")}
                name="username"
                placeholder={t("user.usernamePlaceholder")}
                type="text"
                className={`shadow mb-2 ${
                  isError.username ? "border-[1.5px] border-red-500" : ""
                }`}
                icon={
                  <img
                    className="w-5 h-[18px]"
                    src={`${
                      import.meta.env.VITE_INDEX_DOMAIN
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
                className={`shadow mb-2 ${
                  isError.password ? "border-[1.5px] border-red-500" : ""
                }`}
                icon={
                  <img
                    className="w-5 h-[18px]"
                    src={`${
                      import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-lock-outline-black.svg`}
                    alt="lock"
                  />
                }
                addonAfterIcon={
                  <img
                    className="w-5 h-[18px]"
                    src={
                      !showPassword
                        ? `${
                            import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-hide-eye-black.svg`
                        : `${
                            import.meta.env.VITE_INDEX_DOMAIN
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
              <Input
                label={t("user.confirmPassword")}
                name="confirmPassword"
                placeholder={t("user.confirmPasswordPlaceholder")}
                type={showPassword ? "text" : "password"}
                className={`shadow mb-2 ${
                  isError.confirmPassword ? "border-[1.5px] border-red-500" : ""
                }`}
                icon={
                  <img
                    className="w-5 h-[18px]"
                    src={`${
                      import.meta.env.VITE_INDEX_DOMAIN
                    }/assets/images/icon-lock-outline-black.svg`}
                    alt="lock"
                  />
                }
                addonAfterIcon={
                  <img
                    className="w-5 h-[18px]"
                    src={
                      !showPassword
                        ? `${
                            import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-hide-eye-black.svg`
                        : `${
                            import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-show-eye-black.svg`
                    }
                    alt="left"
                    onClick={() => setShowPassword((prev) => !prev)}
                  />
                }
                onChange={handleChange}
              />
              {isError.confirmPassword && (
                <p className="text-red-500 text-sm">
                  {t("common.thisFieldIsRequired")}
                </p>
              )}
              {/* 记住密码 */}
              <div className="flex justify-end mt-4">
                <div className="flex items-center gap-1 flex-1">
                  <label
                    htmlFor="consent"
                    className="flex items-center gap-1 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      id="consent"
                      name="consent"
                      className="hidden peer"
                      onChange={handleChange}
                    />
                    <div className="w-4 h-4 border border-greyscale-300 rounded flex items-center justify-center peer-checked:bg-primary peer-checked:border-primary">
                      <img
                        className="w-3 h-3"
                        src={`${import.meta.env.VITE_INDEX_DOMAIN
                          }/assets/images/icon-check-white.svg`}
                        alt="check"
                      />
                    </div>
                    <span className="inline-block text-sm text-greyscale-900">{t("user.iHaveReadAndAgree")}&nbsp;</span>
                    <a
                      href="/terms-services"
                      className="text-primary underline inline-block"
                      target="_blank"
                    >
                      {t("termsAndServices.serviceTerms")}
                    </a>
                  </label>
                </div>
              </div>
              {/* {isError.consent && (
                <p className="text-red-500 text-sm">
                  {t("common.thisFieldIsRequired")}
                </p>
              )} */}
              <button
                type="submit"
                className="bg-primary text-white px-10 py-2 rounded-lg w-full cursor-pointer mt-6"
              >
                {t("user.register")}
              </button>
              <div className="flex justify-center mt-4">
                <p className="text-sm text-greyscale-900">
                  {t("user.hasAccount")}
                  <span
                    className="text-primary ml-1 underline"
                    onClick={() =>
                      setIsOpenUserAuthModal({ type: "login", open: true })
                    }
                  >
                    {t("user.clickLogin")}
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

export default RegisterModal;
