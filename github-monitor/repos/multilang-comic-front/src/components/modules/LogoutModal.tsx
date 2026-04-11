import { useNavigate } from "react-router";
import { useUser } from "../../contexts/user.context";
import Modal from "../Modal";
import { useTranslation } from "react-i18next";

// interface LogoutModalProps {
//   onConfirm: () => void;
//   title?: string;
//   content?: string;
//   cancelText?: string;
//   confirmText?: string;
// }

interface LogoutModalProps {
  onConfirm?: () => void;
}

const LogoutModal = ({ onConfirm }: LogoutModalProps) => {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { isOpenUserAuthModal, setIsOpenUserAuthModal, handleLogout } = useUser();

  return (
    <Modal
      open={isOpenUserAuthModal.open && isOpenUserAuthModal.type === "logout"}
      width={400}
    >
      <div className="relative">
        <div className="w-6 h-6 absolute top-4 right-4 cursor-pointer rounded-full bg-black/70 flex items-center justify-center">
          <img
            className="w-[10px] h-[10px]"
            src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/icon-close.svg`}
            alt="close"
            onClick={() =>
              setIsOpenUserAuthModal({ type: "logout", open: false })
            }
          />
        </div>
        {/* Header Image */}
        <div>
          <img
            className="w-full rounded-t-xl"
            src={`${import.meta.env.VITE_INDEX_DOMAIN}/assets/images/logout-header.png`}
            alt="logout"
          />
        </div>
      </div>
      <div className="flex flex-col items-center gap-4 bg-white rounded-b-xl py-6 px-4 -mt-[1px]">
        <h4 className="font-semibold text-center text-greyscale-900 lg:text-xl">
          {t("user.confirmLogout")}
        </h4>
        <p className="text-sm text-greyscale-600 text-center lg:text-base">
          {t("user.confirmLogout1")}
        </p>
        <div className="flex items-center gap-2 w-full">
          <button
            className="border border-primary text-primary px-10 py-2 rounded-xl w-full cursor-pointer"
            onClick={() =>
              setIsOpenUserAuthModal({ type: "logout", open: false })
            }
          >
            {t("user.cancel")}
          </button>
          <button
            className="bg-primary text-white px-10 py-2 rounded-xl w-full cursor-pointer"
            onClick={() => {
              setIsOpenUserAuthModal({ type: "logout", open: false });
              handleLogout();
              onConfirm?.();
              navigate("/");
            }}
          >
            {t("user.confirm")}
          </button>
        </div>
      </div>
    </Modal>
  );
};

export default LogoutModal;
