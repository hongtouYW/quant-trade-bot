import { createPortal } from "react-dom";

interface ModalProps {
  children: React.ReactNode;
  width?: number;
  open: boolean;
  className?: string;
  zIndex?: string;
  backdropBlur?: boolean;
}

const Modal = ({
  children,
  open,
  width = 400,
  className,
  zIndex = 'z-50',
  backdropBlur = false,
}: ModalProps) => {
  if (!open) return null;
  return createPortal(
    <div
      className={`fixed inset-0 bg-black/70 flex items-center justify-center  max-xs:px-4 ${
        backdropBlur ? "backdrop-blur" : ""
      } ${zIndex}`}
    >
      <div className={className} style={{ width: width }}>
        {children}
      </div>
    </div>,
    document.getElementById("modal")!
  );
};

export default Modal;
