import { useTranslation } from "react-i18next";
import { useUser } from "../contexts/user.context";
import { toFmt } from "../utils/utils";
import { useNavigate } from "react-router";

const UserVipPassHeader = ({
  isShowCoin = false,
}: {
  isShowCoin?: boolean;
}) => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  const { userInfo } = useUser();

  return (
    <div className="rounded-xl p-4 bg-greyscale-100">
      <div className="flex justify-between pb-4 border-b border-greyscale-300">
        <div>
          <p className="text-sm text-greyscale-600">{t("common.coin")}:</p>
          <div className="flex items-center gap-1">
            <p className="text-2xl text-[#FE9901] text-bold font-bold">{userInfo?.score}</p>
            <img
              className="w-5 h-5"
              src={`${import.meta.env.VITE_INDEX_DOMAIN
                }/assets/images/icon-coin.svg`}
              alt="coin"
            />
          </div>
        </div>

        <div className="flex items-center gap-[6px]">
          {isShowCoin && (
            <button
              type="button"
              className="text-greyscale-50 px-4 py-3 rounded-xl text-sm font-semibold bg-primary cursor-pointer lg:px-6"
              onClick={() => {
                navigate("/user/topup");
              }}
            >
              {t("user.topup")}
            </button>
          )}
        </div>
      </div>

      <div className="flex pt-4">
        <p className='max-xs:text-sm'>
          <span className="font-semibold">
            {userInfo.isvip_status === 1
              ? t('user.vipPassWillExpiredOn') + ' ' + toFmt(parseInt(userInfo.viptime || '0') * 1000 || 0, "YYYY/MM/DD")
              : t('user.vipNotActivatedYet')}
          </span>
        </p>
      </div>
    </div>
  );
};

export default UserVipPassHeader;
