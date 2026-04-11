// import { useNavigate } from "react-router";
import { useTranslation } from "react-i18next";
import { useUser } from "../../contexts/user.context";
import Modal from "../Modal";

export const Confirm18Modal = () => {
  const { t } = useTranslation();
  const { isOpenConfirm18Modal, setIsOpenConfirm18Modal } = useUser();

  const handleConfirm = () => {
    sessionStorage.setItem("age", "true");
    setIsOpenConfirm18Modal(false);
  };

  return (
    <Modal open={isOpenConfirm18Modal} width={400} backdropBlur zIndex="z-60">
      <div className="flex flex-col items-center gap-4 bg-white rounded-2xl py-6 px-4 lg:gap-6">
        <img
          src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/${import.meta.env.VITE_LOGO_URL || "logo-2.png"}`}
          alt="18-modal"
          className="w-[70px]"
        />
        <h4 className="font-semibold text-greyscale-900 lg:text-xl">{t("common.areYou18YearsOld")}</h4>
        <p className="text-sm text-greyscale-600 leading-[18px] text-center lg:text-base lg:leading-6">
          <span>{t("common.confirm18Message")}</span>
          <br />
          <span
            className="text-primary cursor-pointer underline"
            onClick={() =>
              window.open(
                `${window.location.origin}/terms-of-services`,
                "_blank"
              )
            }
          >
            {t("common.termsOfService")}
          </span>
          {t("common.dot")}
        </p>
        <div className="flex items-center gap-4 w-full">
          <button
            className="w-full text-sm border border-primary text-primary font-semibold rounded-2xl px-6 py-3 cursor-pointer"
            onClick={
              () => {
                if (import.meta.env.VITE_APP_REGION === "global") {
                  window.location.href = "https://comictoon.vip/";
                } else {
                  window.location.href = "https://comictoon.vip/";
                }
              }
            }
          >
            {t("common.notOver18")}
          </button>
          <button
            className="w-full text-sm bg-primary text-white font-semibold rounded-2xl px-6 py-3 cursor-pointer"
            onClick={handleConfirm}
          >
            {t("common.imOver18")}
          </button>
        </div>
      </div>
    </Modal>
  );
};
