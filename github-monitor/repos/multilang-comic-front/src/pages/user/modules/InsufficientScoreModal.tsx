import { useTranslation } from "react-i18next";
import Modal from "../../../components/Modal";
import { useNavigate } from "react-router";

const InsufficientScoreModal = ({
  open,
  onClose,
  purchaseChapter,
  userInfo,
}: {
  open: boolean;
  onClose: () => void;

  purchaseChapter: any;
  userInfo: any;
}) => {
  const navigate = useNavigate();
  const { t } = useTranslation();
  return (
    <Modal open={open} width={400} className="bg-white rounded-xl">
      <div className="relative">
        <img
          className="w-5 h-5 absolute top-4 right-4 cursor-pointer"
          src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-close.svg`}
          alt="close"
          onClick={onClose}
        />
        <div>
          <img
            className="w-full rounded-t-xl"
            src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/purchase-header.png`}
            alt="login"
          />
        </div>
      </div>
      <div className="p-6">
        <div className="text-center mb-6">
          <p className="text-xl font-medium mb-4 leading-7">
            {purchaseChapter?.score > 0
              ? t("common.thisEpisodeRequires1", {
                  score: purchaseChapter?.score || 0,
                })
              : t("common.thisEpisodeRequires", {
                  score: purchaseChapter?.score || 0,
                })}
          </p>
          <p className="leading-[18px] text-red-500 font-normal mb-[6px]">
            {purchaseChapter?.score > 0
              ? t("common.remainingTickets1", {
                  coin: userInfo?.score || 0,
                })
              : t("common.remainingTickets", {
                  coin: userInfo?.score || 0,
                })}
          </p>
          <p className="text-greyscale-500 font-normal leading-[18px]">
            ({t("common.youDontHaveEnoughTickets")})
          </p>
        </div>
        <div className="flex flex-col items-center justify-center gap-[10px] text-white">
          <button
            className="bg-primary w-full py-[14px] leading-4 rounded-full cursor-pointer"
            onClick={() => {
              navigate("/user/topup");
            }}
          >
            {t("common.immediatelyRecharge")}
          </button>
          <button
            className="bg-greyscale-700 w-full py-[14px] leading-4 rounded-full cursor-pointer"
            onClick={onClose}
          >
            {t("common.cancel")}
          </button>
        </div>
      </div>
      {/* <div className="relative text-center">
        <p className="text-[20px] font-medium">{t("common.useCoin")}</p>
        <img
          className="w-4 h-4 absolute top-1 right-1 cursor-pointer"
          src="/assets/images/icon-close-dark.svg"
          alt="close"
          onClick={onClose}
        />
      </div>
      <div className="flex flex-col items-center justify-center gap-[6px]">
        <img
          src="/assets/images/icon-warning.svg"
          alt="close"
          onClick={onClose}
          className="my-4"
        />
        <p className="text-[20px] font-medium">
          {t("common.thisChapterWillUse")} {purchaseChapter?.score || 0}
          {t("common.coin")}
        </p>

        <p className="font-medium text-lg text-center">
          {t("common.youCurrentlyHave")}{' '}
          <span className="text-red-500">
            {userInfo?.score || 0} {t("common.coin")}
          </span>
          , {t("common.cannotWatch")}!
        </p>
        <div className="flex items-center gap-2">
          <img
            className="w-5 h-5"
            src="/assets/images/icon-corner-down-right.svg"
            alt="close"
            onClick={onClose}
          />
          <p className="font-medium text-lg text-greyscale-700">
            {t("common.pleaseRechargeToContinue")}
          </p>
        </div>
      </div>
      <div className="flex items-center justify-center flex-col">
        <button
          className="w-3/4 bg-primary text-white py-2 rounded-full mt-4 mb-2 cursor-pointer"
          onClick={() => {
            navigate("/user/topup");
          }}
        >
          {t("common.immediatelyRecharge")}
        </button>
        <p className="text-sm text-greyscale-600 font-medium">
          {t("common.becomeVIPToWatch")}
        </p>
      </div> */}
    </Modal>
  );
};

export default InsufficientScoreModal;
