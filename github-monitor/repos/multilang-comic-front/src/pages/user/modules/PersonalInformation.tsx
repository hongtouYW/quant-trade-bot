// import { useState } from "react";
// import Input from "../../../components/Input";
import { useUser } from "../../../contexts/user.context";
// import { http } from "../../../api";
// import type { APIResponseType } from "../../../api/type";
// import { API_ENDPOINTS } from "../../../api/api-endpoint";
// import { toast } from "react-toastify";
import { useTranslation } from "react-i18next";
// import { useNavigate } from "react-router";

function PersonalInformation() {
  const { t } = useTranslation();
  // const navigate = useNavigate();
  // const { userInfo, refreshUserInfo } = useUser();
  const { userInfo} = useUser();

  // const [isEditMode, setIsEditMode] = useState<boolean>(false);
  // const [isError, setIsError] = useState({
  //   // nickname: false,
  //   email: false,
  // });

  // const handleSubmit = async (e: React.SyntheticEvent) => {
  //   e.preventDefault();

  //   const formData = new FormData(e.target as HTMLFormElement);
  //   const data = Object.fromEntries(formData);

  //   if (!data.email) {
  //     // if (!data.nickname) {
  //     //   setIsError((prev) => ({
  //     //     ...prev,
  //     //     // nickname: true,
  //     //   }));
  //     // }
  //     if (!data.email) {
  //       setIsError((prev) => ({
  //         ...prev,
  //         email: true,
  //       }));
  //     }
  //     return;
  //   }

  //   try {
  //     const params = {
  //       token: userInfo.token,
  //       // nickname: data.nickname || "",
  //       email: data.email || "",
  //     };
  //     const res = await http.post<APIResponseType>(
  //       API_ENDPOINTS.userChangeInfo,
  //       params
  //     );
  //     if (res?.data?.code === 1) {
  //       refreshUserInfo();
  //       toast.success(res?.data?.msg);
  //       setIsEditMode(false);
  //     } else {
  //       toast.error(res?.data?.msg);
  //     }
  //   } catch (error) {
  //     console.error("error", error);
  //   }

  //   // if (isEditMode) {
  //   // const formData = new FormData(e.target as HTMLFormElement);
  //   //   const data = Object.fromEntries(formData);
  //   //   console.log("data", data);
  //   //   console.log("submit");
  //   //   setIsEditMode(false);
  //   // }
  // };

  // const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
  //   setIsError((prev) => ({
  //     ...prev,
  //     [e.target.name]: e.target.value ? false : true,
  //   }));
  // };

  return (
    <>
      {/* <div className="flex items-center justify-between gap-2 mt-4 max-xs:px-2 max-xs:hidden">
        <form
          onSubmit={handleSubmit}
          className="w-full flex items-start justify-between gap-2"
        >
          <div className="w-1/3 flex flex-col gap-4 max-xs:w-2/3">
            <div>
              <label className="text-sm text-greyscale-600 font-medium mb-1">
                {t("user.name")}
              </label>
              <p className="text-sm font-medium">{userInfo.username || "-"}</p>
            </div>
            <div>
              {isEditMode ? (
                <>
                  <Input
                    label={t("user.email")}
                    name="email"
                    placeholder={t("user.pleaseEnterYourEmailAddress")}
                    type="email"
                    initialValue={userInfo.email}
                    className={`shadow mb-2 ${
                      isError.email ? "border-[1.5px] border-red-500" : ""
                    }`}
                    onChange={handleChange}
                  />
                  {isError.email && (
                    <p className="text-red-500 text-sm">
                      {t("common.thisFieldIsRequired")}
                    </p>
                  )}
                </>
              ) : (
                <>
                  <label className="text-sm text-greyscale-600 font-medium mb-1">
                    {t("user.email")}
                  </label>
                  <p className="text-sm font-medium">{userInfo.email || "-"}</p>
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
              <img src="/assets/images/icon-edit.svg" alt="edit" />
              <p>{t("user.amend")}</p>
            </button>
          )}
          {isEditMode && (
            <button
              type="submit"
              className="text-primary-dark flex items-center gap-1 border-2 border-primary-dark rounded-full px-4 py-[5px] xs:border-none cursor-pointer max-xs:border-none max-xs:px-2 max-xs:text-sm"
            >
              <img src="/assets/images/icon-tick.svg" alt="edit" />
              <p>{t("user.confirmChange")}</p>
            </button>
          )}
        </form>
      </div> */}
      {/* 新样式 - 手机 */}
      <div>
        <div className="flex items-center justify-between py-4 px-2 leading-6 lg:flex-col lg:items-start lg:justify-start lg:p-0 lg:pt-6">
          <p className="lg:text-greyscale-500">{t("user.username")}</p>
          {/* <div className="flex items-center gap-[10px] cursor-pointer" onClick={() => navigate("/user/edit-my-profile")}> */}
          <div className="flex items-center gap-[10px]">
            <p className="text-greyscale-900 font-semibold">{userInfo.username}</p>
            {/* <img src="/assets/images/icon-chevron-right.svg" alt="right" /> */}
          </div>
        </div>

        <div className="flex items-center justify-between py-4 px-2 leading-6 lg:flex-col lg:items-start lg:justify-start lg:p-0 lg:pt-6">
          <p className="lg:text-greyscale-500">{t("user.email")}</p>
          <p className="text-greyscale-900 font-semibold">{userInfo.email}</p>
        </div>
      </div>
    </>
  );
}

export default PersonalInformation;
