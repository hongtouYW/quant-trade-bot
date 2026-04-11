import { useNavigate } from "react-router";
import { useUser } from "../contexts/user.context";
import { useTranslation } from "react-i18next";
import { toFmt } from "../utils/utils";

const VipBanner = ({ vipPrice }: { vipPrice: number }) => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { userInfo } = useUser();
  const currency = import.meta.env.VITE_DEFAULT_CURRENCY;

  return (
    <div className="relative bg-linear-to-r from-[#E9DEF6] to-[#E5E7FC] rounded-lg py-4 px-5 my-5 max-xs:my-0 max-xs:py-[10px]">
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-1 mb-1">
            <img
              className="w-6 h-6"
              src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-vip-badge.svg`}
              alt="vip"
            />
            <p className="font-medium">
              {userInfo.isvip_status === 1
                ? t("user.vipActived")
                : t("user.vipNotActived")}
            </p> 
          </div>
          <p className="text-[#616161] text-sm font-medium max-xs:text-xs">
            {userInfo.isvip_status === 1
              ? t("user.vipExpiredTime") +
                toFmt(parseInt(userInfo.viptime || "0") * 1000 || 0)
              : t("user.enjoyVipPrivilege") + ` ${currency} ${vipPrice} `}
          </p>
        </div>
        {userInfo.isvip_status === 0 && (
          <div>
            <button
              className="text-sm text-white bg-[#424242] px-4 py-[6px] rounded-full flex items-center gap-1 cursor-pointer max-xs:text-xs max-xs:leading-[14px] max-xs:py-2"
              onClick={() => {
                navigate("/user/topup");
              }}
            >
              {t("user.immediatelyOpen")}
              <img
                className="w-4 h-4"
                src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-arrow-circle-right-white.svg`}
                alt="arrow"
              />
            </button>
          </div>
        )}
      </div>
      <img
        className="w-[50px] absolute bottom-0 right-0"
        src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/vip-badge-bg.png`}
        alt="vip"
      />
    </div>
  );
};

export default VipBanner;
