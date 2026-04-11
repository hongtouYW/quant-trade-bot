import { useNavigate } from "react-router";
// import Input from "../../components/Input";
import { useUser } from "../../contexts/user.context";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import type { APIResponseType } from "../../api/type";
import { http } from "../../api";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import { toast } from "react-toastify";
import UserWrapper from "../../components/UserWrapper";
import { isMobile } from "../../utils/utils";
import Input from "../../components/Input";

const EditPasswordContent = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { userInfo } = useUser();

  const [showPassword, setShowPassword] = useState(false);
  const [isError, setIsError] = useState({
    old_password: false,
    password: false,
    repassword: false,
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setIsError((prev) => ({
      ...prev,
      [e.target.name]: e.target.value ? false : true,
    }));
  };

  const handleSubmit = async (e: React.SyntheticEvent) => {
    e.preventDefault();
    const formData = new FormData(e.target as HTMLFormElement);
    const data = Object.fromEntries(formData);
    // console.log("data", data);

    if (!data.old_password) {
      setIsError((prev) => ({
        ...prev,
        old_password: true,
      }));
    }

    if (!data.password) {
      setIsError((prev) => ({
        ...prev,
        password: true,
      }));
    }

    if (!data.repassword) {
      setIsError((prev) => ({
        ...prev,
        repassword: true,
      }));
    }

    if (!data.old_password || !data.password || !data.repassword) {
      return;
    }

    try {
      const params = {
        token: userInfo?.token,
        old_password: data.old_password || "",
        password: data.password || "",
        repassword: data.repassword || "",
      };

      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userChangePassword,
        params
      );

      if (res?.data?.code === 1) {
        toast.success(res?.data?.msg);
        navigate("/user/account");
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (error) {
      console.error("error", error);
    }
  };

  return (
    <div className="lg:p-6">
      {/* header */}
      {isMobile() ? (
        <div className="flex items-center gap-4 px-4 py-4 w-full text-greyscale-900 fixed top-0 bg-white h-12">
          <img
            src="/assets/images/icon-cheveron-left.svg"
            alt="arrow-left"
            onClick={() => navigate("/user/account")}
            className="w-6 h-6 cursor-pointer"
          />
          <p className="font-semibold w-full text-center">{t("user.changeLoginPassword")}</p>
        </div>
      ) : (
        <div className="flex items-center gap-4">
          <img
            src="/assets/images/icon-cheveron-left.svg"
            alt="arrow-left"
            onClick={() => navigate("/user/account")}
            className="w-6 h-6 cursor-pointer"
          />
          <p className="text-2xl font-bold">{t("user.changeLoginPassword")}</p>
        </div>
      )}

      <div className="p-4 pb-10 lg:pt-6 lg:px-0 lg:w-[500px]">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          {/* Current Password */}
          <div>
            <Input
              label={t("user.currentPassword")}
              name="old_password"
              placeholder={t("user.currentPasswordPlaceholder")}
              type={showPassword ? "text" : "password"}
              className={`mb-2 ${isError.old_password ? "border-[1.5px] border-red-500" : ""
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
                  alt="view-password"
                  onClick={() => setShowPassword((prev) => !prev)}
                />
              }
              onChange={handleChange}
            />
            {isError.old_password && (
              <p className="text-red-500 text-sm">
                {t("common.thisFieldIsRequired")}
              </p>
            )}
          </div>

          {/* New Password */}
          <div>
            <Input
              label={t("user.newPassword")}
              name="password"
              placeholder={t("user.newPasswordPlaceholder")}
              type={showPassword ? "text" : "password"}
              className={`mb-2 ${isError.password ? "border-[1.5px] border-red-500" : ""
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
                  alt="view-password"
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
          </div>

          {/* Confirm New Password */}
          <div>
            <Input
              label={t("user.confirmNewPassword")}
              name="repassword"
              placeholder={t("user.confirmNewPasswordPlaceholder")}
              type={showPassword ? "text" : "password"}
              className={`mb-2 ${isError.repassword ? "border-[1.5px] border-red-500" : ""
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
                  alt="view-password"
                  onClick={() => setShowPassword((prev) => !prev)}
                />
              }
              onChange={handleChange}
            />
            {isError.repassword && (
              <p className="text-red-500 text-sm">
                {t("common.thisFieldIsRequired")}
              </p>
            )}
          </div>

          <div className="px-4 text-greyscale-600 text-sm">
            <ul className="list-disc flex flex-col gap-[6px]">
              <li>8 characters or more.</li>
              <li>
                Include 2 types: letters, number, or symbols.
              </li>
              <li>The passwords must be the same.</li>
            </ul>
          </div>

          <button
            type="submit"
            className="bg-primary text-white px-10 py-2 rounded-xl w-full cursor-pointer mt-2"
          >
            {t("common.save")}
          </button>
        </form>
      </div>
    </div>
  );
};

export default function EditPassword() {
  return isMobile() ? (
    <EditPasswordContent />
  ) : (
    <UserWrapper>
      <EditPasswordContent />
    </UserWrapper>
  )
};
