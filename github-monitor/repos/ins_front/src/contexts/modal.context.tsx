import { createContext, useContext, useState } from "react";
import Modal from "../components/modal/Modal";

interface ReactNodeProps {
  children: React.ReactNode;
}

type ModalInfoType = {
  title?: string;
  content?: string;
  confirmFn?: () => void;
  confirmText?: string;
};

// type ModalImgAdsType = {
//   contentImg?: string;
//   position?: "center" | "bottom";
//   elementId?: "img-dialog-center" | "img-dialog-bottom";
//   targetLink?: string;
// };

export type ModalContextType = {
  info: (value: ModalInfoType) => void;
  // imgAds: (value: ModalImgAdsType) => void;
};

export const ModalContext = createContext<ModalContextType | null>({
  info: () => {},
  // imgAds: () => {},
});

export const ModalProvider: React.FC<ReactNodeProps> = ({ children }) => {
  // const alertDialogRef = useRef<HTMLDialogElement | null>(null);
  // const imgDialogCenterRef = useRef<HTMLDialogElement | null>(null);
  // const imgDialogBottomRef = useRef<HTMLDialogElement | null>(null);

  const [errorModalVal, setErrorModalVal] = useState<ModalInfoType>({
    title: "",
    content: "",
    confirmFn: () => {},
    confirmText: "",
  });
  const [openModalError, setOpenModalError] = useState(false);
  // const [modalCenterImgAdsVal, setModalCenterImgAdsVal] =
  //   useState<ModalImgAdsType>({
  //     contentImg: "",
  //     position: "center",
  //     elementId: "img-dialog-center",
  //   });

  // const [modalBottomImgAdsVal, setModalBottomImgAdsVal] =
  //   useState<ModalImgAdsType>({
  //     contentImg: "",
  //     position: "bottom",
  //     elementId: "img-dialog-bottom",
  //   });

  const info = (value: ModalInfoType) => {
    setErrorModalVal(value);
    setOpenModalError(true);
    // if (alertDialogRef.current) alertDialogRef.current.showModal();
  };

  // const imgAds = (value: ModalImgAdsType) => {
  //   if (value.elementId === "img-dialog-center") {
  //     setModalCenterImgAdsVal(value);
  //     // if (imgDialogCenterRef.current) imgDialogCenterRef.current.showModal();
  //     if (imgDialogCenterRef.current) {
  //       imgDialogCenterRef.current.classList.add("dialog-open");
  //       imgDialogCenterRef.current.classList.remove("dialog-close");
  //     }
  //   } else {
  //     setModalBottomImgAdsVal(value);
  //     // if (imgDialogBottomRef.current) imgDialogBottomRef.current.showModal();
  //     if (imgDialogBottomRef.current) {
  //       imgDialogBottomRef.current.classList.add("dialog-open");
  //       imgDialogBottomRef.current.classList.remove("dialog-close");
  //     }
  //   }
  // };

  // const value = { info, imgAds };
  const value = { info };

  return (
    <ModalContext.Provider value={value}>
      {children}
      {openModalError && (
        <Modal.Error
          // dialogRef={alertDialogRef}
          headerTitle={errorModalVal?.title}
          content={errorModalVal?.content}
          confirmFn={errorModalVal?.confirmFn}
          confirmText={errorModalVal?.confirmText}
          open={openModalError}
          setOpenModalError={setOpenModalError}
        />
      )}
      {/* <Modal.ImageAds
        dialogRef={imgDialogCenterRef}
        contentImg={modalCenterImgAdsVal?.contentImg}
        position="center"
        elementId="img-dialog-center"
        targetLink={modalCenterImgAdsVal.targetLink}
      />
      <Modal.ImageAds
        dialogRef={imgDialogBottomRef}
        contentImg={modalBottomImgAdsVal?.contentImg}
        position="bottom"
        elementId="img-dialog-bottom"
        targetLink={modalBottomImgAdsVal.targetLink}
      /> */}
    </ModalContext.Provider>
  );
};

export const useModal = () => {
  return useContext(ModalContext) as ModalContextType;
};
