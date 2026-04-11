import { createPortal } from "react-dom";

import styles from "./Modal.module.css";
// import { useState } from "react";
// import u from "../../utils/utils";
// import { NAVIGATE_BOTTOM, NAVIGATE_CENTER } from "../../utils/constant";
// import Cookies from "universal-cookie";
// import Image from "../Image/Image";

// const cookies = new Cookies();

type ModalProps = {
  headerTitle?: string;
  content?: string;
  // dialogRef: any;
  confirmFn: any;
  confirmText?: string;
  open: boolean;
  setOpenModalError: any;
};

// type ModalImageAdsProps = {
//   contentImg?: string;
//   dialogRef: any;
//   position?: "center" | "bottom";
//   elementId?: "img-dialog-center" | "img-dialog-bottom";
//   targetLink?: string;
// };

const Modal = ({
  children,
  type = "custom",
}: {
  type?: "custom";
  children: React.ReactNode;
}) => {
  if (type === "custom") {
    return createPortal(children, document.getElementById("dialog")!);
  } else {
    return <>{children}</>;
  }
};
const Error = ({
  // dialogRef,
  headerTitle,
  content,
  confirmFn,
  confirmText,
  open,
  setOpenModalError,
}: ModalProps) => {
  const closeDialog = () => {
    setOpenModalError(false);
    // if (dialogRef.current) dialogRef.current.close();
  };

  const handleConfirmFn = () => {
    if (confirmFn) {
      confirmFn();
    }
    closeDialog();
  };

  return createPortal(
    <div className={styles.dialogModal}>
      <div
        // ref={dialogRef}
        className={`${styles.dialogContainer} ${
          open === true ? "show" : "hide"
        }`}
        // open={open}
      >
        <div className={styles.dialogHeader}>
          <div className={styles.dialogHeaderContent}>
            <p className={styles.headerTitle}>{headerTitle}</p>
            <span className={styles.headerClose} onClick={closeDialog}>
              &#x2715;
            </span>
          </div>
        </div>
        <div className="dialogBody">
          <div className={styles.dialogBodyContent}>
            <p>{content}</p>
          </div>
        </div>
        <div className={styles.dialogFooter}>
          <button
            onClick={handleConfirmFn}
            className={styles.dialogFooterConfirmBtn}
          >
            {confirmText ? confirmText : "确定"}
          </button>
        </div>
      </div>
    </div>,
    document.getElementById("alert-dialog")!
  );
};

// const ImageAds = ({
//   dialogRef,
//   contentImg,
//   position = "center",
//   elementId = "img-dialog-center",
//   targetLink,
// }: ModalImageAdsProps) => {
//   const closeDialog = () => {
//     // if (dialogRef.current) dialogRef.current.close();
//     if (dialogRef.current) {
//       if (elementId === "img-dialog-center") {
//         u.setCookies(NAVIGATE_CENTER, "close", 0.5);

//         dialogRef.current.classList.add("dialog-close");
//         dialogRef.current.classList.remove("dialog-open");
//       } else {
//         const navigateBottom = cookies.get(NAVIGATE_BOTTOM);
//         if (!navigateBottom) {
//           u.setCookies(NAVIGATE_BOTTOM, "pending", 0.5);
//           if (targetLink) {
//             window.open(targetLink, "_blank");
//           }
//         } else if (navigateBottom === "pending") {
//           u.setCookies(NAVIGATE_BOTTOM, "close", 0.5);

//           dialogRef.current.classList.add("dialog-close");
//           dialogRef.current.classList.remove("dialog-open");
//         }
//       }
//     }
//   };

//   return createPortal(
//     <div
//       ref={dialogRef}
//       className={`${styles.dialogImgContainer} ${
//         position === "center"
//           ? `${styles.dialogImgContainerCenter}`
//           : `${styles.dialogImgContainerBottom}`
//       }`}
//     >
//       <div
//         className={`${styles.dialogImg} ${
//           position === "center"
//             ? `${styles.dialogImgCenter}`
//             : `${styles.dialogImgBottom}`
//         }`}
//       >
//         <span className={styles.closeDialogImg} onClick={closeDialog}>
//           &times;
//         </span>
//         <a href="#" className={styles.closeDialogLink}>
//           {/* <img src={contentImg} /> */}
//           <Image srcValue={contentImg} alt="modalImg"/>
//         </a>
//       </div>
//     </div>,
//     document.getElementById(elementId)!
//   );
// };

Modal.Error = Error;
// Modal.ImageAds = ImageAds;

export default Modal;
