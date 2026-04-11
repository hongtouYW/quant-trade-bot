import { useTranslation } from "react-i18next";
import Modal from "../../components/Modal";

const TopupConfirmationModal = ({
  open,
  payUrl,
  onClose,
}: {
  open: boolean;
  payUrl: string;
  onClose: () => void;
  // onConfirm: () => void;
}) => {
  const { t } = useTranslation();
  return (
    <Modal open={open} width={400} className="bg-white rounded-xl">
      <div className="relative p-4">
        <div className="flex justify-between pb-4">
          <p className="text-center text-lg font-medium w-full leading-[20px]">
            {t("common.warmReminder")}
          </p>
          <span className="text-[22px] font-medium leading-[20px] cursor-pointer" onClick={onClose}>
            &times;
          </span>
        </div>

        <p className="text-center">{t("common.pleaseCompletePayment")}</p>
        <button
          className="bg-primary text-white px-10 py-2 rounded-lg w-full cursor-pointer mt-4"
          onClick={() => {
            window.open(payUrl, "_blank");
            onClose();
          }}
        >
          {t("common.immediatelyJump")}
        </button>
      </div>
    </Modal>
  );
};

export default TopupConfirmationModal;
