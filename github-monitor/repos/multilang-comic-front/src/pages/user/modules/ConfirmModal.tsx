import Modal from "../../../components/Modal";

interface ConfirmModalProps {
  isOpenConfirmModal: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title?: string;
  content?: string;
  cancelText?: string;
  confirmText?: string;
}

const ConfirmModal = ({
  isOpenConfirmModal,
  onClose,
  onConfirm,
  title = "消息提示",
  content = "确定取消全部？",
  cancelText = "取消",
  confirmText = "确定",
}: ConfirmModalProps) => {
  return (
    <Modal open={isOpenConfirmModal} width={400}>
      <div className="flex flex-col items-center gap-4 bg-white rounded-xl py-6 px-4">
        <h4 className="text-[20px] font-medium">{title}</h4>
        <p className="text-greyscale-600 my-2">{content}</p>
        <div className="flex items-center gap-2 w-[80%]">
          <button
            className="w-full text-sm bg-greyscale-700 text-white rounded-full px-6 py-[10px]"
            onClick={onClose}
          >
            {cancelText}
          </button>
          <button
            className="w-full text-sm bg-primary text-white rounded-full px-6 py-[10px]"
            onClick={onConfirm}
          >
            {confirmText}
          </button>
        </div>
      </div>
    </Modal>
  );
};

export default ConfirmModal;
