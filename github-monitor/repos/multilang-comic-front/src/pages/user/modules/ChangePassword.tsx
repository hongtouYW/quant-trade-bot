// import React, { useState } from "react";
// import Input from "../../../components/Input";
import { useTranslation } from "react-i18next";
// import { useUser } from "../../../contexts/user.context";
// import type { APIResponseType } from "../../../api/type";
// import { http } from "../../../api";
// import { API_ENDPOINTS } from "../../../api/api-endpoint";
// import { toast } from "react-toastify";
import { useNavigate } from "react-router";

const ChangePassword = () => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  // const { userInfo } = useUser();

  // const [showPassword, setShowPassword] = useState(false);
  // const [isEditMode, setIsEditMode] = useState<boolean>(false);

  // const [isError, setIsError] = useState({
  //   old_password: false,
  //   password: false,
  //   repassword: false,
  // });

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();
  //   const formData = new FormData(e.target as HTMLFormElement);
  //   const data = Object.fromEntries(formData);
  //   console.log("data", data);

  //   if (!data.old_password) {
  //     setIsError((prev) => ({
  //       ...prev,
  //       old_password: true,
  //     }));
  //   }

  //   if (!data.password) {
  //     setIsError((prev) => ({
  //       ...prev,
  //       password: true,
  //     }));
  //   }

  //   if (!data.repassword) {
  //     setIsError((prev) => ({
  //       ...prev,
  //       repassword: true,
  //     }));
  //   }

  //   if (!data.old_password || !data.password || !data.repassword) {
  //     return;
  //   }

  //   try {
  //     const params = {
  //       token: userInfo?.token,
  //       old_password: data.old_password || "",
  //       password: data.password || "",
  //       repassword: data.repassword || "",
  //     };

  //     const res = await http.post<APIResponseType>(
  //       API_ENDPOINTS.userChangePassword,
  //       params
  //     );

  //     if (res?.data?.code === 1) {
  //       toast.success(res?.data?.msg);
  //       setIsEditMode(false);
  //     } else {
  //       toast.error(res?.data?.msg);
  //     }
  //   } catch (error) {
  //     console.log("error", error);
  //   }
  // };

  // const handleClearHistory = async () => {
  //   try {
  //     const params = {
  //       token: userInfo?.token,
  //     };
  //     const res = await http.post<APIResponseType>(
  //       API_ENDPOINTS.userClearHistory,
  //       params
  //     );

  //     if (res?.data?.code === 1) {
  //       console.log("res", res);
  //       toast.success(t("user.clearSuccess"));
  //     }
  //   } catch (error) {
  //     console.log("error", error);
  //   }
  // };

  // console.log("showPassword", showPassword);
  return (
    <div className="flex items-center justify-between gap-2 mt-4 max-xs:px-2 lg:mt-6">
      <div
        className="flex items-center justify-between gap-2 w-full text-greyscale-900 cursor-pointer lg:justify-start lg:py-3.5"
        onClick={() => navigate("/user/edit-password")}
      >
        <p>{t("user.changeLoginPassword")}</p>
        <img src="/assets/images/icon-chevron-right-black.svg" alt="right" />
      </div>
      {/* <form
        onSubmit={handleSubmit}
        className="w-full flex items-start justify-between gap-2 max-xs:relative"
      >
        <div className="w-1/2 flex flex-col gap-4 max-xs:w-full max-xs:relative max-xs:pt-3">
          <div>
            {isEditMode ? (
              <>
                <Input
                  label={t("user.currentPassword")}
                  name="old_password"
                  placeholder={t("user.currentPassword")}
                  type={showPassword ? "text" : "password"}
                  className={`mb-2 w-full ${isError.old_password ? "border-[1.5px] border-red-500" : ""}`}
                  addonAfterIcon={
                    <img
                      className="w-5 h-[18px] max-xs:min-w-5"
                      src={
                        !showPassword
                          ? `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-hide-eye.svg`
                          : `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-show-eye.svg`
                      }
                      alt="left"
                      onClick={() => setShowPassword((prev) => !prev)}
                    />
                  }
                />
                {isError.old_password && (
                  <p className="text-red-500 text-sm">
                    {t("common.thisFieldIsRequired")}
                  </p>
                )}
                <Input
                  label={t("user.newPassword")}
                  name="password"
                  placeholder={t("user.newPassword")}
                  type={showPassword ? "text" : "password"}
                  className={`mb-2 w-full ${isError.password ? "border-[1.5px] border-red-500" : ""}`}
                  addonAfterIcon={
                    <img
                      className="w-5 h-[18px] max-xs:min-w-5"
                      src={
                        !showPassword
                          ? `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-hide-eye.svg`
                          : `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-show-eye.svg`
                      }
                      alt="left"
                      onClick={() => setShowPassword((prev) => !prev)}
                    />
                  }
                />
                {isError.password && (
                  <p className="text-red-500 text-sm">
                    {t("common.thisFieldIsRequired")}
                  </p>
                )}
                <Input
                  label={t("user.confirmPassword")}
                  name="repassword"
                  placeholder={t("user.confirmPassword")}
                  type={showPassword ? "text" : "password"}
                  className={`mb-2 w-full ${isError.repassword ? "border-[1.5px] border-red-500" : ""}`}
                  addonAfterIcon={
                    <img
                      className="w-5 h-[18px] max-xs:min-w-5"
                      src={
                        !showPassword
                          ? `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-hide-eye.svg`
                          : `${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-show-eye.svg`
                      }
                      alt="left"
                      onClick={() => setShowPassword((prev) => !prev)}
                    />
                  }
                />
                {isError.repassword && (
                  <p className="text-red-500 text-sm">
                    {t("common.thisFieldIsRequired")}
                  </p>
                )}
              </>
            ) : (
              <>
                <label className="text-sm text-greyscale-600 font-medium mb-1">
                  {t("user.accountPassword")}
                </label>
                <p className="text-sm font-medium">********</p>
              </>
            )}
          </div>
        </div>
        {!isEditMode && (
          <button
            type="button"
            onClick={() => setIsEditMode(true)}
            className="text-primary-dark flex items-center gap-1 border-2 border-primary-dark rounded-full px-4 py-[5px] xs:border-none cursor-pointer max-xs:border-none max-xs:px-2 max-xs:text-sm"
          >
            <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-edit.svg`} alt="edit" />
            <p>{t("user.amend")}</p>
          </button>
        )}
        {isEditMode && (
          <button
            type="submit"
            // onClick={() => setIsEditMode(true)}
            className="text-primary-dark flex items-center gap-1 border-2 border-primary-dark rounded-full px-4 py-[5px] xs:border-none cursor-pointer max-xs:border-none max-xs:px-2 max-xs:text-sm max-xs:absolute max-xs:right-0 max-xs:-top-1"
          >
            <img src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-tick.svg`} alt="edit" />
            <p>{t("user.confirmChange")}</p>
          </button>
        )}
      </form> */}
    </div>
  );
};

export default ChangePassword;
