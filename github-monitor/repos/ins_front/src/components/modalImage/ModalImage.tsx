import { FC } from "react";
import { createPortal } from "react-dom";

interface IModalImage {
  children: React.ReactNode;
  open?: any;
  dialogName?: string;
  className?: string;
}

const ModalImage: FC<IModalImage> = (props) => {
  return createPortal(
    <div className={props.open === true ? "show" : "hide"}>
      <div className={props.className}>{props.children}</div>
    </div>,
    document.getElementById(props.dialogName || "dialog")!
  );
};
export default ModalImage;
