import { toast } from "react-toastify";
import { http } from "../../api";
import { API_ENDPOINTS } from "../../api/api-endpoint";
import type { APIResponseType } from "../../api/type";
import Input from "../../components/Input";
import { useUser } from "../../contexts/user.context";
import { useTranslation } from "react-i18next";
import { isMobile, systemLanguage } from "../../utils/utils";
import UserWrapper from "../../components/UserWrapper";
import { useNavigate } from "react-router";

const Redeem = () => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { userInfo, refreshUserInfo } = useUser();
  const lang = systemLanguage();

  const handleSubmit = async (e: React.SyntheticEvent) => {
    e.preventDefault();
    const formData = new FormData(e.target as HTMLFormElement);
    const data = Object.fromEntries(formData);

    if (!data.code) {
      return;
    }
    try {
      const params = {
        code: data.code,
        token: userInfo?.token,
      };

      const res = await http.post<APIResponseType>(
        API_ENDPOINTS.userRedeemCode,
        params
      );

      if (res?.data?.code === 1) {
        toast.success(res?.data?.msg);
        refreshUserInfo();
      } else {
        toast.error(res?.data?.msg);
      }
    } catch (err) {
      console.error("err", err);
    }
  };

  return (
    <div className="h-full">
      <UserWrapper>
        <div className="flex items-center p-4 w-full text-greyscale-900 fixed top-0 left-0 right-0 bg-white h-14 lg:hidden">
          <img
            src="/assets/images/icon-cheveron-left.svg"
            alt="arrow-left"
            className="w-6 h-6 cursor-pointer"
            onClick={() => navigate("/")}
          />
          <p className="font-semibold text-center w-full">{t("user.membershipRedemption")}</p>
        </div>

        <div className="lg:m-6 lg:rounded-2xl overflow-hidden">
          <div className="">
            <img
              src={`${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/redeem-code-bg-${isMobile() ? "mobile" : "desktop"
                }-${lang}.png`}
              alt="redeem-code"
              className="w-full"
            />
          </div>

          <div className="bg-black p-4 lg:p-6">
            <div className="flex items-center justify-between gap-2 w-full">
              <form onSubmit={handleSubmit} className="w-full">
                <div className="flex items-center gap-4">
                  {/* Input */}
                  <div className="flex-1 min-w-0">
                    <Input
                      name="code"
                      placeholder={t("user.inputRedeemCode")}
                      type="text"
                      className="h-12 w-full lg:h-14 lg:text-base"
                    />
                  </div>

                  {/* Button */}
                  <div className="flex-shrink-0">
                    <button
                      type="submit"
                      className="bg-primary text-white px-4 py-3 rounded-xl cursor-pointer leading-[20px] h-12 lg:px-6"
                    >
                      {t("user.membershipRedemption")}
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        {/* 使用说明 */}
        <div className="m-4 mb-10 lg:m-6">
          <h4 className="font-semibold text-greyscale-900 lg:text-xl">
            {t("user.usageInstructions")}
          </h4>
          <ul className="mt-4">
            <li>
              <span className="text-sm text-greyscale-600">
                {t("user.usageInstructions1")}
              </span>
            </li>
            <li className="mt-4">
              <span className="text-sm text-greyscale-600">
                {t("user.usageInstructions2")}
              </span>
            </li>
          </ul>
        </div>
      </UserWrapper>
    </div>
  );
};

export default Redeem;
