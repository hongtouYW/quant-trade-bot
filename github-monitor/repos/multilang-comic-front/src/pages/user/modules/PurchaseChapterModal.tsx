import { useTranslation } from "react-i18next";
import Modal from "../../../components/Modal";
import { useState } from "react";

const PurchaseChapterModal = ({
  open,
  // noAsk = false,
  onClose,
  onConfirm,
  purchaseChapter,
  userInfo,
}: {
  open: boolean;
  noAsk?: boolean;
  onClose: () => void;
  onConfirm: () => void;
  purchaseChapter: any;
  userInfo: any;
}) => {
  const { t } = useTranslation();
  const [checked, setChecked] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.checked;

    setChecked(value);
  };

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
          <p className="leading-[18px] text-greyscale-600 font-normal">
            {purchaseChapter?.score > 0
              ? t("common.remainingTickets1", {
                  coin: userInfo?.score || 0,
                })
              : t("common.remainingTickets", {
                  coin: userInfo?.score || 0,
                })}
          </p>
        </div>
        <div className="flex items-center justify-center gap-[10px] text-white mb-6">
          <button
            className="bg-greyscale-700 w-full py-[14px] leading-4 rounded-full cursor-pointer"
            onClick={onClose}
          >
            {t("common.cancel")}
          </button>
          <button
            className="bg-primary w-full py-[14px] leading-4 rounded-full cursor-pointer"
            onClick={onConfirm}
          >
            {t("common.confirm")}
          </button>
        </div>
        <div className="flex items-center justify-center">
          <label className="flex items-center space-x-3 cursor-pointer ">
            {/* Hidden native radio */}
            <input
              type="checkbox"
              name="is_not_ask"
              value="1"
              onChange={handleChange}
              className="peer hidden"
            />

            {/* Custom circle */}
            <span className="h-6 w-6 rounded-full border-3 border-primary flex items-center justify-center">
              <span
                className={`h-3 w-3 rounded-full bg-primary  peer-checked:block ${
                  checked ? "block" : "hidden"
                }`}
              ></span>
            </span>

            {/* Label */}
            <span className="text-gray-800">{t("common.dontAskAgain")}</span>
          </label>
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
      </div> */}
      {/* <div className="flex flex-col items-center justify-center gap-1 mb-2">
        <img src="/assets/images/ticket.svg" alt="close" onClick={onClose} />
        <p className="text-[20px] font-medium">
          {t("common.thisChapterWillUse")} {purchaseChapter?.score || 0}{" "}
          {t("common.coin")}
        </p>
        <p className="text-lg font-medium">
          {t("common.remainingCoin")}: {userInfo?.score || 0} {t("common.coin")}
        </p>
      </div> */}
      {/* {!noAsk && (
        <div className="flex items-center justify-center gap-2 mt-3 mb-2">
          <input
            type="checkbox"
            className="w-4 h-4 border-2 border-greyscale-300"
            name="is_not_ask"
          />
          <p className="text-lg font-medium">{t("common.doNotAskAgain")}</p>
        </div>
      )}
      <div className="flex items-center justify-center">
        <button
          className="w-3/4 bg-primary text-white py-2 rounded-full my-4 cursor-pointer"
          onClick={onConfirm}
        >
          {t("common.confirmWatch")}
        </button>
      </div> */}
    </Modal>
  );
};

export default PurchaseChapterModal;
