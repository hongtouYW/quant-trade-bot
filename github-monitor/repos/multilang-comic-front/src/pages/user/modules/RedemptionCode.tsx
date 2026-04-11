import { toast } from "react-toastify";
import { http } from "../../../api";
import { API_ENDPOINTS } from "../../../api/api-endpoint";
import type { APIResponseType } from "../../../api/type";
import Input from "../../../components/Input";
import { useUser } from "../../../contexts/user.context";
import { useTranslation } from "react-i18next";
import { isMobile, systemLanguage } from "../../../utils/utils";

const RedemptionCode = () => {
  const { t } = useTranslation();
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
    <div className="max-xs:w-full pt-4 max-xs:pt-0">
      {/* 使用兑换码 */}
      <div className="max-xs:-ml-2 max-xs:-mr-2">
        <img
          src={`${
            import.meta.env.VITE_INDEX_DOMAIN
          }/assets/images/redeem-code-bg-${isMobile() ? "mobile" : "desktop"}-${lang}.png`}
          alt="redeem-code"
          className="rounded-t-[10px] max-xs:rounded-none"
        />
      </div>
      {/* <h4 className="font-semibold my-6 max-xs:mt-4 max-xs:mb-2">
        {t("user.redeemCode")}
      </h4> */}
      <div className="bg-black max-xs:-mx-2 rounded-b-[10px] max-xs:rounded-none">
        <div className="flex items-center justify-between gap-2 max-xs:w-full w-1/2">
          <form onSubmit={handleSubmit} className="w-full">
            <div className="flex gap-2 p-[10px] max-xs:px-4 max-xs:py-2">
              {/* Input */}
              <div className="flex-1 min-w-0">
                <Input
                  name="code"
                  placeholder={t("user.inputRedeemCode")}
                  type="text"
                  className="h-10 w-full"
                />
              </div>

              {/* Button */}
              <div className="flex-shrink-0">
                <button
                  type="submit"
                  className="bg-primary text-white px-4 py-2 rounded-lg cursor-pointer h-10"
                >
                  {t("user.confirmRedeem")}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      {/* 使用说明 */}
      <div>
        <h4 className="font-semibold mb-2 mt-6">
          {t("user.usageInstructions")}
        </h4>
        <ul>
          <li>
            <span className="text-sm text-greyscale-600">
              {t("user.usageInstructions1")}
            </span>
          </li>
          <li>
            <span className="text-sm text-greyscale-600">
              {t("user.usageInstructions2")}
            </span>
          </li>
        </ul>
      </div>
    </div>
  );
};

export default RedemptionCode;
